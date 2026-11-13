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
