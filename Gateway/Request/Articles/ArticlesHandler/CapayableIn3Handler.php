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

use Buckaroo\Magento2\Exception as BuckarooException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class CapayableIn3Handler extends AbstractArticlesHandler
{
    /**
     * In3 rejects an article identifier longer than 64 characters.
     */
    protected const IDENTIFIER_MAX_LENGTH = 64;

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
            // round first: 19.99 * 100 is 1998.9999... in floating point and would floor to 19.98
            'price' => floor(round($articleUnitPrice * 100, 4)) / 100
        ];
    }

    /**
     * Override to apply discount proportionally to products instead of separate line
     *
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

        // Get items with discount applied proportionally
        $articles['articles'] = $this->getItemsLinesWithDiscount();

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

        $articles = $this->absorbRoundingResidual($articles, (float)$order->getGrandTotal());

        return $this->reconcileWithGrandTotal($articles, (float)$order->getGrandTotal());
    }

    /**
     * In3 is charged the sum of the article lines, so that sum has to equal the order total.
     *
     * Unit prices are rounded down, so the lines can fall short; the shortfall is carried on an
     * adjustment line, because In3 accepts no negative prices. Lines that cannot be made to add up
     * to the total, or more lines than one request may carry, refuse the payment.
     *
     * @param array $articles
     * @param float $grandTotal
     *
     * @throws BuckarooException
     *
     * @return array
     */
    private function reconcileWithGrandTotal(array $articles, float $grandTotal): array
    {
        $residual = round($grandTotal - $this->sumArticleLines($articles), 2);

        if ($residual > 0.01 && count($articles['articles']) < self::MAX_ARTICLE_COUNT) {
            $articles['articles'][] = $this->getArticleArrayLine(
                (string)$this->getAdjustmentLabel(),
                self::ADJUSTMENT_IDENTIFIER,
                1,
                $residual
            );
            $residual = round($grandTotal - $this->sumArticleLines($articles), 2);
        }

        if (abs($residual) > 0.01 || count($articles['articles']) > self::MAX_ARTICLE_COUNT) {
            $this->buckarooLog->addError(sprintf(
                '[%s] In3 article lines cannot represent order %s: %d lines, %.2f off the total of %.2f',
                __METHOD__,
                $this->getOrder()->getIncrementId(),
                count($articles['articles']),
                $residual,
                $grandTotal
            ));

            throw new BuckarooException(
                __('This order cannot be paid with In3. Please choose another payment method.')
            );
        }

        return $articles;
    }

    /**
     * Get items lines with discount applied using Magento's native discount calculation
     *
     * @return array
     * @throws LocalizedException
     */
    protected function getItemsLinesWithDiscount(): array
    {
        $articles = [];
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

            $itemQty = $item->getTotalQty();

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
        }

        return $articles;
    }
}
