<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Articles;

use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\ScopeInterface;

class ArticlesDataBuilder extends AbstractArticlesDataBuilder
{
    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        if ($this->payRemainder) {
            return $this->getRequestArticlesDataPayRemainder();
        }

        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $quote = $this->quoteFactory->create()->load($this->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

        // Set loop variables
        $articles = [];
        $count = 1;

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($cartData as $item) {

            if (empty($item)
                || $item->hasParentItemId()
                || $item->getRowTotalInclTax() == 0
            ) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getTaxPercent() ?? 0
            );

            // @codingStandardsIgnoreStart
            $articles[] = $article;
            // @codingStandardsIgnoreEnd

            if ($count < self::KLARNA_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $serviceLine = $this->getServiceCostLine($this->order);

        if (!empty($serviceLine)) {
            $articles[] = $serviceLine;
            $count++;
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($this->order, $count);

        if (!empty($shippingCosts)) {
            $articles[] = $shippingCosts;
            $count++;
        }

        $discountline = $this->getDiscountLine();

        if (!empty($discountline)) {
            $articles[] = $discountline;
        }

        return ['articles' => $articles];
    }
}
