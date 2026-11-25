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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;

class AfterpayOldHandler extends AbstractArticlesHandler
{
    public const HIGH_TAX_CATEGORY = 1;
    public const MIDDLE_TAX_CATEGORY = 5;
    public const LOW_TAX_CATEGORY = 2;
    public const ZERO_TAX_CATEGORY = 3;
    public const DEFAULT_TAX_CATEGORY = 4;

    /**
     * @inheritdoc
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
            'vatCategory' => $articleVat ?: self::DEFAULT_TAX_CATEGORY,
            'quantity' => (int)round((float)$articleQuantity),
            'price' => round($this->normalizeAmount($articleUnitPrice), 2)
        ];
    }

    /**
     * @inheritdoc
     */
    public function getArticleRefundArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        return [
            'identifier' => (string)$articleId,
            'description' => $articleDescription,
            'vatCategory' => $articleVat ?: self::DEFAULT_TAX_CATEGORY,
            'quantity' => (int)round((float)$articleQuantity),
            'price' => round($this->normalizeAmount($articleUnitPrice), 2)
        ];
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getItemTax($item): float
    {
        return $this->getTaxCategory($this->getOrder());
    }

    /**
     * @inheritdoc
     */
    protected function getTaxCategory($order)
    {
        $storeId = (int)$order->getStoreId();
        $taxClassId = $this->configProviderBuckarooFee->getBuckarooFeeTaxClass($storeId);

        $taxCategory = self::DEFAULT_TAX_CATEGORY;

        if (!$taxClassId) {
            return $taxCategory;
        }
        /**
         * @var Afterpay $afterPayConfig
         */
        $afterPayConfig = $this->configProviderMethodFactory->get($this->getPayment()->getMethod());

        $highClasses = explode(',', (string)$afterPayConfig->getHighTaxClasses($storeId));
        $middleClasses = explode(',', (string)$afterPayConfig->getMiddleTaxClasses($storeId));
        $lowClasses = explode(',', (string)$afterPayConfig->getLowTaxClasses($storeId));
        $zeroClasses = explode(',', (string)$afterPayConfig->getZeroTaxClasses($storeId));

        if (in_array($taxClassId, $highClasses)) {
            $taxCategory = self::HIGH_TAX_CATEGORY;
        } elseif (in_array($taxClassId, $middleClasses)) {
            $taxCategory = self::MIDDLE_TAX_CATEGORY;
        } elseif (in_array($taxClassId, $lowClasses)) {
            $taxCategory = self::LOW_TAX_CATEGORY;
        } elseif (in_array($taxClassId, $zeroClasses)) {
            $taxCategory = self::ZERO_TAX_CATEGORY;
        }

        return $taxCategory;
    }

    /**
     * @inheritdoc
     */
    protected function getIdentifier($item)
    {
        return $item->getProductId();
    }

    /**
     * @inheritdoc
     */
    protected function getShippingCostsLine($order, &$itemsTotalAmount = 0, bool $creditmemo = false): array
    {
        $shippingCostsArticle = [];

        $shippingAmount = $creditmemo
            ? $this->getRefundedShippingAmount($order)
            : $this->getDiscountedShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $shippingCostsArticle = ['shippingCosts' => $shippingAmount];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }

    /**
     * @inheritdoc
     *
     * The discount lines carry an AfterPay tax category, the same one as the item lines they discount.
     */
    public function getDiscountLines(): array
    {
        return $this->withItemTaxCategory(parent::getDiscountLines());
    }

    /**
     * @inheritdoc
     */
    protected function getStoreCreditLines(): array
    {
        return $this->withItemTaxCategory(parent::getStoreCreditLines());
    }

    /**
     * @inheritdoc
     *
     * Counts the separately sent shippingCosts, like ArticleTotalRegistry::sumArticles() does for the
     * amount, so the captured total a refund is capped at includes the shipping.
     */
    protected function sumArticleLines(array $articles): float
    {
        return round(parent::sumArticleLines($articles) + (float)($articles['shippingCosts'] ?? 0), 2);
    }

    /**
     * Shipping refunded by a credit memo, less its share of the order's shipping discount.
     *
     * A credit memo has no shipping discount of its own: Magento takes the order's shipping
     * discount in proportion to the shipping refunded and adds it to the memo's discount total.
     *
     * @param Creditmemo $creditmemo
     *
     * @return float
     */
    private function getRefundedShippingAmount($creditmemo): float
    {
        $orderShipping = (float)$this->getOrder()->getShippingAmount();
        $discount = $this->getShippingDiscount($this->getOrder());

        if ($discount < 0.01 || $orderShipping <= 0) {
            return (float)$this->getShippingAmount($creditmemo);
        }

        $net = (float)$creditmemo->getShippingAmount();
        $share = round($discount * min(1.0, $net / $orderShipping), 2);

        return max(0.0, round(
            $net
            - $share
            + (float)$creditmemo->getShippingTaxAmount()
            + (float)$creditmemo->getShippingDiscountTaxCompensationAmount(),
            2
        ));
    }

    /**
     * Replace the VAT percentage the shared handler passes for a line with the item tax category.
     *
     * @param array $lines
     *
     * @return array
     */
    private function withItemTaxCategory(array $lines): array
    {
        $taxCategory = $this->getTaxCategory($this->getOrder());

        return array_map(fn(array $line) => array_merge($line, ['vatCategory' => $taxCategory]), $lines);
    }

    /**
     * @inheritdoc
     */
    protected function reconcileArticlesWithGrandTotal(
        array $articles,
        float $grandTotal,
        ?float $totalTaxAmount = null
    ): array {
        $lineKeys = array_keys($articles['articles'] ?? []);

        $articles = parent::reconcileArticlesWithGrandTotal(
            $articles,
            $this->getArticlesTarget($articles, $grandTotal),
            $totalTaxAmount
        );

        // The parent passes a VAT percentage for the added line, AfterPay expects a tax category.
        foreach (array_diff(array_keys($articles['articles']), $lineKeys) as $key) {
            $articles['articles'][$key]['vatCategory'] = self::DEFAULT_TAX_CATEGORY;
        }

        return $articles;
    }

    /**
     * Get the amount the article lines add up to, without the separately sent shipping costs
     *
     * @param array $articles
     * @param float $grandTotal
     *
     * @return float
     */
    private function getArticlesTarget(array $articles, float $grandTotal): float
    {
        return round($grandTotal - (float)($articles['shippingCosts'] ?? 0), 2);
    }
}
