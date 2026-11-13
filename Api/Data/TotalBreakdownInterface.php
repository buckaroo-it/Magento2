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
namespace Buckaroo\Magento2\Api\Data;

interface TotalBreakdownInterface
{
    /**
     * Get the item total breakdown entry
     *
     * @return \Buckaroo\Magento2\Api\Data\BreakdownItemInterface
     */
    public function getItemTotal();

    /**
     * Get the shipping breakdown entry
     *
     * @return \Buckaroo\Magento2\Api\Data\BreakdownItemInterface
     */
    public function getShipping();

    /**
     * Get the tax total breakdown entry
     *
     * @return \Buckaroo\Magento2\Api\Data\BreakdownItemInterface
     */
    public function getTaxTotal();
}
