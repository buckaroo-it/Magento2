<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Articles;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

abstract class AbstractArticlesDataBuilder extends AbstractDataBuilder
{
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';
    /**
     * Max articles that can be handled by payment method
     */
    const MAX_ARTICLE_COUNT = 99;

    protected ScopeConfigInterface $scopeConfig;

    protected BuckarooLog $buckarooLog;

    protected int $payRemainder = 0;

    protected QuoteFactory $quoteFactory;

    protected Calculation $taxCalculation;

    protected Config $taxConfig;

    protected BuckarooFee $configProviderBuckarooFee;

    protected SoftwareData $softwareData;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param BuckarooLog $buckarooLog
     * @param QuoteFactory $quoteFactory
     * @param Calculation $taxCalculation
     * @param Config $taxConfig
     * @param BuckarooFee $configProviderBuckarooFee
     * @param SoftwareData $softwareData
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        BuckarooLog          $buckarooLog,
        QuoteFactory         $quoteFactory,
        Calculation          $taxCalculation,
        Config               $taxConfig,
        BuckarooFee          $configProviderBuckarooFee,
        SoftwareData         $softwareData
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->buckarooLog = $buckarooLog;
        $this->quoteFactory = $quoteFactory;
        $this->taxCalculation = $taxCalculation;
        $this->taxConfig = $taxConfig;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->softwareData = $softwareData;
    }


    protected function getRequestArticlesDataPayRemainder(): array
    {
        return $this->getArticleArrayLine(
            'PayRemainder',
            1,
            1,
            round($this->payRemainder, 2),
            $this->getTaxCategory($this->getOrder())
        );
    }

    /**
     * @param $latestKey
     * @param $articleDescription
     * @param $articleId
     * @param $articleQuantity
     * @param $articleUnitPrice
     * @param $articleVat
     *
     * @return array
     */
    public function getArticleArrayLine(
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    )
    {
        return [
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    /**
     * @param Item|\Magento\Sales\Model\Order\Invoice\Item $productItem
     * @param $includesTax
     *
     * @return mixed
     */
    public function calculateProductPrice($productItem, $includesTax)
    {
        $productPrice = $productItem->getPriceInclTax();

        if (!$includesTax) {
            if ($productItem->getDiscountAmount() >= 0.01) {
                $productPrice = $productItem->getPrice()
                    + $productItem->getTaxAmount() / $productItem->getQty();
            }
        }

        if ($productItem->getWeeeTaxAppliedAmount() > 0) {
            $productPrice += $productItem->getWeeeTaxAppliedAmount();
        }

        return $productPrice;
    }

    public function getServiceCostLine($order, &$itemsTotalAmount = 0)
    {
        $buckarooFeeLine = (double)$order->getBuckarooFeeInclTax();

        if (!$buckarooFeeLine && ($order->getBuckarooFee() >= 0.01)) {
            $this->buckarooLog->addDebug(__METHOD__ . '|5|');
            $buckarooFeeLine = (double)$order->getBuckarooFee();
        }

        $article = [];

        if ($buckarooFeeLine && $buckarooFeeLine > 0) {
            $article = $this->getArticleArrayLine(
                'Servicekosten',
                1,
                1,
                round($buckarooFeeLine, 2),
                $this->getTaxCategory($order)
            );
            $itemsTotalAmount += round($buckarooFeeLine, 2);
        }

        return $article;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @param $count
     * @return array
     */
    protected function getShippingCostsLine($order, $count, &$itemsTotalAmount = 0)
    {
        $shippingCostsArticle = [];

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $request = $this->taxCalculation->getRateRequest(null, null, null);
        $taxClassId = $this->taxConfig->getShippingTaxClass();
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

        $shippingCostsArticle = [
            'identifier' => 2,
            'description' => 'Shipping fee',
            'vatPercentage' => $this->formatShippingCostsLineVatPercentage($percent),
            'quantity' => 1,
            'price' => $this->formatPrice($shippingAmount)
        ];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }

    /**
     * Get the discount cost lines
     *
     * @return array
     */
    public function getDiscountLine()
    {
        $article = [];
        $discount = $this->getDiscountAmount();

        if ($discount >= 0) {
            return $article;
        }

        return $this->getArticleArrayLine(
            'Korting',
            1,
            1,
            round($discount, 2),
            0
        );
    }

    protected function getTaxCategory($order)
    {
        $request = $this->taxCalculation->getRateRequest(null, null, null, $order->getStore());
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($order->getStore());
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));
        return $percent;
    }

    protected function getShippingAmount($order)
    {
        return $order->getShippingInclTax();
    }

    protected function formatPrice($price)
    {
        return $price;
    }

    protected function formatShippingCostsLineVatPercentage($percent)
    {
        return $percent;
    }

    /**
     * @return float|int
     */
    protected function getDiscountAmount()
    {
        $discount = 0;
        $edition = $this->softwareData->getProductMetaData()->getEdition();

        if ($this->order->getDiscountAmount() < 0) {
            $discount -= abs((double)$this->order->getDiscountAmount());
        }

        if ($edition == 'Enterprise' && $this->order->getCustomerBalanceAmount() > 0) {
            $discount -= abs((double)$this->order->getCustomerBalanceAmount());
        }

        return $discount;
    }
}
