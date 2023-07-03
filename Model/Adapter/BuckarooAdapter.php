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
use Buckaroo\Config\DefaultConfig;
use Buckaroo\Config\Config;
use Buckaroo\Exceptions\BuckarooException;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Magento2\Gateway\Request\CreditManagement\BuilderComposite;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Software\Data;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Locale\Resolver;

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
     * @var Log
     */
    protected Log $logger;

    /**
     * @var array|null
     */
    private array $mapPaymentMethods;

    /**
     * @param Account $configProviderAccount
     * @param Encryptor $encryptor
     * @param Log $logger
     * @param ProductMetadataInterface $productMetadata
     * @param Resolver $localeResolver
     * @param array|null $mapPaymentMethods
     * @throws \Exception
     */
    public function __construct(
        Account $configProviderAccount,
        Encryptor $encryptor,
        Log $logger,
        ProductMetadataInterface $productMetadata,
        Resolver $localeResolver,
        array $mapPaymentMethods = null
    ) {
        $this->mapPaymentMethods = $mapPaymentMethods;
        $this->logger = $logger;

        $this->buckaroo = new BuckarooClient(new DefaultConfig(
            $encryptor->decrypt($configProviderAccount->getMerchantKey()),
            $encryptor->decrypt($configProviderAccount->getSecretKey()),
            $configProviderAccount->getActive() == 2 ? Config::LIVE_MODE : Config::TEST_MODE,
            null,
            null,
            null,
            null,
            $productMetadata->getName() . ' - ' . $productMetadata->getEdition(),
            $productMetadata->getVersion(),
            'Buckaroo',
            'Magento2',
            Data::BUCKAROO_VERSION,
            str_replace('_', '-', $localeResolver->getLocale())
        ));
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

            return $payment->{$action}($data);
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . $th);
            throw $th;
        }
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
            return $this->buckaroo->method('ideal')->issuers();
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . $th);
            return [];
        }
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
     * @return TransactionResponse|Buckaroo\PaymentMethods\CreditManagement\CreditManagement
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
     * Validate request
     *
     * @throws BuckarooException
     * @throws \Exception
     */
    public function validate($postData, $authHeader, $uri): bool
    {
        $replyHandler = new ReplyHandler($this->buckaroo->client()->config(), $postData, $authHeader, $uri);
        $replyHandler->validate();
        return $replyHandler->isValid();
    }
}
