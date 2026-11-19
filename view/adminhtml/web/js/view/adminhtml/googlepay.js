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

    // Locales the storefront button supports (buckaroo-sdk.js getButtonLocale); anything else renders in English.
    var SUPPORTED_LOCALES = ['en', 'de', 'nl', 'fr', 'it', 'es'];

    return Component.extend({

        defaults: {
            buttonStyle: '',
            buttonStyleElement: '',
            buttonType: '',
            buttonTypeElement: '',
            buttonLocale: '',
            loadErrorMessage: ''
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initGooglePay();
            return this;
        },

        /**
         * Load Google's pay.js once, then render the preview button
         */
        initGooglePay: function () {
            var self = this;

            if (this.isGooglePayLoaded()) {
                this.renderButton();
            } else {
                this.requireGooglePay(function () {
                    self.renderButton();
                });
            }

            this.bindEvents();
        },

        /**
         * @returns {boolean}
         */
        isGooglePayLoaded: function () {
            return !!(window.google && window.google.payments && window.google.payments.api);
        },

        /**
         * Load the Google Pay SDK
         * @param {Function} callback
         */
        requireGooglePay: function (callback) {
            var script = document.createElement('script');

            script.src = 'https://pay.google.com/gp/p/js/pay.js';
            script.type = 'text/javascript';
            script.addEventListener('load', callback);
            // Blocked by an ad blocker, a stricter CSP or no network
            script.addEventListener('error', this.showLoadError.bind(this));
            document.getElementsByTagName('head')[0].appendChild(script);
        },

        /**
         * The store view's language, filtered like the storefront button (unsupported renders English)
         *
         * @returns {string}
         */
        getLocale: function () {
            var locale = (this.buttonLocale || 'en').toLowerCase();

            return SUPPORTED_LOCALES.indexOf(locale) !== -1 ? locale : 'en';
        },

        /**
         * Tell the merchant why the preview is empty
         */
        showLoadError: function () {
            $('#googlepay-button-preview').empty().text(this.loadErrorMessage);
        },

        /**
         * Render the Google Pay button with the selected colour, type and the store's language
         */
        renderButton: function () {
            if (!this.isGooglePayLoaded()) {
                return;
            }

            var container = $('#googlepay-button-preview');
            var button;

            try {
                button = new window.google.payments.api.PaymentsClient({environment: 'TEST'}).createButton({
                    buttonColor: this.buttonStyle || 'black',
                    buttonType: this.buttonType || 'buy',
                    buttonLocale: this.getLocale(),
                    buttonRadius: 4, // same corners as the storefront button (buckaroo-sdk.js)
                    buttonSizeMode: 'static',
                    onClick: function () {}
                });
            } catch (e) {
                this.showLoadError();
                return;
            }

            // Google's brand guidelines show the white button on a dark background
            container.css('background', this.buttonStyle === 'white' ? '#333' : '#f9f9f9');
            container.empty().append(button);
        },

        /**
         * Re-render the preview when the colour or type changes
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

            $(selector).on('change', function () {
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
