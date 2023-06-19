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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay;
use Magento\Sales\Model\Order;

class AfterpayHandler extends AbstractArticlesHandler
{
    /**
     * Get the structure of the array returned to request for refunded items
     *
     * @param string|null $articleDescription
     * @param int|string|null $articleId
     * @param int|float $articleQuantity
     * @param string|float $articleUnitPrice
     * @param string|float $articleVat
     * @return array
     */
    public function getArticleRefundArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        return [
            'refundType' => 'Return',
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

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
            'refundType' => 'Return',
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }
}
