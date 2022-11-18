<?php

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Magento\Quote\Model\Quote\Item;

class TinkaHandler extends AbstractArticlesHandler
{
    /**
     * @return array
     */
    public function getArticleArrayLine(
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ) {
        return [
            'unitCode' => $articleId,
            'description' => $articleDescription,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

    /**
     * @return array
     */
    public function getArticleRefundArrayLine(
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        return [
            'unitCode' => $articleId,
            'description' => $articleDescription,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }
}
