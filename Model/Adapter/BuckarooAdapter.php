<?php

namespace Buckaroo\Magento2\Model\Adapter;

use Buckaroo\Buckaroo;
use Buckaroo\Config\Config;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Dotenv\Dotenv;

class BuckarooAdapter
{
    /**
     * @var Buckaroo
     */
    private Buckaroo $buckaroo;

    /**
     * @var Account
     */
    private $configProviderAccount;

    public function __construct(Account $configProviderAccount)
    {
        $dotenv = Dotenv::createImmutable(__DIR__ );
        $dotenv->load();
        $this->configProviderAccount = $configProviderAccount;
        $websiteKey = $this->configProviderAccount->getMerchantKey();
        $secretKey = $this->configProviderAccount->getSecretKey();
        $envMode = $this->configProviderAccount->getActive() == 2 ? Config::LIVE_MODE : Config::TEST_MODE;
        $this->buckaroo = new Buckaroo($websiteKey, $secretKey, $envMode);
    }

    public function pay($method, $data) {
        return $this->buckaroo->payment($method)->pay($data);
    }

    public function refund($method, $data) {
        return $this->buckaroo->payment($method)->refund($data);
    }
}
