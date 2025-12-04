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
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Magento2\Exception;
use Magento\Framework\Phrase;
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
    protected $buckaroo;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var array|null
     */
    private $mapPaymentMethods;

    /**
     * @var ConfigProviderFactory
     */
    private $configProviderFactory;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ConfigProviderFactory    $configProviderFactory
     * @param Encryptor                $encryptor
     * @param BuckarooLoggerInterface  $logger
     * @param ProductMetadataInterface $productMetadata
     * @param Resolver                 $localeResolver
     * @param StoreManagerInterface    $storeManager
     * @param array|null               $mapPaymentMethods
     */
    public function __construct(
        ConfigProviderFactory $configProviderFactory,
        Encryptor $encryptor,
        BuckarooLoggerInterface $logger,
        ProductMetadataInterface $productMetadata,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        ?array $mapPaymentMethods = null
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
     * @param array  $data
     *
     * @throws \Throwable
     *
     * @return TransactionResponse
     */
    public function execute(string $action, string $method, array $data): TransactionResponse
    {
        $orderStoreId = null;

        if (isset($data['orderStoreId'])) {
            $orderStoreId = (int)$data['orderStoreId'];

            $this->logger->addDebug(sprintf(
                '[SDK] | [Adapter] | [%s:%s] - Using orderStoreId from data: %s | method: %s | action: %s',
                __METHOD__,
                __LINE__,
                $orderStoreId,
                $method,
                $action
            ));
        } else {
            $this->logger->addWarning(sprintf(
                '[SDK] | [Adapter] | [%s:%s] - orderStoreId NOT in data (action: %s, method: %s). Will use current store context.',
                __METHOD__,
                __LINE__,
                $action,
                $method
            ));
        }

        $skipActiveCheck =
            $action === 'refund'
            || $action === 'capture'
            || $this->isCreditManagementOfType($data, BuilderComposite::TYPE_REFUND)
            || $this->isCreditManagementOfType($data, BuilderComposite::TYPE_VOID);

        $this->setClientSdk($method, $orderStoreId, $skipActiveCheck);
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
     * @param string   $paymentMethod
     * @param int|null $orderStoreId  Store ID from the order (for refund/capture operations)
     * @param bool     $skipActiveCheck
     *
     * @throws \Exception
     */
    private function setClientSdk($paymentMethod = '', ?int $orderStoreId = null, bool $skipActiveCheck = false): void
    {
        /** @var Account $configProviderAccount */
        $configProviderAccount = $this->configProviderFactory->get('account');

        $storeId = $orderStoreId ?? $this->storeManager->getStore()->getId();

        if ($orderStoreId !== null) {
            $this->logger->addDebug(sprintf(
                '[SDK] | [Adapter] | [%s:%s] - Checking payment method active status in order store ID: %s (paymentMethod: %s)',
                __METHOD__,
                __LINE__,
                $storeId,
                $paymentMethod
            ));
        }

        $accountMode = $configProviderAccount->getActive($storeId);
        $clientMode = $this->getClientMode($accountMode, $storeId, $paymentMethod, $skipActiveCheck);

        $this->buckaroo = new BuckarooClient(new DefaultConfig(
            $this->encryptor->decrypt($configProviderAccount->getMerchantKey($storeId)),
            $this->encryptor->decrypt($configProviderAccount->getSecretKey($storeId)),
            $clientMode,
            null, // currency
            null, // returnURL
            null, // returnURLCancel
            null, // pushURL
            $this->productMetadata->getName() . ' - ' . $this->productMetadata->getEdition(),
            $this->productMetadata->getVersion(),
            'Buckaroo',
            'Magento2',
            Data::BUCKAROO_VERSION,
            str_replace('_', '-', $this->localeResolver->getLocale()),
            null, // Disable SDK logging - SDK's BuckarooException handles null gracefully in log() method
            null, // timeout
            null  // connectTimeout
        ));
    }

    /**
     * Confirms the validity of the provided merchant key and secret key using the Buckaroo SDK.
     *
     * @param string $merchantKey The merchant key to validate.
     * @param string $secretKey   The secret key to validate.
     *
     * @throws \Exception
     *
     * @return bool Returns true if the credentials are valid, false otherwise.
     */
    public function confirmCredential(string $merchantKey, string $secretKey): bool
    {
        $this->buckaroo = new BuckarooClient(new DefaultConfig(
            $merchantKey,
            $secretKey,
            null, // mode
            null, // currency
            null, // returnURL
            null, // returnURLCancel
            null, // pushURL
            null, // platformName
            null, // platformVersion
            null, // moduleSupplier
            null, // moduleName
            null, // moduleVersion
            null, // culture
            null, // channel
            null, // Disable SDK logging - SDK handles null gracefully
            null, // timeout
            null  // connectTimeout
        ));
        return $this->buckaroo->confirmCredential();
    }

    /**
     * Get client mode base on account mode and payment method mode
     *
     * @param int|string $accountMode
     * @param int|string $storeId
     * @param string     $paymentMethod
     * @param bool       $skipActiveCheck
     *
     * @throws Exception
     *
     * @return string
     */
    private function getClientMode($accountMode, $storeId, string $paymentMethod = '', bool $skipActiveCheck = false): string
    {
        $clientMode = Config::TEST_MODE;

        if ($accountMode == 0) {
            throw new Exception(__('The Buckaroo Module is OFF'));
        }

        if ($accountMode == 1) {
            $clientMode = Config::LIVE_MODE;

            if ($paymentMethod) {
                /** @var  AbstractConfigProvider $configProviderPaymentMethod */
                $configProviderPaymentMethod = $this->configProviderFactory->get($paymentMethod);
                $isActivePaymentMethod = $configProviderPaymentMethod->getActive($storeId);
                if ($isActivePaymentMethod == Enablemode::ENABLE_OFF) {
                    $this->logger->addError(sprintf(
                        '[SDK] | [Adapter] | [%s:%s] - Payment method %s is not active in store ID: %s. ' .
                        'Ensure payment method is enabled in the store where the order was placed.',
                        __METHOD__,
                        __LINE__,
                        $paymentMethod,
                        $storeId
                    ));
                    throw new Exception(__(
                        'Payment method %1 is not active in store ID %2. Enable it in Stores > Configuration for this store view.',
                        $paymentMethod,
                        $storeId
                    ));
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
     *
     * @return string
     */
    protected function getMethodName(string $method): string
    {
        return $this->mapPaymentMethods[$method] ?? $method;
    }

    /**
     * Check if we have credit management information of type
     *
     * @param array  $data
     * @param string $type
     *
     * @return bool
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
     *
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
     * @param array  $data
     * @param string $type
     *
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
     * @throws \Throwable
     *
     * @return array
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
     * @param mixed $postData
     * @param mixed $authHeader
     * @param mixed $uri
     *
     * @throws Exception
     */
    public function validate($postData, $authHeader, $uri): bool
    {
        try {
            $this->setClientSdk();
            $replyHandler = new ReplyHandler($this->buckaroo->client()->config(), $postData, $authHeader, $uri);
            $replyHandler->validate();
            return $replyHandler->isValid();
        } catch (\Buckaroo\Exceptions\BuckarooException $e) {
            throw new Exception(new Phrase($e->getMessage()), $e, $e->getCode());
        }
    }
}
