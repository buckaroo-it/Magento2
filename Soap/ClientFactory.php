<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
