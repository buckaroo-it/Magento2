<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Api\ArticleHandlerInterface;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

abstract class AbstractArticlesHandler implements ArticleHandlerInterface
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

    protected Order $order;
    protected InfoInterface $payment;
    protected float $itemsTotalAmount = 0;

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


    public function getOrderArticlesData($order, $payment): array
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $this->setPayment($payment);
        $this->setOrder($order);

        if ($this->payRemainder) {
            return $this->getRequestArticlesDataPayRemainder();
        }

        $articles = $this->getItemsLines();

        $serviceLine = $this->getServiceCostLine($this->getOrder());

        if (!empty($serviceLine)) {
            $articles[] = $serviceLine;
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($this->getOrder());

        if (!empty($shippingCosts)) {
            $articles[] = $shippingCosts;
        }

        $discountline = $this->getDiscountLine();

        if (!empty($discountline)) {
            $articles[] = $discountline;
        }

        return $articles;
    }

    public function getInvoiceArticlesData($order, $payment): array
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $this->setPayment($payment);
        $this->setOrder($order);

        $invoiceCollection = $this->getOrder()->getInvoiceCollection();
        $numberOfInvoices = $invoiceCollection->count();

        /**
         * @var Invoice $currentInvoice
         */
        $currentInvoice = $invoiceCollection->getLastItem();

        $articles = $this->getInvoiceItemsLines($currentInvoice);

        if (is_array($articles) && $numberOfInvoices == 1) {
            $serviceLine = $this->getServiceCostLine($currentInvoice);
            if (!empty($serviceLine)) $articles[] = $serviceLine;
        }

        $shippingCosts = $this->getShippingCostsLine($currentInvoice);
        if (!empty($shippingCosts)) $articles[] = $shippingCosts;

        if (!empty($shippingCosts)) {
            $articles[] = $shippingCosts;
        }

        return $articles;
    }

    public function getCreditMemoArticlesData(Order $order, \Magento\Payment\Model\InfoInterface $payment): array
    {
        if ($this->payRemainder) {
            return $this->getCreditmemoArticleDataPayRemainder($payment);
        }

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();

        $articles = [];
        $count = 1;
        $itemsTotalAmount = 0;

        /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
        foreach ($creditmemo->getAllItems() as $item) {
            if ($this->skipItem($item)) {
                continue;
            }

            $prodPrice = $this->calculateProductPrice($item);
            $prodPriceWithoutDiscount = round($prodPrice - $item->getDiscountAmount() / $item->getQty(), 2);
            $article = $this->getArticleRefundArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $item->getQty(),
                $prodPriceWithoutDiscount,
                $item->getOrderItem()->getTaxPercent()
            );

            $itemsTotalAmount += $item->getQty() * $prodPriceWithoutDiscount;

            $articles[] = $article;

            if ($count < self::MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        if (count($articles) > 0 && !$payment->getOrder()->hasCreditmemos()) {
            $serviceLine = $this->getServiceCostLine($creditmemo, $itemsTotalAmount);
            if (!empty($serviceLine)) {
                $articles[] = $serviceLine;
            }
        }

        $shippingCosts = $this->getShippingCostsLine($creditmemo, $itemsTotalAmount);
        if (!empty($shippingCosts)) {
            $articles[] = $shippingCosts;
        }

        if (abs($creditmemo->getGrandTotal() - $itemsTotalAmount) > 0.01) {
            $diff = $creditmemo->getGrandTotal() - $itemsTotalAmount;
            $diffLine = $this->getDiffLine($diff);
            $articles[] = $diffLine;
        }

        return $articles;
    }

    protected function getItemsLines()
    {
        $articles = [];
        $count = 1;
        $bundleProductQty = 0;

        $quote = $this->quoteFactory->create()->load($this->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($cartData as $item) {

            if ($this->skipItem($item)) {
                continue;
            }

            if ($this->skipBundleProducts($item, $bundleProductQty)) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $bundleProductQty ?: $item->getQty(),
                $this->calculateProductPrice($item),
                $this->getItemTax($item)
            );

            $articles[] = $article;

            if ($count >= self::MAX_ARTICLE_COUNT) {
                break;
            }

            $count++;

        }

        return $articles;
    }

    /**
     * @param Invoice $invoice
     * @return array
     */
    protected function getInvoiceItemsLines(Invoice $invoice): array
    {
        $articles = [];
        $count = 1;

        /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
        foreach ($invoice->getAllItems() as $item) {

            if ($this->skipItem($item)) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $item->getQty(),
                $this->calculateProductPrice($item),
                $item->getOrderItem()->getTaxPercent()
            );

            $articles[] = $article;

            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    $this->getDiscountDescription($item),
                    $item->getSku(),
                    1,
                    number_format(($item->getDiscountAmount() * -1), 2),
                    0
                );
                $articles[] = $article;
            }

            if ($count >= self::MAX_ARTICLE_COUNT) {
                break;
            }

            $count++;

        }

        return $articles;
    }

    protected function skipItem($item): bool
    {
        if (empty($item)
            || $item->getRowTotalInclTax() == 0
            || $item->hasParentItemId()) {
            return true;
        }

        return false;
    }

    //Skip bundles which have dynamic pricing on (0 = yes,1 = no) - the underlying simples are also in the quote
    protected function skipBundleProducts($item, &$bundleProductQty): bool
    {
        if ($item->getProductType() == Type::TYPE_BUNDLE
            && $item->getProduct()->getCustomAttribute('price_type')
            && $item->getProduct()->getCustomAttribute('price_type')->getValue() == 0
        ) {
            $bundleProductQty = $item->getQty();
            return true;
        }

        if (!$item->getParentItemId()) {
            $bundleProductQty = 0;
        }

        return false;
    }

    /**
     * @param Item $item
     * @return float
     */
    protected function getItemTax(Item $item): float
    {
        return (float)$item->getTaxPercent() ?? 0;
    }

    /**
     * @param Item|\Magento\Sales\Model\Order\Invoice\Item|\Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return mixed|string|null
     */
    protected function getIdentifier(Item $item)
    {
        return $item->getSku();
    }

    /**
     * @param Item|\Magento\Sales\Model\Order\Invoice\Item|\Magento\Sales\Model\Order\Creditmemo\Item $productItem
     * @return mixed
     */
    public function calculateProductPrice($productItem)
    {
        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

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

    /**
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
     * @return array
     */
    public function getArticleRefundArrayLine(
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array
    {
        return [
            'refundType' => 'Refund',
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
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

    protected function getDiscountDescription($item): string
    {
        return 'Korting op ' . $item->getName();
    }


    protected function getTaxCategory($order)
    {
        $request = $this->taxCalculation->getRateRequest(null, null, null, $order->getStore());
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($order->getStore());
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));
        return $percent;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @return array
     */
    protected function getShippingCostsLine($order, &$itemsTotalAmount = 0)
    {
        $shippingCostsArticle = [];

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $request = $this->taxCalculation->getRateRequest();
        $taxClassId = $this->taxConfig->getShippingTaxClass();
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

        $shippingCostsArticle = $this->getArticleArrayLine(
            'Shipping fee',
            2,
            1,
            $this->formatPrice($shippingAmount),
            $this->formatShippingCostsLineVatPercentage($percent)
        );

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

    protected function getCreditmemoArticleDataPayRemainder($payment, $addRefundType = true)
    {
        $article = $this->getArticleArrayLine(
            'PayRemainder',
            1,
            1,
            round($this->payRemainder, 2),
            $this->getTaxCategory($payment->getOrder())
        );
        if ($addRefundType) {
            $article[] = [
                '_' => 'Refund',
                'Name' => 'RefundType',
                'GroupID' => 1,
                'Group' => 'Article',
            ];
        }
        return $article;
    }

    protected function getDiffLine($diff)
    {
        $article = $this->getArticleArrayLine(
            'Discount/Fee',
            4,
            1,
            round($diff, 2),
            4
        );

        return $article;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return InfoInterface
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param $payment
     * @return $this
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;

        return $this;
    }
}