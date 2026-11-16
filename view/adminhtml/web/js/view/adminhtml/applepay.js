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
    'jquery'
], function (Component, $) {
    'use strict';

    return Component.extend({

        defaults: {
            buttonStyle: '',
            buttonStyleElement: '',
            buttonType: '',
            buttonTypeElement: ''
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
            // Never write an empty type: Apple's button throws on type="".
            buttonElement.attr('type', this.buttonType || 'buy');
        },

        /**
         * Bind change events
         */
        bindEvents: function () {
            var self = this;

            // An overridden template may not pass both ids; "#" alone is an invalid selector.
            var selector = [this.buttonStyleElement, this.buttonTypeElement]
                .filter(Boolean)
                .map(function (id) {
                    return '#' + id;
                })
                .join(', ');

            if (!selector) {
                return;
            }

            $(selector).change(function () {
                if (self.buttonStyleElement) {
                    self.buttonStyle = $('#' + self.buttonStyleElement).val();
                }
                if (self.buttonTypeElement) {
                    self.buttonType = $('#' + self.buttonTypeElement).val();
                }
                self.renderButton();
            });
        }
    });
}); 