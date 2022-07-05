<?php

namespace Buckaroo\Magento2\Model\Adapter;

use Buckaroo\Buckaroo;
use Dotenv\Dotenv;

class BuckarooAdapter
{
    /**
     * @var Buckaroo
     */
    private Buckaroo $buckaroo;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ );
        $dotenv->load();
        $this->buckaroo = new Buckaroo($_ENV['BPE_WEBSITE_KEY'], $_ENV['BPE_SECRET_KEY'], $_ENV['BPE_MODE']);
    }

    public function pay($method, $data) {
        return $this->buckaroo->payment($method)->pay($data);
    }

    public function refund($method, $data) {
        return $this->buckaroo->payment($method)->refund($data);
    }
}
