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

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $shippingCostsArticle = ['shippingCosts' => $shippingAmount];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }
}
