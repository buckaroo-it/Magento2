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

use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Article lines for Klarna KP.
 *
 * Buckaroo merges reserved lines with the same ArticleNumber and can only capture them together.
 * When a SKU is on more than one line, each line gets its own ArticleNumber: the SKU followed by
 * the quote item id, so the reserve and the captures refer to the same line.
 */
class KlarnaKpHandler extends AbstractArticlesHandler
{
    /**
     * Payment flag set when the reservation was made with unique article numbers.
     */
    public const UNIQUE_ARTICLE_NUMBERS = 'buckaroo_klarnakp_unique_article_numbers';

    /**
     * @inheritdoc
     */
    protected function getIdentifier($item)
    {
        $identifier = parent::getIdentifier($item);
        $quoteItemId = $this->getDuplicateSkuQuoteItemId($item);

        return $quoteItemId === null ? $identifier : $identifier . '-' . $quoteItemId;
    }

    /**
     * Whether a SKU is on more than one line of the order.
     *
     * @return bool
     */
    public function hasDuplicateSku(): bool
    {
        $counts = array_count_values($this->getLineSkus($this->getOrder()->getAllItems()));

        return !empty($counts) && max($counts) > 1;
    }

    /**
     * Unique article numbers of the order that are not among the given article lines.
     *
     * UpdateReservation replaces every line of the reservation, so it may only be sent when it
     * carries each line the captures will ask for.
     *
     * @param array $articles
     *
     * @return string[]
     */
    public function getMissingUniqueArticleNumbers(array $articles): array
    {
        $sent = [];
        foreach ($articles as $article) {
            if (is_array($article) && isset($article['identifier'])) {
                $sent[(string)$article['identifier']] = true;
            }
        }

        $orderItems = $this->getOrder()->getAllItems();
        $counts = array_count_values($this->getLineSkus($orderItems));
        $missing = [];

        foreach ($orderItems as $orderItem) {
            if ($orderItem->getParentItemId() || $orderItem->getParentItem()
                || ($counts[(string)$orderItem->getSku()] ?? 0) < 2
            ) {
                continue;
            }

            $expected = parent::getIdentifier($orderItem) . '-' . (int)$orderItem->getQuoteItemId();
            if (!(int)$orderItem->getQuoteItemId() || !isset($sent[$expected])) {
                $missing[] = $expected;
            }
        }

        return $missing;
    }

    /**
     * Quote item id of a line whose SKU is also on another line, or null.
     *
     * Quote items are reserved and always get unique numbers. Invoice items are captured and only
     * get them when the reservation has them.
     *
     * @param mixed $item
     *
     * @return int|null
     */
    private function getDuplicateSkuQuoteItemId($item): ?int
    {
        if ($item instanceof QuoteItem) {
            $quote = $item->getQuote();
            $lines = $quote !== null ? $quote->getAllItems() : [];
            $quoteItemId = (int)$item->getId();
        } else {
            if (!$this->getPayment()->getAdditionalInformation(self::UNIQUE_ARTICLE_NUMBERS)) {
                return null;
            }

            $orderItem = $item->getOrderItem();
            $lines = $this->getOrder()->getAllItems();
            $quoteItemId = $orderItem !== null ? (int)$orderItem->getQuoteItemId() : 0;
        }

        $counts = array_count_values($this->getLineSkus($lines));
        if ($quoteItemId <= 0 || ($counts[(string)$item->getSku()] ?? 0) < 2) {
            return null;
        }

        return $quoteItemId;
    }

    /**
     * SKUs of the top-level lines; child items carry their parent's SKU.
     *
     * @param array $items
     *
     * @return string[]
     */
    private function getLineSkus(array $items): array
    {
        $skus = [];

        foreach ($items as $item) {
            if ($item->getParentItemId() || $item->getParentItem()) {
                continue;
            }

            $skus[] = (string)$item->getSku();
        }

        return $skus;
    }
}
