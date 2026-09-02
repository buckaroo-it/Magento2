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

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\PaypalExpress\QuoteCreateResponseInterface;

interface PaypalExpressQuoteCreateInterface
{
  /**
   * Get order breakdown after shipping is applied
   *
   * @param ShippingAddressRequestInterface $shippingAddress
   * @param string                          $page
   * @param string|null                     $orderData
   *
   * @return \Buckaroo\Magento2\Api\Data\PaypalExpress\QuoteCreateResponseInterface
   */
    public function execute(
        ShippingAddressRequestInterface $shippingAddress,
        string $page,
        ?string $orderData = null
    );
}
