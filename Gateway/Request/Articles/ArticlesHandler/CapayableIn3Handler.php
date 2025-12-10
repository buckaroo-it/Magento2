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

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class CapayableIn3Handler extends AbstractArticlesHandler
{
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
            'quantity' => $articleQuantity,
            'price' => floor($articleUnitPrice * 100) / 100
        ];
    }

    /**
     * Override to apply discount proportionally to products instead of separate line
     * In3 API doesn't accept negative GrossUnitPrice values
     *
     * @param Order $order
     * @param InfoInterface $payment
     * @return array
     * @throws LocalizedException
     */
    public function getOrderArticlesData(Order $order, InfoInterface $payment): array
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $this->setPayment($payment);
        $this->setOrder($order);

        if ($this->payReminderService->isPayRemainder($order)) {
            return ['articles' => [0 => $this->getRequestArticlesDataPayRemainder()]];
        }

        // Get discount amount to distribute across products
        $discountAmount = abs((float)$order->getDiscountAmount());

        // Get items with discount applied proportionally
        $articles['articles'] = $this->getItemsLinesWithDiscount($discountAmount);

        $serviceLine = $this->getServiceCostLine($this->getOrder());
        if (!empty($serviceLine)) {
            $articles = array_merge_recursive($articles, $serviceLine);
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($this->getOrder());
        if (!empty($shippingCosts)) {
            $articles = array_merge_recursive($articles, $shippingCosts);
        }

        $additionalLines = $this->getAdditionalLines();
        if (!empty($additionalLines)) {
            $articles = array_merge_recursive($articles, $additionalLines);
        }

        return $articles;
    }

    /**
     * Get items lines with discount applied using Magento's native discount calculation
     *
     * @param float $totalDiscount
     * @return array
     * @throws LocalizedException
     */
    protected function getItemsLinesWithDiscount(float $totalDiscount): array
    {
        $articles = [];
        $count = 1;
        $bundleProductQty = 0;

        $quote = $this->getQuote();
        $cartData = $quote->getAllItems();

        /**
         * @var \Magento\Quote\Model\Quote\Item $item
         */
        foreach ($cartData as $item) {
            if ($this->skipBundleProducts($item, $bundleProductQty)) {
                continue;
            }

            if ($this->skipItem($item, $bundleProductQty)) {
                continue;
            }

            $itemQty = $bundleProductQty ?: $item->getQty();

            $itemPrice = $this->calculateProductPrice($item);

            if ($item->getDiscountAmount() > 0) {
                $discountPerUnit = $item->getDiscountAmount() / $itemQty;
                $itemPrice = $itemPrice - $discountPerUnit;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $itemQty,
                $itemPrice,
                $this->getItemTax($item)
            );

            $articles[] = $article;

            if ($count >= self::MAX_ARTICLE_COUNT) {
                break;
            }

            $count++;
        }

        return $articles;
    }
}
