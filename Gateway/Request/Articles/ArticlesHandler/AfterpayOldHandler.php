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
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatCategory' => $articleVat ?: self::DEFAULT_TAX_CATEGORY,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
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
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatCategory' => $articleVat ?: self::DEFAULT_TAX_CATEGORY,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    /**
     * @inheritdoc
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
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($storeId);

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
    protected function getShippingCostsLine($order, int &$itemsTotalAmount = 0): array
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
