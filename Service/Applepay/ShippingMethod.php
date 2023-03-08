<?php

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\TotalsCollector;

class ShippingMethod
{
    private ExtensibleDataObjectConverter $dataObjectConverter;
    private ShippingMethodConverter $shippingMethodConverter;
    private TotalsCollector $totalsCollector;
    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @var ShipmentEstimationInterface
     */
    private $shipmentEstimation;

    public function __construct(
        ExtensibleDataObjectConverter $dataObjectConverter,
        ShippingMethodConverter $shippingMethodConverter,
        TotalsCollector $totalsCollector,
        ShipmentEstimationInterface $shipmentEstimation,
        Log $logging
    ) {
        $this->dataObjectConverter = $dataObjectConverter;
        $this->shippingMethodConverter = $shippingMethodConverter;
        $this->totalsCollector = $totalsCollector;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->logging = $logging;
    }

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


    public function getAvailableMethods($cart)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        $address = $cart->getShippingAddress();

        $address->setLimitCarrier(null);
        $address->setQuote($cart);
        $address->setCollectShippingRates(true);
        $this->totalsCollector->collectAddressTotals($cart, $address);
        $methods = [];

        $shippingRates = $address->getGroupedAllShippingRates();
        $this->logging->addDebug(__METHOD__ . '|2|' . var_export(array_keys($shippingRates), true));
        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methodData = $this->dataObjectConverter->toFlatArray(
                    $this->shippingMethodConverter->modelToDataObject($rate, $cart->getQuoteCurrencyCode()),
                    [],
                    ShippingMethodInterface::class
                );
                $methods[] = $this->processMoneyTypeData(
                    $methodData,
                    $cart->getQuoteCurrencyCode()
                );
            }
        }
        return $methods;
    }

    /**
     * Get list of available shipping methods
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Framework\Api\ExtensibleDataInterface $address
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface[]
     */
    private function getShippingMethods2(Quote $quote)
    {
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setCollectShippingRates(true);

        $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

        $shippingRates = $shippingAddress->getGroupedAllShippingRates();
        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methodData = $this->dataObjectConverter->toFlatArray(
                    $this->shippingMethodConverter->modelToDataObject($rate, $quote->getQuoteCurrencyCode()),
                    [],
                    ShippingMethodInterface::class
                );
                $methods[] = $this->processMoneyTypeData(
                    $methodData,
                    $quote->getQuoteCurrencyCode()
                );
            }
        }
        return $methods;
    }

    private function processMoneyTypeData(array $data, string $quoteCurrencyCode): array
    {
        if (isset($data['amount'])) {
            $data['amount'] = ['value' => $data['amount'], 'currency' => $quoteCurrencyCode];
        }

        /** @deprecated The field should not be used on the storefront */
        $data['base_amount'] = null;

        if (isset($data['price_excl_tax'])) {
            $data['price_excl_tax'] = ['value' => $data['price_excl_tax'], 'currency' => $quoteCurrencyCode];
        }

        if (isset($data['price_incl_tax'])) {
            $data['price_incl_tax'] = ['value' => $data['price_incl_tax'], 'currency' => $quoteCurrencyCode];
        }
        return $data;
    }
}
