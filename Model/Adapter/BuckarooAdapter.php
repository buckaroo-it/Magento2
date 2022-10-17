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

    public function __construct(Account $configProviderAccount, Encryptor $encryptor, array $mapPaymentMethods = null)
    {
        $this->mapPaymentMethods = $mapPaymentMethods;
  
        $this->buckaroo = new BuckarooClient(
            $encryptor->decrypt($configProviderAccount->getMerchantKey()),
            $encryptor->decrypt($configProviderAccount->getSecretKey()),
            $configProviderAccount->getActive() == 2 ? Config::LIVE_MODE : Config::TEST_MODE
        );
    }

    public function execute(string $action, string $method, array $data): TransactionResponse
    {
        $payment = $this->buckaroo->method($this->getMethodName($method));

        if ($this->hasCreditManagement($data)) {
            $payment = $payment->combine($this->getCreditManagementBody($data));
        }

        if($this->isCreditManagementRefund($data)) {
            return $this->getCreditNoteBody($data);
        }

        return $payment->{$action}($data);
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
     * Check if has credit management
     * @param array $data
     * @return bool
     */
    protected function hasCreditManagement(array $data): bool
    {
        return isset($data[BuilderComposite::TYPE_ORDER]) &&
            is_array($data[BuilderComposite::TYPE_ORDER]) &&
            count($data[BuilderComposite::TYPE_ORDER]) > 0;
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
     */
    protected function getCreditNoteBody(array $data)
    {
        return $this->buckaroo->method('credit_management')
        ->createCreditNote(
            $data[BuilderComposite::TYPE_REFUND]
        );
    }

    protected function isCreditManagementRefund(array $data): bool
    {
        return isset($data[BuilderComposite::TYPE_REFUND]) &&
            is_array($data[BuilderComposite::TYPE_REFUND]) &&
            count($data[BuilderComposite::TYPE_REFUND]) > 0;
    }
}
