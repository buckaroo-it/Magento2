<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Capayable;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderItemInterface;

class ArticlesDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $articles = [];

        // Use getAllVisibleItems() which is already optimized and doesn't trigger parent_item_id queries
        foreach ($this->getOrder()->getAllVisibleItems() as $item) {
            /** @var OrderItemInterface $item */
            $articles[] = [
                'identifier'  => $item->getSku(),
                'description' => $item->getName(),
                'quantity'    => $item->getQtyOrdered(),
                'price'       => $item->getBasePriceInclTax()
            ];
        }
        return [
            'articles' => array_slice($articles, 0, 99)
        ];
    }
}
