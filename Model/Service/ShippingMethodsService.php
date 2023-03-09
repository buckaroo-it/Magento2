<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

class ShippingMethodsService
{
    /**
     * Get shipping methods by address
     *
     * @param Quote $quote
     * @param AddressInterface $address
     * @return ShippingMethodInterface[]
     */
    public function getAvailableShippingMethods($quote, $address)
    {
        return $this->shipmentEstimation->estimateByExtendedAddress($quote, $address);
    }

    /**
     * Add first found shipping method to the shipping address &
     * recalculate shipping totals
     *
     * @param Address $address
     *
     * @return Quote
     */
    protected function addFirstShippingMethod(Address $address, Quote $quote)
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
