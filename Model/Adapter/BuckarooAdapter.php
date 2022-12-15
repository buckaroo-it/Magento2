<?php

namespace Buckaroo\Magento2\Model\Adapter;

use Buckaroo\Config\Config;
use Buckaroo\BuckarooClient;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Exceptions\BuckarooException;
use Magento\Framework\Encryption\Encryptor;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Gateway\Request\CreditManagement\BuilderComposite;
use Buckaroo\Magento2\Logging\Log;

class BuckarooAdapter
{
    /**
     * @var BuckarooClient
     */
    protected BuckarooClient $buckaroo;

    /**
     * @var Account
     */
    private Account $configProviderAccount;

    /**
     * @var Encryptor
     */
    protected Encryptor $encryptor;

    private array $mapPaymentMethods;

    protected $logger;

    public function __construct(
        Account $configProviderAccount,
        Encryptor $encryptor,
        array $mapPaymentMethods = null,
        Log $logger
    ) {
        $this->mapPaymentMethods = $mapPaymentMethods;
        $this->logger = $logger;

        $this->buckaroo = new BuckarooClient(
            $encryptor->decrypt($configProviderAccount->getMerchantKey()),
            $encryptor->decrypt($configProviderAccount->getSecretKey()),
            $configProviderAccount->getActive() == 2 ? Config::LIVE_MODE : Config::TEST_MODE
        );
    }

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
            $this->logger->addDebug(__METHOD__ . (string)$th);
            throw $th;
        }
    }

    /**
     * @throws BuckarooException
     */
    public function validate($post_data, $auth_header, $uri): bool
    {
        $reply_handler = new ReplyHandler($this->buckaroo->client()->config(), $post_data, $auth_header, $uri);
        $reply_handler->validate();
        return $reply_handler->isValid();
    }

    protected function getMethodName($method)
    {
        return $this->mapPaymentMethods[$method] ?? $method;
    }

    /**
     * Get credit management body
     *
     * @param array $data
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
     */
    protected function createCreditNote(array $data, $type = BuilderComposite::TYPE_REFUND)
    {
        return $this->buckaroo->method('credit_management')
            ->createCreditNote(
                $data[$type]
            );
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
}
