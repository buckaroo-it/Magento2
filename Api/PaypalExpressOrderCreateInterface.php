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

namespace Buckaroo\Magento2\Api;

use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterface;

interface PaypalExpressOrderCreateInterface
{
    /**
     * Get order breakdown after shipping is applied
     *
     * @param string      $paypal_order_id
     * @param string|null $cart_id
     *
     * @return \Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterface
     */
    public function execute(
        string $paypal_order_id,
        ?string $cart_id = null
    );
}
