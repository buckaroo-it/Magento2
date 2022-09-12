<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Articles;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;

class CreditmemoArticlesDataBuilder extends AbstractArticlesDataBuilder
{
    /**
     * Max articles that can be handled by payment method
     */
    const MAX_ARTICLE_COUNT = 99;

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $articles = $this->getCreditmemoArticles();

        return ['articles' => $articles];
    }

    private function getCreditmemoArticles(): array
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->getPayment()->getCreditmemo();
        $paymentMethod = $this->getPayment()->getMethodInstance();
        $articles = [];


        if ($paymentMethod->canRefundPartialPerInvoice() && $creditmemo && $creditmemo->getBaseGrandTotal() !== $this->getOrder()->getBaseTotalInvoiced()) {
            $articles = $this->getCreditmemoArticleData($this->getPayment());
        }

        if (isset($services['RequestParameter'])) {
            $articles = array_merge($services['RequestParameter'], $articles);
        }

        return $articles;
    }

    /**
     * @param InfoInterface $payment
     *
     * @return array
     */
    public function getCreditmemoArticleData(InfoInterface $payment): array
    {
        if ($this->payRemainder) {
            return $this->getCreditmemoArticleDataPayRemainder($payment);
        }

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $articles = [];
        $count = 1;
        $itemsTotalAmount = 0;

        /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
        foreach ($creditmemo->getAllItems() as $item) {
            if (empty($item) || $item->getRowTotalInclTax() == 0) {
                continue;
            }

            $prodPrice = $this->calculateProductPrice($item, $includesTax);
            $prodPriceWithoutDiscount = round($prodPrice - $item->getDiscountAmount() / $item->getQty(), 2);
            $article = $this->getArticleArrayLine(
                $item->getName(),
                $item->getSku(),
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

        // hasCreditmemos returns since 2.2.6 true or false.
        // The current creditmemo is still "in progress" and thus has yet to be saved.
        $serviceLine = $this->getServiceCostLine($count, $creditmemo, $itemsTotalAmount);
        if (!empty($serviceLine)) {
            $articles[] = $serviceLine;
            $count++;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($creditmemo, $count, $itemsTotalAmount);
        if (!empty($shippingCosts)) {
            $articles[] = $shippingCosts;
            $count++;
        }

        //Add diff line
        if (abs($creditmemo->getGrandTotal() - $itemsTotalAmount) > 0.01) {
            $diff = $creditmemo->getGrandTotal() - $itemsTotalAmount;
            $diffLine = $this->getDiffLine($diff);
            $articles[] = $diffLine;
        }

        return $articles;
    }

    protected function getCreditmemoArticleDataPayRemainder($payment, $addRefundType = true)
    {
        $article = $this->getArticleArrayLine(
            1,
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
            1,
            1,
            round($diff, 2),
            4
        );

        return $article;
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
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    )
    {
        return [
            'type' => 'Refund',
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }
}
