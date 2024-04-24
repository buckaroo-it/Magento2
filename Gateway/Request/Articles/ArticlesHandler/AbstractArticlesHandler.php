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

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Api\ArticleHandlerInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractArticlesHandler implements ArticleHandlerInterface
{
    public const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    public const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';
    /**
     * Max articles that can be handled by payment method
     */
    public const MAX_ARTICLE_COUNT = 99;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var BuckarooLog
     */
    protected BuckarooLog $buckarooLog;

    /**
     * @var int
     */
    protected int $payRemainder = 0;

    /**
     * @var QuoteFactory
     */
    protected QuoteFactory $quoteFactory;

    /**
     * @var Calculation
     */
    protected Calculation $taxCalculation;

    /**
     * @var Config
     */
    protected Config $taxConfig;

    /**
     * @var BuckarooFee
     */
    protected BuckarooFee $configProviderBuckarooFee;

    /**
     * @var ConfigProviderMethodFactory
     */
    protected ConfigProviderMethodFactory $configProviderMethodFactory;

    /**
     * @var SoftwareData
     */
    protected SoftwareData $softwareData;

    /**
     * @var Order
     */
    protected Order $order;

    /**
     * @var Quote|null
     */
    protected ?Quote $quote = null;

    /**
     * @var InfoInterface
     */
    protected InfoInterface $payment;

    /**
     * @var PayReminderService
     */
    protected PayReminderService $payReminderService;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param BuckarooLog $buckarooLog
     * @param QuoteFactory $quoteFactory
     * @param Calculation $taxCalculation
     * @param Config $taxConfig
     * @param BuckarooFee $configProviderBuckarooFee
     * @param SoftwareData $softwareData
     * @param ConfigProviderMethodFactory $configProviderMethodFactory
     * @param PayReminderService $payReminderService
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        BuckarooLog $buckarooLog,
        QuoteFactory $quoteFactory,
        Calculation $taxCalculation,
        Config $taxConfig,
        BuckarooFee $configProviderBuckarooFee,
        SoftwareData $softwareData,
        ConfigProviderMethodFactory $configProviderMethodFactory,
        PayReminderService $payReminderService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->buckarooLog = $buckarooLog;
        $this->quoteFactory = $quoteFactory;
        $this->taxCalculation = $taxCalculation;
        $this->taxConfig = $taxConfig;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->softwareData = $softwareData;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritdoc
     */
    public function getOrderArticlesData(Order $order, InfoInterface $payment): array
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $this->setPayment($payment);
        $this->setOrder($order);

        if ($this->payReminderService->isPayRemainder($order)) {
            return ['articles' => [0 => $this->getRequestArticlesDataPayRemainder()]];
        }

        $articles['articles'] = $this->getItemsLines();

        $serviceLine = $this->getServiceCostLine($this->getOrder());
        if (!empty($serviceLine)) {
            $articles = array_merge_recursive($articles, $serviceLine);
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($this->getOrder());
        if (!empty($shippingCosts)) {
            $articles = array_merge_recursive($articles, $shippingCosts);
        }

        $discountline = $this->getDiscountLine();
        if (!empty($discountline)) {
            $articles['articles'][] = $discountline;
        }

        $additionalLines = $this->getAdditionalLines();
        if (!empty($additionalLines)) {
            $articles = array_merge_recursive($articles, $additionalLines);
        }

        return $articles;
    }

    /**
     * Get Pay Remainder article
     *
     * @return array
     */
    protected function getRequestArticlesDataPayRemainder(): array
    {
        return $this->getArticleArrayLine(
            'PayRemainder',
            1,
            1,
            round($this->payReminderService->getPayRemainder($this->getOrder()), 2),
            $this->getTaxCategory($this->getOrder())
        );
    }

    /**
     * Mapping item article
     *
     * @param string|null $articleDescription
     * @param int|string|null $articleId
     * @param int|float $articleQuantity
     * @param string|float $articleUnitPrice
     * @param string|float $articleVat
     * @return array
     */
    public function getArticleArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        return [
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    /**
     * Get order
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Set order
     *
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order): AbstractArticlesHandler
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get Quote
     *
     * @return Quote
     */
    public function getQuote(): Quote
    {
        if (!$this->quote instanceof Quote) {
            $this->quote = $this->quoteFactory->create()->load($this->getOrder()->getQuoteId());
        }

        return $this->quote;
    }

    /**
     * Set Quote
     *
     * @param Quote $quote
     * @return $this
     */
    public function setQuote(Quote $quote): AbstractArticlesHandler
    {
        $this->quote = $quote;
        return $this;
    }

    /**
     * Get tax category
     *
     * @param Order|Invoice $order
     * @return float|int
     */
    protected function getTaxCategory($order)
    {
        $request = $this->taxCalculation->getRateRequest(
            null,
            null,
            null,
            $order->getStore()
        );
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($order->getStore());
        return $this->taxCalculation->getRate($request->setProductClassId($taxClassId));
    }

    /**
     * Get items lines
     *
     * @return array
     */
    protected function getItemsLines(): array
    {
        $articles = [];
        $count = 1;
        $bundleProductQty = 0;

        $quote = $this->getQuote();
        $cartData = $quote->getAllItems();

        /**
         * @var Item $item
         */
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
     * Skip item if item has parent or total equal 0
     *
     * @param Item|Invoice\Item|Creditmemo\Item $item
     * @return bool
     */
    protected function skipItem($item): bool
    {
        if ($item->getRowTotalInclTax() == 0
            || $item->hasParentItemId()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Skip bundles which have dynamic pricing on (0 = yes,1 = no) - the underlying simples are also in the quote
     *
     * @param Item $item
     * @param int $bundleProductQty
     * @return bool
     */
    protected function skipBundleProducts(Item $item, int &$bundleProductQty): bool
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
     * Get identifier, can be sku or product id
     *
     * @param Item|Invoice\Item|Creditmemo\Item $item
     * @return mixed|string|null
     */
    protected function getIdentifier($item)
    {
        return $item->getSku();
    }

    /**
     * Calculate product price
     *
     * @param Item|Invoice\Item|Creditmemo\Item $productItem
     * @return float
     */
    public function calculateProductPrice($productItem): float
    {
        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $productPrice = $productItem->getPriceInclTax();

        if (!$includesTax
            && $productItem->getDiscountAmount() >= 0.01) {
            $productPrice = $productItem->getPrice()
                + $productItem->getTaxAmount() / $productItem->getQty();
        }

        if ($productItem->getWeeeTaxAppliedAmount() > 0) {
            $productPrice += $productItem->getWeeeTaxAppliedAmount();
        }

        return (float)$productPrice;
    }

    /**
     * Get discount amount
     *
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

    /**
     * Get item tax category or percentage
     *
     * @param Item|Order\Item $item
     * @return float
     */
    protected function getItemTax($item): float
    {
        return (float)$item->getTaxPercent() ?? 0;
    }

    /**
     * Get payment fee line
     *
     * @param Order|Invoice|Creditmemo $order
     * @param float $itemsTotalAmount
     * @return array|array[]
     */
    public function getServiceCostLine($order, &$itemsTotalAmount = 0, bool $creditmemo = false): array
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
            if ($creditmemo) {
                $article['refundType'] = 'Refund';
            }
            $itemsTotalAmount += round($buckarooFeeLine, 2);
        }

        return !empty($article) ? ['articles' => [$article]] : [];
    }

    /**
     * Get shipping cost line
     *
     * @param Order|Invoice|Creditmemo $order
     * @param int $itemsTotalAmount
     * @param bool $creditmemo
     * @return array
     */
    protected function getShippingCostsLine($order, &$itemsTotalAmount = 0, bool $creditmemo = false): array
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

        if ($creditmemo) {
            $shippingCostsArticle['refundType'] = 'Refund';
        }

        $itemsTotalAmount += $shippingAmount;

        return !empty($shippingCostsArticle) ? ['articles' => [$shippingCostsArticle]] : [];
    }

    /**
     * Get shipping amount include taxes
     *
     * @param Order|Invoice|Creditmemo $order
     * @return float|null
     */
    protected function getShippingAmount($order): ?float
    {
        return (float)$order->getShippingInclTax();
    }

    /**
     * Format price
     *
     * @param float|null $price
     * @return float|null
     */
    protected function formatPrice(?float $price): ?float
    {
        return $price;
    }

    /**
     * Format shipping cost line
     *
     * @param float $percent
     * @return float
     */
    protected function formatShippingCostsLineVatPercentage(float $percent): float
    {
        return $percent;
    }

    /**
     * Get the discount cost lines
     *
     * @return array
     */
    public function getDiscountLine(): array
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
     * @inheritdoc
     */
    public function getInvoiceArticlesData(Order $order, InfoInterface $payment): array
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

        $articles['articles'] = $this->getInvoiceItemsLines($currentInvoice);

        if (is_array($articles) && $numberOfInvoices == 1) {
            $serviceLine = $this->getServiceCostLine($currentInvoice);
            if (!empty($serviceLine)) {
                $articles = array_merge_recursive($articles, $serviceLine);
            }
        }

        $shippingCosts = $this->getShippingCostsLine($currentInvoice);
        if (!empty($shippingCosts)) {
            $articles = array_merge_recursive($articles, $shippingCosts);
        }

        return $articles;
    }

    /**
     * Get items from invoice
     *
     * @param Invoice $invoice
     * @return array
     */
    protected function getInvoiceItemsLines(Invoice $invoice): array
    {
        $articles = [];
        $count = 1;

        /** @var Invoice\Item $item */
        foreach ($invoice->getAllItems() as $item) {
            if ($this->skipItem($item)) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $item->getQty(),
                $this->calculateProductPrice($item),
                $this->getItemTax($item->getOrderItem())
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

    /**
     * Get invoice discount description
     *
     * @param Invoice\Item $item
     * @return string
     */
    protected function getDiscountDescription($item): string
    {
        return 'Korting op ' . $item->getName();
    }

    /**
     * Get Items Data from Creditmemo (refund)
     *
     * @param Order $order
     * @param InfoInterface $payment
     * @return array|array[]
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getCreditMemoArticlesData(Order $order, InfoInterface $payment): array
    {
        if ($this->payReminderService->isPayRemainder($order)) {
            return ['articles' => [0 => $this->getCreditmemoArticleDataPayRemainder()]];
        }

        $this->setPayment($payment);
        $this->setOrder($order);

        /** @var Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();

        $articles = [];
        $count = 1;
        $itemsTotalAmount = 0;

        /**
         * @var Creditmemo\Item $item
         */
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
                $this->getItemTax($item->getOrderItem())
            );

            $itemsTotalAmount += $item->getQty() * $prodPriceWithoutDiscount;

            $articles['articles'][] = $article;

            if ($count < self::MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        if (!empty($articles) && !$payment->getOrder()->hasCreditmemos()) {
            $serviceLine = $this->getServiceCostLine($creditmemo, $itemsTotalAmount, true);
            if (!empty($serviceLine)) {
                $articles = array_merge_recursive($articles, $serviceLine);
            }
        }

        $shippingCosts = $this->getShippingCostsLine($creditmemo, $itemsTotalAmount, true);
        if (!empty($shippingCosts)) {
            $articles = array_merge_recursive($articles, $shippingCosts);
        }

        if (abs($creditmemo->getGrandTotal() - $itemsTotalAmount) > 0.01) {
            $diff = $creditmemo->getGrandTotal() - $itemsTotalAmount;
            $diffLine = $this->getDiffLine($diff, true);
            $articles = array_merge_recursive($articles, $diffLine);
        }

        return $articles;
    }

    /**
     * Get pay remainder article for credit memo
     *
     * @return array
     */
    protected function getCreditmemoArticleDataPayRemainder(): array
    {
        $payRemainderAmount = round($this->payReminderService->getPayRemainder($this->getOrder()), 2);
        return $this->getArticleRefundArrayLine(
            'PayRemainder',
            1,
            1,
            $payRemainderAmount,
            $this->getTaxCategory($this->getOrder())
        );
    }

    /**
     * Get the structure of the array returned to request for refunded items
     *
     * @param string|null $articleDescription
     * @param int|string|null $articleId
     * @param int|float $articleQuantity
     * @param string|float $articleUnitPrice
     * @param string|float $articleVat
     * @return array
     */
    public function getArticleRefundArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        return [
            'refundType' => 'Refund',
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    /**
     * Get the difference between total and items total
     *
     * @param float $diff
     * @param bool $creditmemo
     * @return array[]
     */
    protected function getDiffLine(float $diff, bool $creditmemo = false): array
    {
        $article = $this->getArticleArrayLine(
            'Discount/Fee',
            4,
            1,
            round($diff, 2),
            4
        );

        if ($creditmemo) {
            $article['refundType'] = 'Refund';
        }

        return ['articles' => [$article]];
    }

    /**
     * Get payment
     *
     * @return InfoInterface
     */
    public function getPayment(): InfoInterface
    {
        return $this->payment;
    }

    /**
     * Set payment
     *
     * @param $payment
     * @return $this
     */
    public function setPayment($payment): AbstractArticlesHandler
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * Get Additional Lines for specific methods
     *
     * @return array
     */
    protected function getAdditionalLines(): array
    {
        return [];
    }
}
