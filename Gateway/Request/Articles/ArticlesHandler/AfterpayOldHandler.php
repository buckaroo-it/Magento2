<?php

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Magento\Quote\Model\Quote\Item;

class AfterpayOldHandler extends AbstractArticlesHandler
{
    public const HIGH_TAX_CATEGORY = 1;
    public const MIDDLE_TAX_CATEGORY = 5;
    public const LOW_TAX_CATEGORY = 2;
    public const ZERO_TAX_CATEGORY = 3;
    public const DEFAULT_TAX_CATEGORY = 4;

    /**
     * Get Item Tax
     *
     * @param Item|\Magento\Sales\Model\Order\Item $item
     * @return float
     */
    protected function getItemTax($item): float
    {
        return $this->getTaxCategory($this->getOrder());
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
    ) {
        return [
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatCategory' => $articleVat ?: self::DEFAULT_TAX_CATEGORY,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    protected function getTaxCategory($order)
    {
        $storeId = (int) $order->getStoreId();
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($storeId);

        $taxCategory = self::DEFAULT_TAX_CATEGORY;

        if (!$taxClassId) {
            return $taxCategory;
        }
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay $afterPayConfig
         */
        $afterPayConfig = $this->configProviderMethodFactory->get($this->getPayment()->getMethod());

        $highClasses   = explode(',', (string)$afterPayConfig->getHighTaxClasses($storeId));
        $middleClasses = explode(',', (string)$afterPayConfig->getMiddleTaxClasses($storeId));
        $lowClasses    = explode(',', (string)$afterPayConfig->getLowTaxClasses($storeId));
        $zeroClasses   = explode(',', (string)$afterPayConfig->getZeroTaxClasses($storeId));

        if (in_array($taxClassId, $highClasses)) {
            $taxCategory = self::HIGH_TAX_CATEGORY;
        } elseif (in_array($taxClassId, $middleClasses)) {
            $taxCategory = self::MIDDLE_TAX_CATEGORY;
        } elseif (in_array($taxClassId, $lowClasses)) {
            $taxCategory = self::LOW_TAX_CATEGORY;
        } elseif (in_array($taxClassId, $zeroClasses)) {
            $taxCategory = self::ZERO_TAX_CATEGORY;
        } else {
            $taxCategory = self::DEFAULT_TAX_CATEGORY;
        }

        return $taxCategory;
    }

    /**
     * @param Item|\Magento\Sales\Model\Order\Invoice\Item|\Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return mixed|string|null
     */
    protected function getIdentifier($item)
    {
        return $item->getProductId();
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

        $shippingCostsArticle = ['shippingCosts' => $shippingAmount];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }

    /**
     * Get the structure of the array returned to request for refunded items
     *
     * @param $articleDescription
     * @param $articleId
     * @param $articleQuantity
     * @param $articleUnitPrice
     * @param string $articleVat
     * @return array
     */
    public function getArticleRefundArrayLine(
        $articleDescription,
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
}
