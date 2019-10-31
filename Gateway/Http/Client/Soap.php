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

namespace TIG\Buckaroo\Gateway\Http\Client;

use Magento\Payment\Model\Method\Logger;
use TIG\Buckaroo\Soap\ClientFactory;

/**
 * Class Soap
 *
 * @package TIG\Buckaroo\Gateway\Http\Client
 */
class Soap extends \Magento\Payment\Gateway\Http\Client\Soap
{
    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @param Logger                $logger
     * @param ClientFactory         $clientFactory
     * @param EmptyConverter | null $converter
     */
    public function __construct(
        Logger $logger,
        ClientFactory $clientFactory,
        EmptyConverter $converter
    ) {
        parent::__construct($logger, $clientFactory, $converter);

        $this->clientFactory = $clientFactory;
    }

    /**
     * @param null|\Magento\Store\Model\Store $store
     *
     * @return $this
     */
    public function setStore($store)
    {
        $this->clientFactory->setStore($store);

        return $this;
    }
}
