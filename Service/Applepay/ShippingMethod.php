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
     * @param ExtensibleDataObjectConverter $dataObjectConverter
     * @param ShippingMethodConverter $shippingMethodConverter
     * @param TotalsCollector $totalsCollector
     */
    public function __construct(
        ExtensibleDataObjectConverter $dataObjectConverter,
        ShippingMethodConverter $shippingMethodConverter,
        TotalsCollector $totalsCollector

    ) {
        $this->dataObjectConverter = $dataObjectConverter;
        $this->shippingMethodConverter = $shippingMethodConverter;
        $this->totalsCollector = $totalsCollector;

    }

    public function getAvailableMethods($cart)
    {
        $address = $cart->getShippingAddress();
        $address->setLimitCarrier(null);

        $address->setQuote($cart);
        $address->setCollectShippingRates(true);

        try {
            $this->totalsCollector->collectAddressTotals($cart, $address);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to collect shipping rates.'));
        }

        $methods = [];
        $shippingRates = $address->getGroupedAllShippingRates();

        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methods[] = $this->shippingMethodConverter->modelToDataObject($rate, $cart->getQuoteCurrencyCode());
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
