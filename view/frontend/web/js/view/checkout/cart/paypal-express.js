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
    'uiComponent',
    'jquery',
    'Buckaroo_Magento2/js/view/checkout/paypal-express/pay'
], function (Component, $, paypalExpressPay) {
    'use strict';

    return Component.extend({
        
        defaults: {
            config: {},
            context: 'cart'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initPaypalExpress();
            return this;
        },

        /**
         * Initialize PayPal Express functionality
         */
        initPaypalExpress: function () {
            var self = this;
            
            // Set up the global function for backward compatibility
            window.showPaypalExpressButton = this.showPaypalExpressButton.bind(this);
            
            // Wait a bit for DOM elements to be ready, then initialize
            setTimeout(function() {
                self.showPaypalExpressButton();
            }, 100);
        },

        /**
         * Show PayPal Express button
         */
        showPaypalExpressButton: function () {
            try {
                paypalExpressPay.setConfig(this.config, this.context);
                paypalExpressPay.init();
            } catch (error) {
            }
        }
    });
}); 