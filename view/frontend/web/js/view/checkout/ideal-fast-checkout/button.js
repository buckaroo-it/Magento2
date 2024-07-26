define(
    [
        'uiComponent',
        'buckaroo/ideal-fast-checkout/pay',
        'jquery'
    ],
    function (
        Component,
        idealFastCheckoutPay,
        $
    ) {
        'use strict';

        return Component.extend({
            initialize: function (config) {
                this._super();
                idealFastCheckoutPay.setConfig(config.data, 'product');
                this.initPayButton();
            },

            initPayButton: function () {
                $(document).ready(() => {
                    idealFastCheckoutPay.init();
                });
            }
        });
    }
);
