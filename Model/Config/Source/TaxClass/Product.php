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

namespace Buckaroo\Magento2\Model\Config\Source\TaxClass;

class Product extends \Magento\Tax\Model\TaxClass\Source\Product
{
    /**
     * Return product tax class options without an empty option.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return $this->getAllOptions(false);
    }
}
