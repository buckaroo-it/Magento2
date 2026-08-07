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

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $item->getTotalQty(),
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
            $totalQty = (float)$productItem->getTotalQty() ?: (float)$productItem->getQty() ?: 1.0;
            $productPrice = $productItem->getPrice()
                + $productItem->getTaxAmount() / $totalQty;
        }

        if ($productItem->getWeeeTaxAppliedAmount() > 0) {
            $productPrice += $productItem->getWeeeTaxAppliedAmount();
        }

        return (float)$productPrice;
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
        $discount = 0;
        $edition = $this->softwareData->getProductMetaData()->getEdition();

        if ($this->order->getDiscountAmount() < 0) {
            $discount -= abs((float)$this->order->getDiscountAmount());

            $includesTax = (bool)$this->scopeConfig->getValue(
                static::TAX_CALCULATION_INCLUDES_TAX,
                ScopeInterface::SCOPE_STORE
            );
            if (!$includesTax) {
                $discount -= abs((float)$this->order->getDiscountTaxCompensationAmount());
            }
        }

        if ($edition == 'Enterprise' && $this->order->getCustomerBalanceAmount() > 0) {
            $discount -= abs((float)$this->order->getCustomerBalanceAmount());
        }

        return $discount;
    }

    /**
     * Get effective VAT rate as a weighted average of order item tax rates.
     *
     * @return float
     */
    protected function getOrderEffectiveVatRate(): float
    {
        $totalExclTax = 0.0;
        $weightedTax = 0.0;

        foreach (($this->order->getAllVisibleItems() ?: []) as $item) {
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

        $shippingAmount = $this->getShippingAmount($order);
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
            $this->renderInOrderStoreLocale(fn (): string => (string)$this->getShippingFee()),
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
        $discount = $this->getDiscountAmount();

        if ($discount >= 0) {
            return [];
        }

        $vatGroups = $this->getOrderVatGroups();

        if (count($vatGroups) <= 1) {
            $vatRate = !empty($vatGroups) ? (float)array_key_first($vatGroups) : $this->getOrderEffectiveVatRate();
            return [$this->getArticleArrayLine(
                (string)$this->getDiscount(),
                1,
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

            $lines[] = $this->getArticleArrayLine(
                (string)$this->getDiscount(),
                1,
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
        $groups = [];
        foreach (($this->order->getAllVisibleItems() ?: []) as $item) {
            $rowTotal = (float)$item->getRowTotal();
            if ($rowTotal <= 0) {
                continue;
            }
            $vatRate = (float)$item->getTaxPercent();
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

        $discountLines = $this->getDiscountLines();

        $articles['articles'] = $this->getInvoiceItemsLines($currentInvoice, empty($discountLines));

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

        foreach ($discountLines as $discountLine) {
            $articles['articles'][] = $discountLine;
        }

        $additionalLines = $this->getAdditionalLines();
        if (!empty($additionalLines)) {
            $articles = array_merge_recursive($articles, $additionalLines);
        }

        $articles = $this->reconcileArticlesWithGrandTotal($articles, (float)$currentInvoice->getGrandTotal(), (float)$currentInvoice->getTaxAmount());

        return $articles;
    }

    /**
     * Get items from invoice
     *
     * @param Invoice $invoice
     * @param bool    $includePerItemDiscounts When false, per-item discount lines are
     *                                         omitted because a global discount line is
     *                                         already being added by the caller.
     *
     * @return array
     */
    protected function getInvoiceItemsLines(Invoice $invoice, bool $includePerItemDiscounts = true): array
    {
        $articles = [];
        $count = 1;
        $includesTax = (bool)$this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

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

            if ($includePerItemDiscounts && $item->getDiscountAmount() > 0) {
                $count++;
                $discountAmount = (float)$item->getDiscountAmount();
                if (!$includesTax) {
                    $discountAmount += abs((float)($item->getDiscountTaxCompensationAmount() ?? 0));
                }
                $article = $this->getArticleArrayLine(
                    $this->getDiscountDescription($item),
                    $item->getSku(),
                    1,
                    round(-$discountAmount, 2),
                    $this->getItemTax($item->getOrderItem())
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
     *
     * @return string
     */
    protected function getDiscountDescription($item): string
    {
        return $this->getDiscountOn() . ' ' . $item->getName();
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
        return [];
    }
}
