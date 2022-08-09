<?php

namespace Buckaroo\Magento2\Model\Adapter;

use Buckaroo\BuckarooClient;
use Buckaroo\Config\Config;
use Buckaroo\Exceptions\SDKException;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Encryption\Encryptor;
use Buckaroo\Handlers\Reply\ReplyHandler;

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

    public function __construct(Account $configProviderAccount, Encryptor $encryptor)
    {
        $this->encryptor = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $websiteKey = $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey());
        $secretKey = $this->encryptor->decrypt($this->configProviderAccount->getSecretKey());
        $envMode = $this->configProviderAccount->getActive() == 2 ? Config::LIVE_MODE : Config::TEST_MODE;
        $this->buckaroo = new BuckarooClient($websiteKey, $secretKey, $envMode);
    }

    public function pay($method, $data) {
        return $this->buckaroo->method($method)->pay($data);
    }

    public function payInInstallments($method, $data) {
        return $this->buckaroo->method($method)->payInInstallments($data);
    }

    public function refund($method, $data) {
        return $this->buckaroo->method($method)->refund($data);
    }

    /**
     * @throws SDKException
     */
    public function validate($post_data, $auth_header, $uri): bool
    {
        $reply_handler = new ReplyHandler($this->buckaroo->client()->config(), $post_data, $auth_header, $uri);
        $reply_handler->validate();
        return $reply_handler->isValid();
    }
}
