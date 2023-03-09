<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
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

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\ShippingMethodManagement;

class SetAddress
{
    /**
     * @var Log
     */
    protected $logger;
    /**
     * @var TotalsCollector
     */
    protected $totalsCollector;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * Shipping method converter
     *
     * @var ShippingMethodConverter
     */
    protected $converter;
    /**
     * @var \Magento\Quote\Api\ShipmentEstimationInterface
     */
    protected $shipmentEstimation;

    /**
     * Apple Pay common constructor
     *
     * @param Context $context
     * @param Log $logger
     * @param TotalsCollector $totalsCollector
     * @param ShippingMethodConverter $converter
     * @param CustomerSession|null $customerSession
     */
    public function __construct(
        Context                 $context,
        Log                     $logger,
        TotalsCollector         $totalsCollector,
        ShippingMethodConverter $converter,
        ShipmentEstimationInterface $shipmentEstimation,
        CustomerSession         $customerSession = null
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->totalsCollector = $totalsCollector;
        $this->converter = $converter;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->customerSession = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
    }


}
