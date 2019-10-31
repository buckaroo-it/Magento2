<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace TIG\Buckaroo\Soap;

use TIG\Buckaroo\Model\ConfigProvider\PrivateKey;

class ClientFactory extends \Magento\Framework\Webapi\Soap\ClientFactory
{
    /**
     * @var PrivateKey
     */
    public $configProviderPrivateKey;

    /** @var null|\Magento\Store\Model\Store */
    private $store = null;

    /**
     * @param PrivateKey $configProviderPrivateKey
     */
    public function __construct(PrivateKey $configProviderPrivateKey)
    {
        $this->configProviderPrivateKey = $configProviderPrivateKey;
    }

    /**
     * Factory method for Client\SoapClientWSSEC
     *
     * @param string $wsdl
     * @param array  $options
     *
     * @return Client\SoapClientWSSEC
     * @throws \TIG\Buckaroo\Exception|\LogicException
     */
    public function create($wsdl, array $options = [])
    {
        $privateKey = $this->configProviderPrivateKey->getPrivateKey($this->getStore());

        $client = new Client\SoapClientWSSEC($wsdl, $options);
        $client->loadPem($privateKey);

        return $client;
    }

    /**
     * @param $store
     *
     * @return $this
     */
    public function setStore($store)
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return \Magento\Store\Model\Store|null
     */
    public function getStore()
    {
        return $this->store;
    }
}
