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
        'buckaroo/applepay/pay',
    ],
    function (
        $,
        Component,
        quote,
        applepayPay
    ) {
        'use strict';

        return Component.extend({
            showPayButton: function () {
                if (!window.checkoutConfig || !window.checkoutConfig.payment) {
                    return;
                }

                var apConfig = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay;

                if (apConfig && apConfig.availableButtons &&
                    apConfig.availableButtons.indexOf('Product') !== -1 &&
                    apConfig.integrationMode === '0'
                ) {
                    applepayPay.setQuote(quote);
                    applepayPay.showPayButton('product');
                }
            }
        });
    }
);
