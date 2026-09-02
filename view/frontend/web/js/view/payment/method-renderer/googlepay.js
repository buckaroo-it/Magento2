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
define([
    'Magento_Checkout/js/view/payment/default',
    'Buckaroo_Magento2/js/view/payment/method-renderer/googlepay-client',
    'Buckaroo_Magento2/js/view/payment/method-renderer/googlepay-redirect',
    'Magento_Checkout/js/model/quote'
], function (Component, ClientComponent, RedirectComponent, quote) {
    'use strict';

    var mode = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_googlepay.integrationMode;

    // Extend and return the appropriate renderer dynamically
    return mode === '0' ? ClientComponent : RedirectComponent;
});
