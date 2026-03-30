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
