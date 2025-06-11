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
    'buckaroo/payconiq/pay'
], function (Component, payconiqPay) {
    'use strict';

    return Component.extend({
        
        defaults: {
            transactionKey: ''
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initPayconiq();
            return this;
        },

        /**
         * Initialize Payconiq functionality
         */
        initPayconiq: function () {
            // Set up the global function for backward compatibility
            window.cancelPayment = this.cancelPayment.bind(this);
            
            // Initialize Payconiq
            payconiqPay.setTransactionKey(this.transactionKey);
            payconiqPay.showQrCode();
        },

        /**
         * Cancel payment function
         */
        cancelPayment: function () {
            payconiqPay.cancelPayment();
        }
    });
}); 