<?php

namespace Buckaroo\Magento2\Model\Adapter;

use Buckaroo\BuckarooClient;
use Buckaroo\Config\Config;
use Buckaroo\Exceptions\BuckarooException;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Encryption\Encryptor;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Transaction\Response\TransactionResponse;

class BuckarooAdapter
{
    /**
     * @var BuckarooClient
     */
    private BuckarooClient $buckaroo;

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
        $this->encryptor = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $this->mapPaymentMethods = $mapPaymentMethods;
        $websiteKey = $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey());
        $secretKey = $this->encryptor->decrypt($this->configProviderAccount->getSecretKey());
        $envMode = $this->configProviderAccount->getActive() == 2 ? Config::LIVE_MODE : Config::TEST_MODE;
        $this->buckaroo = new BuckarooClient($websiteKey, $secretKey, $envMode);
    }

    public function pay($method, $data): TransactionResponse
    {
        return $this->buckaroo->method($this->getMethodName($method))->pay($data);
    }

    public function payInInstallments($method, $data): TransactionResponse
    {
        return $this->buckaroo->method($this->getMethodName($method))->payInInstallments($data);
    }

    public function reserve($method, $data): TransactionResponse
    {
        return $this->buckaroo->method($this->getMethodName($method))->reserve($data);
    }


    public function refund($method, $data): TransactionResponse
    {
        return $this->buckaroo->method($this->getMethodName($method))->refund($data);
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

    private function getMethodName($method)
    {
        return isset($this->mapPaymentMethods[$method]) ? $this->mapPaymentMethods[$method] : $method;
    }
}
