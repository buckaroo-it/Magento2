<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Articles;

use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;

class InvoicedArticlesDataBuilder extends AbstractArticlesDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $articles = $this->getInvoicedArticles();

        return ['articles' => $articles];
    }

    private function getInvoicedArticles(): array
    {
        $invoiceCollection = $this->getOrder()->getInvoiceCollection();
        $numberOfInvoices = $invoiceCollection->count();
        /**
         * @var Invoice $currentInvoice
         */
        $currentInvoice = $invoiceCollection->getLastItem();

        if (isset($currentInvoice)) {
            $articles = $this->getInvoiceArticleData($currentInvoice);
        }

        // For the first invoice possible add payment fee
        if (is_array($articles) && $numberOfInvoices == 1) {
            $serviceLine = $this->getServiceCostLine($currentInvoice);
            if(!empty($serviceLine)) $articles[] = $serviceLine;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($currentInvoice, (count($articles) + 1));
        if(!empty($shippingCosts)) $articles[] = $shippingCosts;

        return $articles;
    }

    /**
     * @param Invoice $invoice
     *
     * @return array
     */
    public function getInvoiceArticleData($invoice)
    {
        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        // Set loop variables
        $articles = [];
        $count    = 1;

        /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
        foreach ($invoice->getAllItems() as $item) {
            if (empty($item) || $item->getRowTotalInclTax() == 0) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getOrderItem()->getTaxPercent()
            );

            // @codingStandardsIgnoreStart
            $articles[] = $article;
            // @codingStandardsIgnoreEnd

            // Capture calculates discount per order line
            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    'Korting op ' . $item->getName(),
                    $item->getSku(),
                    1,
                    number_format(($item->getDiscountAmount()*-1), 2),
                    0
                );
                // @codingStandardsIgnoreStart
                $articles[] = $article;
                // @codingStandardsIgnoreEnd
            }

            if ($count < self::MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        return $articles;
    }
}
