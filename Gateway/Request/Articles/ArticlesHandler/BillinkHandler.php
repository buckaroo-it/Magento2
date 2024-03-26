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

use Magento\Quote\Model\Quote\Item;

class BillinkHandler extends AbstractArticlesHandler
{
    /**
     * Get the discount cost lines
     *
     * @return array
     */
    public function getDiscountLine(): array
    {
        return [];
    }

    /**
     * Get items lines
     *
     * @return array
     */
    protected function getItemsLines(): array
    {
        $articles = [];
        $count = 1;
        $bundleProductQty = 0;

        $quote = $this->quoteFactory->create()->load($this->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

        /**
         * @var Item $item
         */
        foreach ($cartData as $item) {
            if ($this->skipItem($item)) {
                continue;
            }

            if ($this->skipBundleProducts($item, $bundleProductQty)) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $bundleProductQty ?: $item->getQty(),
                $this->calculateProductPrice($item),
                $this->getItemTax($item)
            );

            $articles[] = $article;

            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    'Korting op ' . $item->getName(),
                    $item->getSku(),
                    1,
                    number_format(($item->getDiscountAmount() * -1), 2),
                    $item->getTaxPercent() ?: 0
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
}
