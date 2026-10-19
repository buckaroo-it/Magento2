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
    'jquery'
], function (Component, $) {
    'use strict';

    return Component.extend({

        defaults: {
            buttonStyle: '',
            buttonStyleElement: ''
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initApplePay();
            return this;
        },

        /**
         * Initialize Apple Pay functionality
         */
        initApplePay: function () {
            var self = this;
            
            if (!customElements.get('apple-pay-button')) {
                this.requireApplePay(function() {
                    self.renderButton();
                });
            } else {
                this.renderButton();
            }

            this.bindEvents();
        },

        /**
         * Load Apple Pay SDK
         * @param {Function} callback
         */
        requireApplePay: function (callback) {
            const e = document.createElement("script");
            e.src = `https://applepay.cdn-apple.com/jsapi/v1.1.0/apple-pay-button.js`;
            e.type = "text/javascript";
            e.addEventListener("load", callback);
            document.getElementsByTagName("head")[0].appendChild(e);
        },

        /**
         * Render Apple Pay button
         */
        renderButton: function () {
            const buttonElement = $('apple-pay-button');
            buttonElement.attr('lang', document.firstElementChild.lang);
            buttonElement.attr('buttonStyle', this.buttonStyle);
            buttonElement.attr('type', "buy");
        },

        /**
         * Bind change events
         */
        bindEvents: function () {
            var self = this;

            $('#' + this.buttonStyleElement).change(function () {
                if ($(this).val() !== self.buttonStyle) {
                    self.buttonStyle = $(this).val();
                    self.renderButton();
                }
            });
        }
    });
}); 