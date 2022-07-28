<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

class ArticlesDataBuilder implements BuilderInterface
{
    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';
    /**
     * Max articles that can be handled by klarna
     */
    const KLARNA_MAX_ARTICLE_COUNT = 99;

    protected ScopeConfigInterface $scopeConfig;

    private BuckarooLog $buckarooLog;

    protected int $payRemainder = 0;

    protected QuoteFactory $quoteFactory;

    protected Calculation $taxCalculation;

    protected Config $taxConfig;

    protected BuckarooFee $configProviderBuckarooFee;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param BuckarooLog $buckarooLog
     * @param QuoteFactory $quoteFactory
     * @param Calculation $taxCalculation
     * @param Config $taxConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        BuckarooLog          $buckarooLog,
        QuoteFactory         $quoteFactory,
        Calculation          $taxCalculation,
        Config               $taxConfig,
        BuckarooFee          $configProviderBuckarooFee
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->buckarooLog = $buckarooLog;
        $this->quoteFactory = $quoteFactory;
        $this->taxCalculation = $taxCalculation;
        $this->taxConfig = $taxConfig;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
    }

    public function build(array $buildSubject)
    {
        $payment = $buildSubject->getPayment();
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        if ($this->payRemainder) {
            return $this->getRequestArticlesDataPayRemainder($payment);
        }

        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

        // Set loop variables
        $articles = [];
        $count = 1;

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($cartData as $item) {

            if (empty($item)
                || $item->hasParentItemId()
                || $item->getRowTotalInclTax() == 0
            ) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getTaxPercent() ?? 0
            );

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreEnd

            if ($count < self::KLARNA_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $serviceLine = $this->getServiceCostLine($count, $payment->getOrder());

        if (!empty($serviceLine)) {
            $articles = array_merge($articles, $serviceLine);
            $count++;
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder(), $count);

        if (!empty($shippingCosts)) {
            $articles = array_merge($articles, $shippingCosts);
            $count++;
        }

        $discountline = $this->getDiscountLine($count, $payment);

        if (!empty($discountline)) {
            $articles = array_merge($articles, $discountline);
        }

        return $articles;
    }

    protected function getRequestArticlesDataPayRemainder($payment)
    {
        return $this->getArticleArrayLine(
            1,
            'PayRemainder',
            1,
            1,
            round($this->payRemainder, 2),
            $this->getTaxCategory($payment->getOrder())
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
        $latestKey,
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    )
    {
        $article = [
            [
                '_' => $articleDescription,
                'Name' => 'Description',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_' => $articleId,
                'Name' => 'Identifier',
                'Group' => 'Article',
                'GroupID' => $latestKey,
            ],
            [
                '_' => $articleQuantity,
                'Name' => 'Quantity',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_' => $articleUnitPrice,
                'Name' => 'GrossUnitPrice',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_' => $articleVat,
                'Name' => 'VatPercentage',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ]
        ];

        return $article;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $productItem
     * @param                                 $includesTax
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

    public function getServiceCostLine($latestKey, $order, &$itemsTotalAmount = 0)
    {
        $buckarooFeeLine = $order->getBuckarooFeeInclTax();

        if (!$buckarooFeeLine && ($order->getBuckarooFee() >= 0.01)) {
            $this->buckarooLog->addDebug(__METHOD__ . '|5|');
            $buckarooFeeLine = $order->getBuckarooFee();
        }

        $article = [];

        if (false !== $buckarooFeeLine && (double)$buckarooFeeLine > 0) {
            $article = $this->getArticleArrayLine(
                $latestKey,
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
            [
                '_' => 'Shipping fee',
                'Name' => 'Description',
                'Group' => 'Article',
                'GroupID' => $count,
            ],
            [
                '_' => $this->formatPrice($shippingAmount),
                'Name' => $this->getPriceFieldName(),
                'Group' => 'Article',
                'GroupID' => $count,
            ],
            [
                '_' => $this->formatShippingCostsLineVatPercentage($percent),
                'Name' => 'VatPercentage',
                'Group' => 'Article',
                'GroupID' => $count,
            ],
            [
                '_' => '1',
                'Name' => 'Quantity',
                'Group' => 'Article',
                'GroupID' => $count,
            ],
            [
                '_' => '1',
                'Name' => 'Identifier',
                'Group' => 'Article',
                'GroupID' => $count,
            ],
        ];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }

    /**
     * Get the discount cost lines
     *
     * @param int $latestKey
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getDiscountLine($latestKey, $payment)
    {
        $article = [];
        $discount = $this->getDiscountAmount($payment);

        if ($discount >= 0) {
            return $article;
        }

        $article = $this->getArticleArrayLine(
            $latestKey,
            'Korting',
            1,
            1,
            round($discount, 2),
            0
        );

        return $article;
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

    protected function getPriceFieldName()
    {
        return 'GrossUnitPrice';
    }

    protected function formatShippingCostsLineVatPercentage($percent)
    {
        return $percent;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return float|int
     */
    protected function getDiscountAmount($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $discount = 0;
        $edition = $this->softwareData->getProductMetaData()->getEdition();

        if ($order->getDiscountAmount() < 0) {
            $discount -= abs((double)$order->getDiscountAmount());
        }

        if ($edition == 'Enterprise' && $order->getCustomerBalanceAmount() > 0) {
            $discount -= abs((double)$order->getCustomerBalanceAmount());
        }

        return $discount;
    }
}
