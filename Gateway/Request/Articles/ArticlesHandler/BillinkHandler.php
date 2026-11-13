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

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Magento\Quote\Model\Quote\Item;

class BillinkHandler extends AbstractArticlesHandler
{
    /**
     * Get the discount cost lines
     *
     * @return array
     */
    public function getDiscountLines(): array
    {
        return [];
    }

    /**
     * Suppress the store credit line, as Billink prices every discount on the item lines.
     *
     * Same reason the global discount line is suppressed.
     *
     * @return array
     */
    protected function getStoreCreditLines(): array
    {
        return [];
    }
}
