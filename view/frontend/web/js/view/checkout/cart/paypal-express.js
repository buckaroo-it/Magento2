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
                console.error('PayPal Express initialization error:', error);
            }
        }
    });
}); 