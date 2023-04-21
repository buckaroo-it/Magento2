<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class MyParcelAddressHandler extends AbstractAddressHandler
{
    /**
     * @var BuckarooHelper
     */
    public BuckarooHelper $helper;

    /**
     * @param Log $buckarooLogger
     * @param BuckarooHelper $helper
     */
    public function __construct(Log $buckarooLogger, BuckarooHelper $helper)
    {
        $this->helper = $helper;
        parent::__construct($buckarooLogger);
    }

    /**
     * Update Shipping Address By MyParcel
     *
     * @param Order $order
     * @param OrderAddressInterface $shippingAddress
     * @return Order
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        $this->buckarooLogger->addDebug(__METHOD__ . '|1|');
        $myparcelFetched = false;
        $myparcelOptions = $order->getData('myparcel_delivery_options');
        $requestData = $shippingAddress->getData();
        if (!empty($myparcelOptions)) {
            try {
                $myparcelOptions = json_decode($myparcelOptions, true);
                $isPickup = $myparcelOptions['isPickup'] ?? false;
                if ($isPickup) {
                    $this->updateShippingAddressByMyParcel(
                        $myparcelOptions['pickupLocation'],
                        $requestData
                    );
                    $myparcelFetched = true;
                }
            } catch (\JsonException $je) {
                $this->buckarooLogger->addDebug(
                    __METHOD__ . '|2|' . ' Error related to json_decode (MyParcel plugin compatibility)'
                );
            }
        }

        if (!$myparcelFetched) {
            $this->buckarooLogger->addDebug(__METHOD__ . '|10|');
            if ((strpos((string)$order->getShippingMethod(), 'myparcelnl') !== false)
                &&
                (strpos((string)$order->getShippingMethod(), 'pickup') !== false)
            ) {
                $this->buckarooLogger->addDebug(__METHOD__ . '|15|');
                if ($this->helper->getCheckoutSession()->getMyParcelNLBuckarooData()
                    && $myParcelNLData = $this->helper->getJson()->unserialize(
                        $this->helper->getCheckoutSession()->getMyParcelNLBuckarooData()
                    )
                ) {
                    $this->buckarooLogger->addDebug(__METHOD__ . '|20|');
                    $this->updateShippingAddressByMyParcel($myParcelNLData, $requestData);
                }
            }
        }


        return $order;
    }

    /**
     * Update shipping address by DPD Pickup point
     *
     * @param array $myParcelLocation
     * @param array $requestData
     * @return void
     */
    protected function updateShippingAddressByMyParcel(array $myParcelLocation, array &$requestData)
    {
        $mapping = [
            ['ShippingStreet', $myParcelLocation['street']],
            ['ShippingPostalCode', $myParcelLocation['postal_code']],
            ['ShippingCity', $myParcelLocation['city']],
            ['ShippingCountryCode', $myParcelLocation['cc']],
            ['ShippingHouseNumber', $myParcelLocation['number']],
            ['ShippingHouseNumberSuffix', $myParcelLocation['number_suffix']],
        ];

        $this->buckarooLogger->addDebug(__METHOD__ . '|1|' . var_export($mapping, true));

        $this->updateShippingAddressCommonMappingV2($mapping, $requestData);
    }

    /**
     * Set shipping address fields by DPD Parcel
     *
     * @param array $myParcelLocation
     * @param array $requestData
     * @return void
     */
    protected function updateShippingAddressByMyParcelV2(array $myParcelLocation, array &$requestData)
    {
        $mapping = [
            ['Street', $myParcelLocation['street']],
            ['PostalCode', $myParcelLocation['postal_code']],
            ['City', $myParcelLocation['city']],
            ['Country', $myParcelLocation['cc']],
            ['StreetNumber', $myParcelLocation['number']],
            ['StreetNumberAdditional', $myParcelLocation['number_suffix']],
        ];

        $this->buckarooLogger->addDebug(__METHOD__ . '|1|' . var_export($mapping, true));

        $this->updateShippingAddressCommonMapping($mapping, $requestData);
    }
}
