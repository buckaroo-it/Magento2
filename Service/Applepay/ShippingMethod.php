<?php
namespace Buckaroo\Magento2\Service\Applepay;

use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Api\ShipmentEstimationInterface;

use Buckaroo\Magento2\Logging\Log as BuckarooLog;

class ShippingMethod
{
    /**
     * @var ShippingMethodConverter
     */
    private ShippingMethodConverter $shippingMethodConverter;

    /**
     * @var ExtensibleDataObjectConverter
     */
    private ExtensibleDataObjectConverter $dataObjectConverter;

    /**
     * @var TotalsCollector
     */
    private TotalsCollector $totalsCollector;

    /**
     * @var BuckarooLog
     */
    private BuckarooLog $logger;

    /**
     * @var ShipmentEstimationInterface
     */
    protected ShipmentEstimationInterface $shipmentEstimation;

    /**
     * @param ExtensibleDataObjectConverter $dataObjectConverter
     * @param ShippingMethodConverter $shippingMethodConverter
     * @param TotalsCollector $totalsCollector
     * @param BuckarooLog $logger
     * @param ShipmentEstimationInterface $shipmentEstimation
     */
    public function __construct(
        ExtensibleDataObjectConverter $dataObjectConverter,
        ShippingMethodConverter $shippingMethodConverter,
        TotalsCollector $totalsCollector,
        BuckarooLog $logger,
        ShipmentEstimationInterface $shipmentEstimation

    ) {
        $this->dataObjectConverter = $dataObjectConverter;
        $this->shippingMethodConverter = $shippingMethodConverter;
        $this->totalsCollector = $totalsCollector;
        $this->logger = $logger;
        $this->shipmentEstimation = $shipmentEstimation;

    }

    public function getAvailableMethods($cart)
    {
        $this->logger->addDebug('Starting getAvailableMethods process.');

        $address = $cart->getShippingAddress();
        $address->setLimitCarrier(null);

        $this->logger->addDebug('Address: '. json_encode($address));
        $address->setQuote($cart);
        $address->setCollectShippingRates(true);


        $testtt = $this->shipmentEstimation->estimateByExtendedAddress(
            $cart->getId(),
            $cart->getShippingAddress()
        );

        $this->logger->addDebug('testing shipment estimation::::: '. json_encode($testtt));

        try {
            $this->totalsCollector->collectAddressTotals($cart, $address);
        } catch (\Exception $e) {
            $this->logger->addError('Error collecting address totals: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to collect shipping rates.'));
        }

        $methods = [];
        $shippingRates = $address->getGroupedAllShippingRates();

        $this->logger->addDebug('shipping rates:::::'. json_encode($shippingRates));

        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $this->logger->addDebug('carrier rate:'. json_encode($rate));
//                $methodData = $this->dataObjectConverter->toFlatArray(
//                    $this->shippingMethodConverter->modelToDataObject($rate, $cart->getQuoteCurrencyCode()),
//                    [],
//                    ShippingMethodInterface::class
//                );
                $methods[] = $this->shippingMethodConverter->modelToDataObject(
                    $rate,
                    $cart->getQuoteCurrencyCode()
                );
            }
        }

        $this->logger->addDebug('Shipping methods retrieved successfully.');

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
