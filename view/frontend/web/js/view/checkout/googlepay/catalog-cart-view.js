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
define(
    [
        'jquery',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'buckaroo/googlepay/pay',
        'buckaroo/googlepay/handlers/order-handler'
    ],
    function (
        $,
        Component,
        quote,
        googlepayPay,
        orderHandler
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Buckaroo_Magento2/checkout/cart/googlepay'
            },

            initialize: function () {
                this._super();

                // Subscribe to transaction result from pay.js
                googlepayPay.transactionResult.subscribe(function (paymentData) {
                    if (paymentData) {
                        orderHandler.setGooglepayPaymentData(paymentData);
                        orderHandler.placeOrder(null);
                    }
                }.bind(this));

                return this;
            },

            showPayButton: function () {
                if (!window.checkoutConfig || !window.checkoutConfig.payment) {
                    return;
                }

                var gpConfig = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_googlepay;

                if (gpConfig && gpConfig.integrationMode === '0') {
                    googlepayPay.setQuote(quote);
                    googlepayPay.showPayButton('cart');
                }
            }
        });
    }
);
