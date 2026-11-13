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
namespace Buckaroo\Magento2\Api\Data\PaypalExpress;

use Buckaroo\Magento2\Api\Data\BreakdownItemInterface;

interface QuoteCreateResponseInterface extends BreakdownItemInterface
{
    /**
     * Get order breakdown
     *
     * @return \Buckaroo\Magento2\Api\Data\TotalBreakdownInterface
     */
    public function getBreakdown();

    /**
     * Get masked cart id
     *
     * @return string
     */
    public function getCartId();
}
