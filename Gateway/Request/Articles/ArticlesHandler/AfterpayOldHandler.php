<?php

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Magento\Quote\Model\Quote\Item;

class AfterpayOldHandler extends AbstractArticlesHandler
{
    /**
     * @param Item $item
     * @return float
     */
    protected function getItemTax(Item $item): float
    {
        return $this->getTaxCategory($this->getOrder());
    }

    /**
     * @return array
     */
    public function getArticleArrayLine(
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    )
    {
        return [
            'identifier' => $articleId,
            'description' => $articleDescription,
            'vatCategory' => $articleVat,
            'quantity' => $articleQuantity,
            'price' => $articleUnitPrice
        ];
    }

}
