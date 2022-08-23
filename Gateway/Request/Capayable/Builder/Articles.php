<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Gateway\Request\Capayable\Builder;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class Articles extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        
        $articles = [];
        
        foreach($this->getOrder()->getAllItems() as $item) {

            /** @var \Magento\Sales\Api\Data\OrderItemInterface $item */

            if (empty($item) || $item->getParentItem() != null) {
                continue;
            }

            $articles[] = [
                'identifier'        => $item->getSku(),
                'description'       => $item->getName(),
                'quantity'          => $item->getQtyOrdered(),
                'price'             => $item->getBasePriceInclTax()
            ];
        }
        return [
            'articles' => array_slice($articles, 0, 99)
        ];
    }
}
