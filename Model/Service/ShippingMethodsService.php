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

namespace Buckaroo\Magento2\Model\Service;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

class ShippingMethodsService
{
    /**
     * @var ShipmentEstimationInterface
     */
    protected ShipmentEstimationInterface $shipmentEstimation;

    /**
     * @param ShipmentEstimationInterface $shipmentEstimation
     */
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
    public function getAvailableShippingMethods(Quote $quote, AddressInterface $address): array
    {
        $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
            $quote->getId(),
            $quote->getShippingAddress()
        );

        $shippingMethodsResult = [];
        if (count($shippingMethods)) {
            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethodsResult[] = [
                    'carrier_title'  => $shippingMethod->getCarrierTitle(),
                    'price_incl_tax' => round($shippingMethod->getAmount(), 2),
                    'method_code'    => $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode(),
                    'method_title'   => $shippingMethod->getMethodTitle(),
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
     * Add first found shipping method to the shipping address & recalculate shipping totals
     *
     * @param Address $address
     * @param Quote $quote
     * @return Quote
     */
    public function addFirstShippingMethod(Address $address, Quote $quote): Quote
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
