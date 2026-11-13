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
        'uiComponent',
        'buckaroo/paypal-express/pay',
    ],
    function (
        Component,
        paypalExpressPay
    ) {
        'use strict';

        return Component.extend({
            initialize: function (config) {
                this._super();
                paypalExpressPay.setConfig(config.data, 'product');
            },

            showPayButton: function () {
                paypalExpressPay.init();
            }
        });
    }
);
