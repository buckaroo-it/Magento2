<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

class ShippingMethodsService
{
    /**
     * @var \Magento\Quote\Api\ShipmentEstimationInterface
     */
    protected $shipmentEstimation;

    public function __construct(
        ShipmentEstimationInterface $shipmentEstimation
    ) {
        $this->shipmentEstimation = $shipmentEstimation;
    }

    /**
     * Get shipping methods by address
     *
     * @param Quote $quote
     * @param AddressInterface $address
     * @return array
     */
    public function getAvailableShippingMethods($quote, $address)
    {
        $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
            $quote->getId(),
            $quote->getShippingAddress()
        );

        $shippingMethodsResult = [];
        if (count($shippingMethods)) {
            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethodsResult[] = [
                    'carrier_title' => $shippingMethod->getCarrierTitle(),
                    'price_incl_tax' => round($shippingMethod->getAmount(), 2),
                    'method_code' => $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode(),
                    'method_title' => $shippingMethod->getMethodTitle(),
                ];
            }

            $shippingMethod = array_shift($shippingMethods);
            $address->setShippingMethod($shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode());

        }

        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        return $shippingMethodsResult;
    }

    /**
     * Add first found shipping method to the shipping address &
     * recalculate shipping totals
     *
     * @param Address $address
     *
     * @return Quote
     */
    public function addFirstShippingMethod(Address $address, Quote $quote)
    {
        if ($address->getShippingMethod() === null) {
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
                $quote->getId(),
                $quote->getShippingAddress()
            );

            if (count($shippingMethods)) {
                $shippingMethod = array_shift($shippingMethods);
                $address->setShippingMethod($shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode());
            }
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        return $quote;
    }
}
