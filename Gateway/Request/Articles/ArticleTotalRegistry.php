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

namespace Buckaroo\Magento2\Gateway\Request\Articles;

/**
 * Carries the assembled article total from the articles data builder to the amount data
 * builder within one gateway request.
 *
 */
class ArticleTotalRegistry
{
    /**
     * Article totals of the current request, keyed by order increment id.
     *
     * @var array<string, float>
     */
    private array $totals = [];

    /**
     * Record the summed article lines for an order.
     *
     * @param string $orderIncrementId
     * @param float  $total
     *
     * @return void
     */
    public function set(string $orderIncrementId, float $total): void
    {
        $this->totals[$orderIncrementId] = round($total, 2);
    }

    /**
     * Article total for an order, or null when the articles were not built in this request.
     *
     * @param string $orderIncrementId
     *
     * @return float|null
     */
    public function get(string $orderIncrementId): ?float
    {
        return $this->totals[$orderIncrementId] ?? null;
    }

    /**
     * Sum the line totals of an assembled article list.
     *
     * @param array $articles
     *
     * @return float
     */
    public function sumArticles(array $articles): float
    {
        $total = 0.0;

        foreach (($articles['articles'] ?? []) as $article) {
            if (!is_array($article)) {
                continue;
            }
            $total += (float)($article['price'] ?? 0) * (float)($article['quantity'] ?? 1);
        }

        return round($total, 2);
    }
}
