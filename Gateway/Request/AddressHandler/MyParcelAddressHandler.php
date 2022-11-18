<?php

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class MyParcelAddressHandler extends AbstractAddressHandler
{
    public BuckarooHelper $helper;

    public function __construct(Log $buckarooLogger, BuckarooHelper $helper)
    {
        $this->helper = $helper;
        parent::__construct($buckarooLogger);
    }

    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        $this->buckarooLogger->addDebug(__METHOD__ . '|1|');
        $myparcelFetched = false;
        if ($myparcelOptions = $order->getData('myparcel_delivery_options')) {
            if (!empty($myparcelOptions)) {
                try {
                    $myparcelOptions = json_decode($myparcelOptions, true);
                    $isPickup = $myparcelOptions['isPickup'] ?? false;
                    if ($isPickup) {
                        $this->updateShippingAddressByMyParcel($myparcelOptions['pickupLocation'], $requestData);
                        $myparcelFetched = true;
                    }
                } catch (\JsonException $je) {
                    $this->buckarooLogger->addDebug(__METHOD__ . '|2|' . ' Error related to json_decode (MyParcel plugin compatibility)');
                }
            }
        }

        if (!$myparcelFetched) {
            $this->buckarooLogger->addDebug(__METHOD__ . '|10|');
            if ((strpos((string)$order->getShippingMethod(), 'myparcelnl') !== false)
                &&
                (strpos((string)$order->getShippingMethod(), 'pickup') !== false)
            ) {
                $this->buckarooLogger->addDebug(__METHOD__ . '|15|');
                if ($this->helper->getCheckoutSession()->getMyParcelNLBuckarooData()) {
                    if ($myParcelNLData = $this->helper->getJson()->unserialize($this->helper->getCheckoutSession()->getMyParcelNLBuckarooData())) {
                        $this->buckarooLogger->addDebug(__METHOD__ . '|20|');
                        $this->updateShippingAddressByMyParcel($myParcelNLData, $requestData);
                    }
                }
            }
        }

        return $order;
    }

    protected function updateShippingAddressByMyParcel($myParcelLocation, &$requestData)
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

    protected function updateShippingAddressByMyParcelV2($myParcelLocation, &$requestData)
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
