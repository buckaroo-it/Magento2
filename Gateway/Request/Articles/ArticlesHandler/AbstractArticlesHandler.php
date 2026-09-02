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

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\App\Area;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyFields)
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
     * Largest gap between the article lines and the amount that is still treated as rounding.
     */
    public const ROUNDING_RESIDUAL_TOLERANCE = 0.05;

    /**
     * ArticleNumber for a discount line. Must differ from the service cost line (article 1):
     * a capture nominates reserved lines by number, so duplicates are ambiguous.
     */
    public const DISCOUNT_IDENTIFIER = 'discount';

    /**
     * ArticleNumber for the line that carries a rounding residual.
     */
    public const ADJUSTMENT_IDENTIFIER = 'adjustment';

    /**
     * A bundle priced from its children (catalog price_type "Dynamic", stored on the order item as
     * product_calculations). The children carry the money, the parent only holds their sum.
     */
    public const BUNDLE_CALCULATE_CHILD = 0;

    /**
     * The invoice whose own share of the order-level credits is being priced, or null when the
     * lines are built for the whole order.
     *
     * @var Invoice|null
     */
    private ?Invoice $creditAllocationInvoice = null;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var BuckarooLog
     */
    protected $buckarooLog;

    /**
     * @var int
     */
    protected $payRemainder = 0;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var Calculation
     */
    protected $taxCalculation;

    /**
     * @var Config
     */
    protected $taxConfig;

    /**
     * @var BuckarooFee
     */
    protected $configProviderBuckarooFee;

    /**
     * @var ConfigProviderMethodFactory
     */
    protected $configProviderMethodFactory;

    /**
     * @var SoftwareData
     */
    protected $softwareData;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var Quote|null
     */
    protected $quote = null;

    /**
     * @var InfoInterface
     */
    protected $payment;

    /**
     * @var PayReminderService
     */
    protected $payReminderService;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote
     */
    protected $quoteResource;

    /**
     * @var Emulation|null
     */
    private $appEmulation;

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
     * @param \Magento\Quote\Model\ResourceModel\Quote|null $quoteResource
     * @param Emulation|null $appEmulation
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        PayReminderService $payReminderService,
        ?\Magento\Quote\Model\ResourceModel\Quote $quoteResource = null,
        ?Emulation $appEmulation = null
    ) {
        $this->quoteResource = $quoteResource
            ?? \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Quote\Model\ResourceModel\Quote::class);
        $this->appEmulation = $appEmulation;
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

        foreach ($this->getDiscountLines() as $discountLine) {
            $articles['articles'][] = $discountLine;
        }

        $additionalLines = $this->getAdditionalLines();
        if (!empty($additionalLines)) {
            $articles = array_merge_recursive($articles, $additionalLines);
        }

        $articles = $this->absorbRoundingResidual($articles, (float)$order->getGrandTotal());

        $articles = $this->reconcileArticlesWithGrandTotal($articles, (float)$order->getGrandTotal());

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
     * @param string|null     $articleDescription
     * @param int|string|null $articleId
     * @param int|float       $articleQuantity
     * @param string|float    $articleUnitPrice
     * @param string|float    $articleVat
     *
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
            'identifier' => (string)$articleId,
            'description' => $articleDescription,
            'vatPercentage' => $this->normalizeAmount($articleVat),
            'quantity' => (int)round((float)$articleQuantity),
            'price' => round($this->normalizeAmount($articleUnitPrice), 2)
        ];
    }

    /**
     * Normalize an amount to a float for the SDK's strictly-typed Article model
     * (float $price, float $vatPercentage). Numeric strings such as "20.0000" or
     * thousands-formatted amounts like "1,234.50" would otherwise cause a
     * TypeError when assigned to the typed SDK properties.
     *
     * @param string|int|float|null $amount
     */
    protected function normalizeAmount($amount): float
    {
        if ($amount === null || $amount === '' || is_int($amount) || is_float($amount)) {
            return (float)$amount;
        }

        $normalized = trim((string)$amount);

        // number_format-style thousands separators ("1,234.50") — strictly matched
        // so locale-ambiguous values (e.g. "1.234,56") are never silently misread.
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        }

        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(
                sprintf('Article amount "%s" is not a valid number.', $amount)
            );
        }

        return (float)$normalized;
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
     *
     * @return $this
     */
    public function setOrder(Order $order): AbstractArticlesHandler
    {
        $this->order = $order;
        // ArticlesHandlerFactory hands out a SHARED instance and getQuote() caches, so without
        // this a second order in the same process reuses the previous order's cart.
        $this->quote = null;
        $this->creditAllocationInvoice = null;

        return $this;
    }

    /**
     * Get Quote
     *
     * @throws LocalizedException
     *
     * @return Quote
     */
    public function getQuote(): Quote
    {
        if (!$this->quote instanceof Quote) {
            $quote = $this->quoteFactory->create();
            $this->quoteResource->load($quote, $this->getOrder()->getQuoteId());
            $this->quote = $quote;
        }

        return $this->quote;
    }

    /**
     * Set Quote
     *
     * @param Quote $quote
     *
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
     *
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
        $taxClassId = $this->configProviderBuckarooFee->getBuckarooFeeTaxClass($order->getStore());
        return $this->taxCalculation->getRate($request->setProductClassId($taxClassId));
    }

    /**
     * Get items lines
     *
     * @throws LocalizedException
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
            if ($this->skipBundleProducts($item, $bundleProductQty)) {
                continue;
            }

            if ($this->skipItem($item, $bundleProductQty)) {
                continue;
            }

            $itemQty = (float)$item->getTotalQty();

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $itemQty,
                $this->getDiscountedProductPrice($item, $this->getUnitDiscount($item, $itemQty)),
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
     * @param int                               $bundleProductQty If > 0, we're processing bundle children so don't skip items with parents
     *
     * @return bool
     */
    protected function skipItem($item, int $bundleProductQty = 0): bool
    {
        if ($item->getRowTotalInclTax() == 0) {
            return true;
        }

        // If we're processing bundle product children (bundleProductQty > 0),
        // don't skip items with parents - we want to send them individually
        if ($item->hasParentItemId() && $bundleProductQty == 0) {
            return true;
        }

        return false;
    }

    /**
     * Skip bundles which have dynamic pricing on (0 = yes,1 = no) - the underlying simples are also in the quote
     *
     * @param Item $item
     * @param int  $bundleProductQty
     *
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
     *
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
     *
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
            // A quote item answers getTotalQty(), an invoice or creditmemo item getQty(), and an
            // order item only getQtyOrdered() - and the order item is what the capture prices
            // are read from, so all three have to be tried.
            $totalQty = (float)$productItem->getTotalQty()
                ?: (float)$productItem->getQty()
                ?: (float)$productItem->getQtyOrdered()
                ?: 1.0;
            $productPrice = $productItem->getPrice()
                + $productItem->getTaxAmount() / $totalQty;
        }

        if ($productItem->getWeeeTaxAppliedAmount() > 0) {
            $productPrice += $productItem->getWeeeTaxAppliedAmount();
        }

        return (float)$productPrice;
    }

    /**
     * Unit price of an item with its own share of the cart discount already netted in.
     *
     * A partial capture is validated against the RESERVED prices, so a discount may not sit on
     * a separate lump line - that line cannot be captured in part.
     *
     * @param Item|Order\Item|Invoice\Item|Creditmemo\Item $item
     * @param float                                        $unitDiscount
     *
     * @return float
     */
    protected function getDiscountedProductPrice($item, float $unitDiscount): float
    {
        return round($this->calculateProductPrice($item) - $unitDiscount, 2);
    }

    /**
     * Discount written onto a single unit of an item.
     *
     * Taken from the order item on the capture side too, so the price matches the one the
     * reserve sent for that unit.
     *
     * @param Item|Order\Item|Invoice\Item $item
     * @param float                        $qty
     *
     * @return float
     */
    protected function getUnitDiscount($item, float $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        $discount = abs((float)$item->getDiscountAmount());

        if ($discount < 0.01) {
            return 0.0;
        }

        $includesTax = (bool)$this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );
        if (!$includesTax) {
            $discount += abs((float)($item->getDiscountTaxCompensationAmount() ?? 0));
        }

        return $discount / $qty;
    }

    /**
     * Get text for Discount
     *
     * @return Phrase
     */
    public function getDiscount() : Phrase
    {
        return __('Discount');
    }

    /**
     * Get text for Shipping fee
     *
     * @return Phrase
     */
    public function getShippingFee() : Phrase
    {
        return __('Shipping fee');
    }

    /**
     * Get text for Service costs
     *
     * @return Phrase
     */
    public function getServiceCosts() : Phrase
    {
        return __('Service Costs');
    }

    /**
     * Get text for Discount on
     *
     * @return Phrase
     */
    public function getDiscountOn() :Phrase
    {
        return __('Discount on');
    }

    /**
     * Get discount amount
     *
     * @return float|int
     */
    protected function getDiscountAmount()
    {
        $discount = 0.0;

        // The allocated discount rides on the item lines and the shipping discount on the
        // shipping line; only what no line could absorb is left for a global line.
        $unallocated = $this->getUnallocatedOrderDiscount();
        if ($unallocated >= 0.01) {
            $discount -= $unallocated;
        }

        $storeCredit = $this->getStoreCreditAmount();
        if ($storeCredit >= 0.01) {
            $discount -= $storeCredit;
        }

        return $discount;
    }

    /**
     * Store credit for the lines being priced, as a positive amount.
     *
     * @return float
     */
    protected function getStoreCreditAmount(): float
    {
        if ($this->softwareData->getProductMetaData()->getEdition() !== 'Enterprise') {
            return 0.0;
        }

        return max(0.0, $this->getAllocatedCreditAmount('customer_balance_amount'));
    }

    /**
     * One order-level credit, read from the invoice being priced when there is one.
     *
     * @param string $field
     *
     * @return float
     */
    private function getAllocatedCreditAmount(string $field): float
    {
        $source = $this->creditAllocationInvoice ?? $this->getOrder();

        return (float)$source->getData($field);
    }

    /**
     * The store credit lines for an invoice, without the cart discount.
     *
     * The part of the cart discount that no line could absorb is one indivisible reserved line and
     * stays on the first invoice; store credit follows Magento's per-invoice allocation, so every
     * invoice that absorbs some of it carries its own share.
     *
     * @return array
     */
    protected function getStoreCreditLines(): array
    {
        $storeCredit = $this->getStoreCreditAmount();

        if ($storeCredit < 0.01) {
            return [];
        }

        return $this->buildDiscountLines(-$storeCredit, $this->getOrderVatGroups());
    }

    /**
     * Get an effective VAT rate as a weighted average of order item tax rates.
     *
     * @return float
     */
    protected function getOrderEffectiveVatRate(): float
    {
        $totalExclTax = 0.0;
        $weightedTax = 0.0;

        foreach ($this->getPricedOrderItems() as $item) {
            $rowTotal = (float)$item->getRowTotal();
            if ($rowTotal <= 0) {
                continue;
            }
            $totalExclTax += $rowTotal;
            $weightedTax += $rowTotal * (float)$item->getTaxPercent();
        }

        if ($totalExclTax <= 0) {
            return 0.0;
        }

        return round($weightedTax / $totalExclTax, 2);
    }

    /**
     * Get item tax category or percentage
     *
     * @param Item|Order\Item $item
     *
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
     * @param float                    $itemsTotalAmount
     * @param bool                     $creditmemo
     *
     * @return array|array[]
     */
    public function getServiceCostLine($order, &$itemsTotalAmount = 0, bool $creditmemo = false): array
    {
        $buckarooFeeLine = (float)$order->getBuckarooFeeInclTax();

        if (!$buckarooFeeLine && ($order->getBuckarooFee() >= 0.01)) {
            $this->buckarooLog->addDebug(__METHOD__ . '|5|');
            $buckarooFeeLine = (float)$order->getBuckarooFee();
        }

        $article = [];

        if ($buckarooFeeLine && $buckarooFeeLine > 0) {
            $article = $this->getArticleArrayLine(
                (string)$this->getServiceCosts(),
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
     * @param int                      $itemsTotalAmount
     * @param bool                     $creditmemo
     *
     * @return array
     */
    protected function getShippingCostsLine($order, &$itemsTotalAmount = 0, bool $creditmemo = false): array
    {
        $shippingCostsArticle = [];

        $shippingAmount = $this->getDiscountedShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $request = $this->taxCalculation->getRateRequest(
            $order->getShippingAddress(),
            $order->getBillingAddress(),
            null,
            $order->getStore()
        );
        $taxClassId = $this->taxConfig->getShippingTaxClass($order->getStore());
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

        $shippingCostsArticle = $this->getArticleArrayLine(
            $this->renderInOrderStoreLocale(function () {
                return (string)$this->getShippingFee();
            }),
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
     * Render a synthetic article label in the locale of the order's store.
     *
     * @param callable $render
     *
     * @return string
     */
    private function renderInOrderStoreLocale(callable $render): string
    {
        $order = $this->order ?? null;
        if ($order === null || $this->appEmulation === null) {
            return (string)$render();
        }

        $this->appEmulation->startEnvironmentEmulation((int)$order->getStoreId(), Area::AREA_FRONTEND, true);

        try {
            return (string)$render();
        } finally {
            $this->appEmulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Discount written onto the shipping line, as a positive amount.
     *
     * Kept here, not in a global discount line, so a partial capture can repeat the reserved
     * price. Magento settles shipping on the first invoice only.
     *
     * @param Order|Invoice|Creditmemo $order
     *
     * @return float
     */
    protected function getShippingDiscount($order): float
    {
        $discount = abs((float)($order->getShippingDiscountAmount() ?? 0));

        if ($discount < 0.01) {
            return 0.0;
        }

        $includesTax = (bool)$this->scopeConfig->getValue(
            static::TAX_CALCULATION_SHIPPING_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );
        if (!$includesTax) {
            $discount += abs((float)($order->getShippingDiscountTaxCompensationAmount() ?? 0));
        }

        return round($discount, 2);
    }

    /**
     * Gross shipping cost with its discount already taken off.
     *
     * Shipping_incl_tax is the cost BEFORE any discount while shipping_discount_amount is net of
     * tax, so subtracting one from the other loses the tax on the discount. shipping_tax_amount is
     * already computed on the discounted cost, so the net cost plus that tax is the gross paid.
     *
     * The discount always comes from the ORDER: an invoice has no shipping_discount_amount column,
     * so reading it from one silently yields zero.
     *
     * @param Order|Invoice|Creditmemo $entity
     *
     * @return float
     */
    private function getDiscountedShippingAmount($entity): float
    {
        $discount = $this->getShippingDiscount($this->order ?? $entity);

        if ($discount < 0.01) {
            return round((float)$this->getShippingAmount($entity), 2);
        }

        $net = round((float)$entity->getShippingAmount() - $discount, 2);

        if ($net <= 0) {
            return 0.0;
        }

        return round(
            $net
            + (float)$entity->getShippingTaxAmount()
            + (float)$entity->getShippingDiscountTaxCompensationAmount(),
            2
        );
    }

    /**
     * Get shipping amount include taxes
     *
     * @param Order|Invoice|Creditmemo $order
     *
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
     *
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
     *
     * @return float
     */
    protected function formatShippingCostsLineVatPercentage(float $percent): float
    {
        return $percent;
    }

    /**
     * Get discount lines, split proportionally per VAT rate present in the order.
     *
     * @return array
     */
    public function getDiscountLines(): array
    {
        return $this->buildDiscountLines((float)$this->getDiscountAmount(), $this->getOrderVatGroups());
    }

    /**
     * Build the discount article lines for an amount, split proportionally per VAT rate.
     *
     * @param float $discount Negative.
     * @param array $vatGroups
     *
     * @return array
     */
    private function buildDiscountLines(float $discount, array $vatGroups): array
    {
        if ($discount >= 0) {
            return [];
        }

        if (count($vatGroups) <= 1) {
            $vatRate = !empty($vatGroups) ? (float)array_key_first($vatGroups) : $this->getOrderEffectiveVatRate();
            return [$this->getArticleArrayLine(
                (string)$this->getDiscount(),
                self::DISCOUNT_IDENTIFIER,
                1,
                round($discount, 2),
                $vatRate
            )];
        }

        $lines = [];
        $totalRowTotal = array_sum(array_column($vatGroups, 'rowTotal'));
        $allocatedDiscount = 0.0;
        $vatRates = array_keys($vatGroups);
        $lastVatRate = end($vatRates);

        foreach ($vatGroups as $vatRate => $group) {
            if ($vatRate === $lastVatRate) {
                $lineDiscount = round($discount - $allocatedDiscount, 2);
            } else {
                $lineDiscount = round($discount * ($group['rowTotal'] / $totalRowTotal), 2);
                $allocatedDiscount += $lineDiscount;
            }

            if (abs($lineDiscount) < 0.01) {
                continue;
            }

            // One line per VAT rate, each with its own number - a capture nominates lines by
            // ArticleNumber and cannot tell duplicates apart.
            $lines[] = $this->getArticleArrayLine(
                (string)$this->getDiscount(),
                self::DISCOUNT_IDENTIFIER . '-' . $this->normalizeAmount($vatRate),
                1,
                $lineDiscount,
                $vatRate
            );
        }

        return $lines;
    }

    /**
     * Get the discount cost line.
     *
     * @deprecated A single discount line cannot represent discounts spread over multiple VAT rates.
     * @see \Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AbstractArticlesHandler::getDiscountLines()
     * @return array
     */
    public function getDiscountLine(): array
    {
        $lines = $this->getDiscountLines();
        return !empty($lines) ? $lines[0] : [];
    }

    /**
     * Get order items grouped by VAT rate with their combined excl-tax row totals.
     *
     * @return array
     */
    protected function getOrderVatGroups(): array
    {
        $rows = [];
        foreach ($this->getPricedOrderItems() as $item) {
            $rows[] = [(float)$item->getRowTotal(), (float)$item->getTaxPercent()];
        }

        return $this->groupRowTotalsByVatRate($rows);
    }

    /**
     * Combine [rowTotal, vatRate] pairs into per-VAT-rate row totals.
     *
     * @param array $rows
     *
     * @return array
     */
    private function groupRowTotalsByVatRate(array $rows): array
    {
        $groups = [];
        foreach ($rows as [$rowTotal, $vatRate]) {
            if ($rowTotal <= 0) {
                continue;
            }
            if (!isset($groups[$vatRate])) {
                $groups[$vatRate] = ['rowTotal' => 0.0];
            }
            $groups[$vatRate]['rowTotal'] += $rowTotal;
        }

        return $groups;
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

        $isFirstInvoice = $numberOfInvoices == 1;

        // Price the order-level credits against this invoice's own share of them.
        $this->creditAllocationInvoice = $currentInvoice;

        $discountLines = $isFirstInvoice ? $this->getDiscountLines() : $this->getStoreCreditLines();

        $articles['articles'] = $this->getInvoiceItemsLines($currentInvoice);

        if ($isFirstInvoice) {
            $articles = $this->mergeArticleLines($articles, $this->getServiceCostLine($currentInvoice));
        }

        $articles = $this->mergeArticleLines($articles, $this->getShippingCostsLine($currentInvoice));

        foreach ($discountLines as $discountLine) {
            $articles['articles'][] = $discountLine;
        }

        $articles = $this->mergeArticleLines($articles, $this->getAdditionalLines());

        return $this->finaliseCaptureArticles($articles, $currentInvoice);
    }

    /**
     * What the capture for one invoice actually asked the gateway for.
     *
     * A refund is validated against the transaction it targets, and that transaction holds the
     * amount the capture sent at reserved prices. The invoice grand total rounds the discount per
     * invoice instead, so a refund built from it can exceed what is refundable. Rebuilds the same
     * lines the capture sent for that invoice and returns their sum.
     *
     * @param Order         $order
     * @param InfoInterface $payment
     * @param Invoice       $invoice
     *
     * @return float
     */
    public function getCapturedTotalForInvoice(Order $order, InfoInterface $payment, Invoice $invoice): float
    {
        $this->setPayment($payment);
        $this->setOrder($order);

        $isFirstInvoice = $this->isFirstInvoice($order, $invoice);

        // Mirror getInvoiceArticlesData(): the credits are priced per invoice.
        $this->creditAllocationInvoice = $invoice;

        $articles = ['articles' => $this->getInvoiceItemsLines($invoice)];

        if ($isFirstInvoice) {
            $articles = $this->mergeArticleLines($articles, $this->getServiceCostLine($invoice));
        }

        foreach (($isFirstInvoice ? $this->getDiscountLines() : $this->getStoreCreditLines()) as $discountLine) {
            $articles['articles'][] = $discountLine;
        }

        $articles = $this->mergeArticleLines($articles, $this->getAdditionalLines());

        $articles = $this->mergeArticleLines($articles, $this->getShippingCostsLine($invoice));

        if ($this->isClosingInvoice($order, $invoice)) {
            $articles = $this->appendClosingResidual($articles);
        }

        return $this->sumArticleLines($articles);
    }

    /**
     * Ids of every invoice on the order, in creation order.
     *
     * @param Order $order
     *
     * @return int[]
     */
    private function getSortedInvoiceIds(Order $order): array
    {
        $invoiceIds = [];

        foreach ($order->getInvoiceCollection() as $existing) {
            $invoiceIds[] = (int)$existing->getId();
        }

        sort($invoiceIds);

        return $invoiceIds;
    }

    /**
     * Whether this invoice is the order's first, which carries the service cost and the discount.
     *
     * @param Order   $order
     * @param Invoice $invoice
     *
     * @return bool
     */
    private function isFirstInvoice(Order $order, Invoice $invoice): bool
    {
        $invoiceIds = $this->getSortedInvoiceIds($order);

        return !empty($invoiceIds) && (int)$invoice->getId() === reset($invoiceIds);
    }

    /**
     * Whether this invoice closes the order, which is where the reserve's residual is settled.
     *
     * @param Order   $order
     * @param Invoice $invoice
     *
     * @return bool
     */
    private function isClosingInvoice(Order $order, Invoice $invoice): bool
    {
        $invoiceIds = $this->getSortedInvoiceIds($order);

        return !empty($invoiceIds)
            && (int)$invoice->getId() === end($invoiceIds)
            && !$order->canInvoice();
    }

    /**
     * Settle the reserve's leftover rounding on the capture that closes the order.
     *
     * @param array $articles
     *
     * @return array
     */
    private function appendClosingResidual(array $articles): array
    {
        $residual = $this->getReserveRoundingResidual();

        if (abs($residual) < 0.01 || abs($residual) > self::ROUNDING_RESIDUAL_TOLERANCE) {
            return $articles;
        }

        return $this->addAdjustmentLine(
            $articles,
            $residual,
            round($this->sumArticleLines($articles) + $residual, 2)
        );
    }

    /**
     * Append a builder's lines to the article list if it produced any.
     *
     * @param array $articles
     * @param array $lines
     *
     * @return array
     */
    private function mergeArticleLines(array $articles, array $lines): array
    {
        return empty($lines) ? $articles : array_merge_recursive($articles, $lines);
    }

    /**
     * Settle the assembled capture lines against the invoice they were built for.
     *
     * @param array   $articles
     * @param Invoice $invoice
     *
     * @return array
     */
    private function finaliseCaptureArticles(array $articles, Invoice $invoice): array
    {
        $articles = $this->appendReservedAdjustment($articles);

        $this->reportInvoiceTotalMismatch($articles, (float)$invoice->getGrandTotal());

        return $articles;
    }

    /**
     * Nominate the reserve's rounding adjustment, on the capture that closes the order.
     *
     * The adjustment is one indivisible reserved line, like the shipping and global discount
     * lines. Re-pricing it per invoice re-prices a line the reservation has already fixed, and a
     * provider that resums the reservation refuses the capture. Magento rounds a discount per
     * invoice while the reserve rounds per unit, so an intermediate capture cannot match its own
     * invoice total anyway: it goes at reserved prices and the leftover is settled here.
     *
     * @param array $articles
     *
     * @return array
     */
    private function appendReservedAdjustment(array $articles): array
    {
        // An intermediate capture goes at reserved prices and leaves the difference behind.
        if ($this->getOrder()->canInvoice()) {
            return $articles;
        }

        $residual = $this->getReserveRoundingResidual();

        if (abs($residual) < 0.01 || abs($residual) > self::ROUNDING_RESIDUAL_TOLERANCE) {
            return $articles;
        }

        return $this->addAdjustmentLine(
            $articles,
            $residual,
            round($this->sumArticleLines($articles) + $residual, 2)
        );
    }

    /**
     * The rounding residual the reserve carried, recomputed from the order items.
     *
     * Article prices are rounded to the cent, so price * qty cannot always reach the exact line
     * value; the reserve gathered the difference into its adjustment line and the closing capture
     * has to nominate that amount.
     *
     * Derived from the items rather than from grand_total - total_paid: the ledger records each
     * invoice at its own grand total while the gateway holds amounts rounded per unit, so the two
     * drift by a cent per capture and a ledger-derived residual would be wrong.
     *
     * @return float
     */
    private function getReserveRoundingResidual(): float
    {
        $residual = 0.0;

        foreach ($this->getPricedOrderItems() as $item) {
            $qty = (float)$item->getQtyOrdered();

            if ($qty <= 0) {
                continue;
            }

            $unitPrice = $this->calculateProductPrice($item) - $this->getReservedUnitDiscount($item);
            $residual += ($unitPrice - round($unitPrice, 2)) * $qty;
        }

        return round($residual, 2);
    }

    /**
     * Give a rounding residual a line of its own so the articles sum exactly to the amount.
     *
     * Never rewrites the price of a real line: that no longer matches the merchant's records and
     * is invisible to a provider that resolves a capture from the reservation.
     *
     * @param array $articles
     * @param float $targetTotal
     *
     * @return array
     */
    protected function absorbRoundingResidual(array $articles, float $targetTotal): array
    {
        if ($targetTotal <= 0) {
            return $articles;
        }

        $residual = round($targetTotal - $this->sumArticleLines($articles), 2);

        if (abs($residual) < 0.01 || abs($residual) > self::ROUNDING_RESIDUAL_TOLERANCE) {
            return $articles;
        }

        return $this->addAdjustmentLine($articles, $residual, $targetTotal);
    }

    /**
     * Carry a residual on a line of its own.
     *
     * Splitting a unit off an existing line - what this replaces - left two articles with the
     * same ArticleNumber, which a capture cannot tell apart.
     *
     * @param array $articles
     * @param float $residual
     * @param float $targetTotal
     *
     * @return array
     */
    private function addAdjustmentLine(array $articles, float $residual, float $targetTotal): array
    {
        if (count($articles['articles'] ?? []) >= self::MAX_ARTICLE_COUNT) {
            return $articles;
        }

        $articles['articles'][] = $this->getArticleArrayLine(
            (string)$this->getAdjustmentLabel(),
            self::ADJUSTMENT_IDENTIFIER,
            1,
            $residual,
            0
        );

        $this->buckarooLog->addDebug(sprintf(
            '[%s] Added an adjustment line of %.2f so the articles sum to %.2f exactly.',
            __METHOD__,
            $residual,
            $targetTotal
        ));

        return $articles;
    }

    /**
     * Get text for the rounding adjustment line
     *
     * @return Phrase
     */
    public function getAdjustmentLabel(): Phrase
    {
        return __('Adjustment');
    }

    /**
     * Sum the line totals of an assembled article list.
     *
     * @param array $articles
     *
     * @return float
     */
    protected function sumArticleLines(array $articles): float
    {
        // Kept in step with ArticleTotalRegistry::sumArticles(), which the data builders use to
        // derive the amount from these same lines.
        $sum = 0.0;
        foreach (($articles['articles'] ?? []) as $article) {
            if (!is_array($article)) {
                continue;
            }
            $sum += (float)($article['price'] ?? 0) * (float)($article['quantity'] ?? 1);
        }

        return round($sum, 2);
    }

    /**
     * The order items that carry the money the article lines are built from.
     *
     * A dynamic-price bundle keeps its prices, VAT rates and discount on its child items and
     * getAllVisibleItems() hides those, so the parent is dropped in favour of its children. Every
     * other child item is skipped. This is the same set getItemsLines() sends.
     *
     * @return array
     */
    private function getPricedOrderItems(): array
    {
        $items = $this->order === null ? [] : ($this->order->getAllItems() ?: []);

        $byId = [];
        foreach ($items as $item) {
            $byId[(int)$item->getItemId()] = $item;
        }

        $priced = [];
        foreach ($items as $item) {
            if ($this->isDynamicPriceBundle($item)) {
                continue;
            }

            $parentId = (int)$item->getParentItemId();
            if ($parentId !== 0 && !$this->isDynamicPriceBundle($byId[$parentId] ?? null)) {
                continue;
            }

            $priced[] = $item;
        }

        return $priced;
    }

    /**
     * Whether this is a bundle whose children carry the prices.
     *
     * An order item records the price type as product_calculations and normally has no product
     * loaded; a quote item carries the product but no options array, so both are read.
     *
     * @param Item|Order\Item|null $item
     *
     * @return bool
     */
    private function isDynamicPriceBundle($item): bool
    {
        if ($item === null || $item->getProductType() !== Type::TYPE_BUNDLE) {
            return false;
        }

        $options = $item->getProductOptions() ?: [];
        if (array_key_exists('product_calculations', $options)) {
            return (int)$options['product_calculations'] === self::BUNDLE_CALCULATE_CHILD;
        }

        $product = $item->getProduct();
        if ($product === null) {
            return false;
        }

        $priceType = $product->getCustomAttribute('price_type');

        return $priceType !== null && (int)$priceType->getValue() === self::BUNDLE_CALCULATE_CHILD;
    }

    /**
     * Part of the order discount that was never written onto the order items.
     *
     * Mirrors getInvoiceItemsLines() and getDiscountAmount(): the tax compensation counts as
     * discount only when catalog prices exclude tax.
     *
     * @return float
     */
    private function getUnallocatedOrderDiscount(): float
    {
        $order = $this->getOrder();

        if ((float)$order->getDiscountAmount() >= 0) {
            return 0.0;
        }

        $includesTax = (bool)$this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $orderDiscount = abs((float)$order->getDiscountAmount());
        $allocated = 0.0;

        foreach ($this->getPricedOrderItems() as $item) {
            $allocated += abs((float)$item->getDiscountAmount());
            if (!$includesTax) {
                $allocated += abs((float)($item->getDiscountTaxCompensationAmount() ?? 0));
            }
        }

        // The shipping discount is part of the order discount but is carried by the shipping
        // line, not by an order item, so it is allocated rather than missing.
        $allocated += abs((float)($order->getShippingDiscountAmount() ?? 0));

        if (!$includesTax) {
            $orderDiscount += abs((float)$order->getDiscountTaxCompensationAmount());
        }

        return round($orderDiscount - $allocated, 2);
    }

    /**
     * Log when the capture article lines do not sum to the invoice grand total.
     *
     * The lines are NOT padded to close the gap. Klarna validates capture articles against the
     * ones it saw during the reserve, so an invented line is rejected outright ("The following
     * article numbers are unknown or not pending: extra-fees"), and padding up to an inflated
     * invoice total produces a capture above the authorized amount (CAPTURE_NOT_ALLOWED).
     *
     * A gap here means the order data itself is inconsistent - typically a third-party module
     * applying a cart-level discount without allocating it to the order items, which makes
     * Magento's own invoice grand total exceed the order grand total. The amount actually sent
     * is capped at the remaining authorized amount by AbstractInvoiceDataBuilder.
     *
     * @param array $articles
     * @param float $invoiceGrandTotal
     *
     * @return void
     */
    protected function reportInvoiceTotalMismatch(array $articles, float $invoiceGrandTotal): void
    {
        $articleSum = $this->sumArticleLines($articles);
        $diff = round($invoiceGrandTotal - $articleSum, 2);

        if (abs($diff) <= 0.01) {
            return;
        }

        $order = $this->getOrder();

        $this->buckarooLog->addError(sprintf(
            '[%s] Capture article sum does not match the invoice grand total: invoiceGrandTotal=%.2f, '
            . 'articleSum=%.2f, diff=%.2f, orderGrandTotal=%.2f, orderDiscount=%.2f, itemDiscountSum=%.2f. '
            . 'Sending the article lines unchanged; the amount is capped at the remaining authorized amount. '
            . 'Check whether a third-party discount module allocated the order discount to the order items.',
            __METHOD__,
            $invoiceGrandTotal,
            $articleSum,
            $diff,
            (float)$order->getGrandTotal(),
            (float)$order->getDiscountAmount(),
            $this->getItemDiscountSum()
        ));
    }

    /**
     * Sum of the discount actually allocated to the order items.
     *
     * Magento derives invoice totals from these values, so when they do not add up to
     * sales_order.discount_amount the invoice grand total drifts away from the order total.
     *
     * @return float
     */
    private function getItemDiscountSum(): float
    {
        $sum = 0.0;
        foreach ($this->getPricedOrderItems() as $item) {
            $sum += (float)$item->getDiscountAmount();
        }

        return round($sum, 2);
    }

    /**
     * Get items from an invoice
     *
     * @param Invoice $invoice
     *
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

            $orderItem = $item->getOrderItem();

            // Price and discount both come from the ORDER item, which mirrors the quote the
            // reserve was built from. The invoice item would derive the gross unit price from its
            // own rounded tax over the invoiced quantity, drifting a cent from the reserved price.
            $priceItem = $orderItem;

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $item->getQty(),
                $this->getDiscountedProductPrice($priceItem, $this->getReservedUnitDiscount($orderItem)),
                $this->getItemTax($orderItem)
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
     * Per-unit discount as the reserve sent it.
     *
     * Derived from the ORDER item, which mirrors the quote the reserve was built from, so a
     * partial capture repeats the reserved price.
     *
     * @param Order\Item|null $orderItem
     *
     * @return float
     */
    protected function getReservedUnitDiscount($orderItem): float
    {
        if ($orderItem === null) {
            return 0.0;
        }

        return $this->getUnitDiscount($orderItem, (float)$orderItem->getQtyOrdered());
    }

    /**
     * Get Items Data from Creditmemo (refund)
     *
     * @param Order         $order
     * @param InfoInterface $payment
     *
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
     * @param string|null     $articleDescription
     * @param int|string|null $articleId
     * @param int|float       $articleQuantity
     * @param string|float    $articleUnitPrice
     * @param string|float    $articleVat
     *
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
            'identifier' => (string)$articleId,
            'description' => $articleDescription,
            'vatPercentage' => $this->normalizeAmount($articleVat),
            'quantity' => (int)round((float)$articleQuantity),
            'price' => round($this->normalizeAmount($articleUnitPrice), 2)
        ];
    }

    /**
     * Safety net: if the assembled article lines do not sum exactly to the grand total
     * (rounding, unusual discount types, third-party fees), add an adjustment line to
     * close the gap and prevent Klarna/AfterPay AmountDebit validation errors.
     *
     * @param array $articles
     * @param float $grandTotal
     * @param float|null $totalTaxAmount Tax amount for the context (order or invoice); falls back to order tax if null.
     *
     * @return array
     */
    protected function reconcileArticlesWithGrandTotal(array $articles, float $grandTotal, ?float $totalTaxAmount = null): array
    {
        $articleSum = 0.0;
        foreach ($articles['articles'] as $article) {
            if (!is_array($article)) {
                continue;
            }
            $articleSum += (float)($article['price'] ?? 0) * (float)($article['quantity'] ?? 1);
        }

        $diff = round($grandTotal - $articleSum, 2);

        if (abs($diff) <= 0.01) {
            return $articles;
        }

        $contextTax = $totalTaxAmount ?? (float)$this->getOrder()->getTaxAmount();
        $vatRate = $this->calculateResidualVatRate($articles, $diff, $contextTax);

        $this->buckarooLog->addDebug(sprintf(
            '[%s] Article sum mismatch: grandTotal=%.2f, articleSum=%.2f, diff=%.2f, vatRate=%.4f. Adding reconciliation line.',
            __METHOD__,
            $grandTotal,
            $articleSum,
            $diff,
            $vatRate
        ));

        $reconciliationLine = $this->getArticleArrayLine(
            (string)__('Extra Fees'),
            'extra-fees',
            1,
            $diff,
            $vatRate
        );

        $articles['articles'][] = $reconciliationLine;

        return $articles;
    }

    /**
     * Derive the VAT rate for the residual (unaccounted) amount.
     *
     * @param array $articles        Already-built article lines
     * @param float $diff            Residual amount (incl. VAT) not yet in article lines
     * @param float $totalTaxAmount  Total tax for the current context (order or invoice)
     * @return float
     */
    private function calculateResidualVatRate(array $articles, float $diff, float $totalTaxAmount): float
    {
        try {
            $knownTax = 0.0;
            foreach ($articles['articles'] as $article) {
                if (!is_array($article) || empty($article['vatPercentage'])) {
                    continue;
                }
                $lineTotal = (float)($article['price'] ?? 0) * (float)($article['quantity'] ?? 1);
                $vatRate   = (float)$article['vatPercentage'];
                $knownTax += $lineTotal - ($lineTotal / (1 + $vatRate / 100));
            }

            $residualTax     = $totalTaxAmount - $knownTax;
            $residualExclTax = $diff - $residualTax;

            if ($residualExclTax > 0.01 && $residualTax >= 0) {
                return round($residualTax / $residualExclTax * 100, 4);
            }
        } catch (\Exception $e) {
            $this->buckarooLog->addDebug(sprintf('[%s] Could not derive residual VAT rate: %s', __METHOD__, $e->getMessage()));
        }

        return 0.0;
    }

    /**
     * Get the difference between total and items total
     *
     * @param float $diff
     * @param bool  $creditmemo
     *
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
     * @param InfoInterface $payment
     *
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
        $articles = $this->getRewardAndGiftCardLines();

        return empty($articles) ? [] : ['articles' => $articles];
    }

    /**
     * Reward point and gift card discount lines, for the methods that send them.
     *
     * Priced against the invoice being captured when there is one. Magento settles both on the
     * first invoice in practice, so later invoices simply record 0 and get no line.
     *
     * @return array
     */
    protected function getRewardAndGiftCardLines(): array
    {
        $articles = [];

        $rewardLine = $this->getRewardLine();
        if (!empty($rewardLine)) {
            $articles[] = $rewardLine;
        }

        $giftCardLine = $this->getGiftCardLine();
        if (!empty($giftCardLine)) {
            $articles[] = $giftCardLine;
        }

        return $articles;
    }

    /**
     * Get the reward points discount line
     *
     * @return array
     */
    public function getRewardLine(): array
    {
        $discount = $this->getAllocatedCreditAmount('reward_currency_amount');

        if ($discount <= 0) {
            return [];
        }

        $this->buckarooLog->addDebug(__METHOD__ . '|Reward points discount found: ' . $discount);

        return $this->getArticleArrayLine('Discount Reward Points', 5, 1, -$discount, 0);
    }

    /**
     * Get the gift card discount line
     *
     * @return array
     */
    public function getGiftCardLine(): array
    {
        $discount = $this->getAllocatedCreditAmount('gift_cards_amount');

        if ($discount <= 0) {
            return [];
        }

        $this->buckarooLog->addDebug(__METHOD__ . '|Gift card discount found: ' . $discount);

        return $this->getArticleArrayLine('Discount Gift Card', 6, 1, -$discount, 0);
    }
}
