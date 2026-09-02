<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Service;

use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

class ShippingMethodsService
{
    /**
     * @var ShipmentEstimationInterface
     */
    protected $shipmentEstimation;

    /**
     * @param ShipmentEstimationInterface $shipmentEstimation
     */
    public function __construct(
        ShipmentEstimationInterface $shipmentEstimation
    ) {
        $this->shipmentEstimation = $shipmentEstimation;
    }

    /**
     * Retrieve available shipping methods by the quote's address.
     *
     * @param Quote            $quote
     * @param AddressInterface $address
     *
     * @throws InputException
     *
     * @return array
     */
    public function getAvailableShippingMethods(Quote $quote, AddressInterface $address): array
    {
        $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
            $quote->getId(),
            $quote->getShippingAddress()
        );

        $shippingMethodsResult = [];
        if (count($shippingMethods) > 0) {
            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethodsResult[] = [
                    'carrier_code'   => (string)$shippingMethod->getCarrierCode(),
                    'carrier_title'  => (string)$shippingMethod->getCarrierTitle(),
                    'price_incl_tax' => round($shippingMethod->getAmount(), 2),
                    'method_code'    => (string)$shippingMethod->getCarrierCode() . '_' . (string)$shippingMethod->getMethodCode(),
                    'method_title'   => (string)$shippingMethod->getMethodTitle(),
                ];
            }

            // Optionally, set the first available shipping method as default.
            $firstMethod = array_shift($shippingMethods);
            $address->setShippingMethod($firstMethod->getCarrierCode() . '_' . $firstMethod->getMethodCode());
        }

        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        return $shippingMethodsResult;
    }

    /**
     * Add the first available shipping method to the address and recalculate rates.
     *
     * @param Address $address
     * @param Quote   $quote
     *
     * @throws InputException
     *
     * @return Quote
     */
    public function addFirstShippingMethod(Address $address, Quote $quote): Quote
    {
        if (empty($address->getShippingMethod())) {
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
                $quote->getId(),
                $quote->getShippingAddress()
            );

            if (count($shippingMethods) > 0) {
                $firstMethod = array_shift($shippingMethods);
                $address->setShippingMethod($firstMethod->getCarrierCode() . '_' . $firstMethod->getMethodCode());
            }
        }
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        return $quote;
    }
}
