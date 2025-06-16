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

class KlarnaKpHandler extends AbstractArticlesHandler
{
    /**
     * Get Additional Lines for specific methods
     *
     * @return array
     */
    protected function getAdditionalLines(): array
    {
        $articles = [];

        $rewardLine = $this->getRewardLine();
        if (!empty($rewardLine)) {
            $articles[] = $rewardLine;
        }

        $giftCardLine = $this->getGiftCardLine();
        if (!empty($giftCardLine)) {
            $articles[] = $giftCardLine;
        }

        return ['articles' => $articles];    }

    /**
     * Get the reward cost lines
     *
     * @return array
     */
    public function getRewardLine()
    {
        $article = [];
        $discount = (float)$this->getQuote()->getRewardCurrencyAmount();

        if ($discount <= 0) {
            return $article;
        }

        return $this->getArticleArrayLine(
            'Discount Reward Points',
            5,
            1,
            -$discount,
            0
        );
    }

    /**
     * Get the gift card discount line
     *
     * @return array
     */
    public function getGiftCardLine(): array
    {
        $discount = (float)$this->getQuote()->getGiftCardsAmount(); // or getBaseGiftCardsAmount()

        if ($discount <= 0) {
            return [];
        }

        return $this->getArticleArrayLine(
            'Discount Gift Card',
            6,
            1,
            -$discount,
            0
        );
    }
}
