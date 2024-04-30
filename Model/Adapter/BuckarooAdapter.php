<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Adapter;

use Buckaroo\BuckarooClient;
use Buckaroo\Config\Config;
use Buckaroo\Config\DefaultConfig;
use Buckaroo\Exceptions\BuckarooException;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Request\CreditManagement\BuilderComposite;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Config\Source\Enablemode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Service\Software\Data;
use Buckaroo\PaymentMethods\CreditManagement\CreditManagement;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooAdapter
{
    /**
     * @var BuckarooClient
     */
    protected BuckarooClient $buckaroo;

    /**
     * @var Encryptor
     */
    protected Encryptor $encryptor;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var array|null
     */
    private ?array $mapPaymentMethods;

    /**
     * @var ConfigProviderFactory
     */
    private ConfigProviderFactory $configProviderFactory;

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * @var Resolver
     */
    private Resolver $localeResolver;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param ConfigProviderFactory $configProviderFactory
     * @param Encryptor $encryptor
     * @param BuckarooLoggerInterface $logger
     * @param ProductMetadataInterface $productMetadata
     * @param Resolver $localeResolver
     * @param StoreManagerInterface $storeManager
     * @param array|null $mapPaymentMethods
     */
    public function __construct(
        ConfigProviderFactory $configProviderFactory,
        Encryptor $encryptor,
        BuckarooLoggerInterface $logger,
        ProductMetadataInterface $productMetadata,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        array $mapPaymentMethods = null
    ) {
        $this->mapPaymentMethods = $mapPaymentMethods;
        $this->logger = $logger;
        $this->configProviderFactory = $configProviderFactory;
        $this->encryptor = $encryptor;
        $this->productMetadata = $productMetadata;
        $this->localeResolver = $localeResolver;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute request using Buckaroo SDK
     *
     * @param string $action
     * @param string $method
     * @param array $data
     * @return TransactionResponse
     * @throws \Throwable
     */
    public function execute(string $action, string $method, array $data): TransactionResponse
    {
        $this->setClientSdk($method);
        $payment = $this->buckaroo->method($this->getMethodName($method));

        try {
            if ($this->isCreditManagementOfType($data, BuilderComposite::TYPE_ORDER)) {
                $payment = $payment->combine($this->getCreditManagementBody($data));
            }

            if ($this->isCreditManagementOfType($data, BuilderComposite::TYPE_REFUND)) {
                $this->createCreditNote($data);
            }

            if ($this->isCreditManagementOfType($data, BuilderComposite::TYPE_VOID)) {
                return $this->createCreditNote($data, BuilderComposite::TYPE_VOID);
            }

            if (isset($data['serviceVersion'])) {
                $payment->setServiceVersion($data['serviceVersion']);
                unset($data['serviceVersion']);
            }

            return $payment->{$action}($data);
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[SDK] | [Adapter] | [%s:%s] - Execute request using Buckaroo SDK | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));

            throw $th;
        }
    }

    /**
     * Set Client SDK base on account configuration and payment method configuration
     *
     * @throws \Exception
     */
    private function setClientSdk($paymentMethod = ''): void
    {
        /** @var Account $configProviderAccount */
        $configProviderAccount = $this->configProviderFactory->get('account');
        $storeId = $this->storeManager->getStore()->getId();
        $accountMode = $configProviderAccount->getActive($storeId);
        $clientMode = $this->getClientMode($accountMode, $storeId, $paymentMethod);

        $this->buckaroo = new BuckarooClient(new DefaultConfig(
            $this->encryptor->decrypt($configProviderAccount->getMerchantKey()),
            $this->encryptor->decrypt($configProviderAccount->getSecretKey()),
            $clientMode,
            null,
            null,
            null,
            null,
            $this->productMetadata->getName() . ' - ' . $this->productMetadata->getEdition(),
            $this->productMetadata->getVersion(),
            'Buckaroo',
            'Magento2',
            Data::BUCKAROO_VERSION,
            str_replace('_', '-', $this->localeResolver->getLocale())
        ));
    }

    /**
     * Confirms the validity of the provided merchant key and secret key using the Buckaroo SDK.
     *
     * @param string $merchantKey The merchant key to validate.
     * @param string $secretKey The secret key to validate.
     * @return bool Returns true if the credentials are valid, false otherwise.
     * @throws \Exception
     */
    public function confirmCredential(string $merchantKey, string $secretKey): bool
    {
        $this->buckaroo = new BuckarooClient(new DefaultConfig(
            $merchantKey,
            $secretKey
        ));
        return $this->buckaroo->confirmCredential();
    }

    /**
     * Get client mode base on account mode and payment method mode
     *
     * @param int|string $accountMode
     * @param int|string $storeId
     * @param string $paymentMethod
     * @return string
     * @throws Exception
     */
    private function getClientMode($accountMode, $storeId, string $paymentMethod = ''): string
    {
        $clientMode = Config::TEST_MODE;

        if ($accountMode == Enablemode::ENABLE_OFF) {
            throw new Exception(__('The Buckaroo Module is OFF'));
        }

        if ($accountMode == Enablemode::ENABLE_LIVE) {
            $clientMode = Config::LIVE_MODE;

            if ($paymentMethod) {
                /** @var  AbstractConfigProvider $configProviderPaymentMethod */
                $configProviderPaymentMethod = $this->configProviderFactory->get($paymentMethod);
                $isActivePaymentMethod = $configProviderPaymentMethod->getActive($storeId);
                if ($isActivePaymentMethod == Enablemode::ENABLE_OFF) {
                    throw new Exception(__('Payment method: %s is not active', $paymentMethod));
                }

                if ($isActivePaymentMethod == Enablemode::ENABLE_TEST) {
                    $clientMode = Config::TEST_MODE;
                }
            }
        }

        return $clientMode;
    }

    /**
     * Get the payment method name from SDK
     *
     * @param string $method
     * @return string
     */
    protected function getMethodName(string $method): string
    {
        return $this->mapPaymentMethods[$method] ?? $method;
    }

    /**
     * Check if we have credit management information of type
     *
     * @param array $data
     * @param string $type
     *
     * @return boolean
     */
    protected function isCreditManagementOfType(array $data, string $type): bool
    {
        return isset($data[$type]) &&
            is_array($data[$type]) &&
            count($data[$type]) > 0;
    }

    /**
     * Get credit management body
     *
     * @param array $data
     * @return TransactionResponse|CreditManagement
     */
    protected function getCreditManagementBody(array $data)
    {
        return $this->buckaroo->method('credit_management')
            ->manually()
            ->createCombinedInvoice(
                $data[BuilderComposite::TYPE_ORDER]
            );
    }

    /**
     * Get credit note body
     *
     * @param array $data
     * @param string $type
     * @return TransactionResponse
     */
    protected function createCreditNote(array $data, string $type = BuilderComposite::TYPE_REFUND): TransactionResponse
    {
        return $this->buckaroo->method('credit_management')
            ->createCreditNote(
                $data[$type]
            );
    }

    /**
     * Get ideal issuers
     *
     * @return array
     * @throws \Throwable
     */
    public function getIdealIssuers(): array
    {
        try {
            $this->setClientSdk();
            return $this->buckaroo->method('ideal')->issuers();
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[SDK] | [Adapter] | [%s:%s] - Get ideal issuers | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
            return [];
        }
    }

    /**
     * Validate request
     *
     * @throws BuckarooException
     * @throws \Exception
     */
    public function validate($postData, $authHeader, $uri): bool
    {
        $this->setClientSdk();
        $replyHandler = new ReplyHandler($this->buckaroo->client()->config(), $postData, $authHeader, $uri);
        $replyHandler->validate();
        return $replyHandler->isValid();
    }
}
