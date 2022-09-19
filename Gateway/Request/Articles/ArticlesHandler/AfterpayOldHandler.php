<?php

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Magento\Quote\Model\Quote\Item;

class AfterpayOldHandler extends AbstractArticlesHandler
{
    /**
     * @param Item $item
     * @return float
     */
    protected function getItemTax(Item $item): float
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
    )
    {
        return [
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatCategory' => $articleVat ?: 4,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    protected function getTaxCategory($order)
    {
        $storeId = (int) $order->getStoreId();
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($storeId);

        $taxCategory = 4;

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
            $taxCategory = 1;
        } elseif (in_array($taxClassId, $middleClasses)) {
            $taxCategory = 5;
        } elseif (in_array($taxClassId, $lowClasses)) {
            $taxCategory = 2;
        } elseif (in_array($taxClassId, $zeroClasses)) {
            $taxCategory = 3;
        } else {
            // No classes == 4
            $taxCategory = 4;
        }

        return $taxCategory;
    }

    /**
     * @param Item $item
     * @return mixed|string|null
     */
    protected function getIdentifier(Item $item)
    {
        return $item->getId();
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @return array
     */
    protected function getShippingCostsLine($order, &$itemsTotalAmount = 0)
    {
        return [];
    }

}
