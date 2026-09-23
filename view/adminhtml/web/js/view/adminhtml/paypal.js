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
            buttonColor: '',
            buttonShape: '',
            buttonColorElement: '',
            buttonShapeElement: '',
            hasRendered: false,
            element: null
        },

        /**
         * Funding sources, mirroring the storefront SDK call in js/lib/buckaroo-sdk.js so the
         * preview cannot show a button the shopper is never offered. Keep the two in sync.
         */
        fundingParams: 'disable-funding=credit,card,bancontact,blik,eps,giropay,ideal,' +
            'mercadopago,mybank,p24,sepa,sofort,venmo&enable-funding=paylater',

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initPayPal();
            return this;
        },

        /**
         * Initialize PayPal functionality
         */
        initPayPal: function () {
            var self = this;

            if (!window.paypal) {
                this.requirePayPal(function() {
                    self.renderButton();
                });
            } else {
                this.renderButton();
            }

            this.bindEvents();
        },

        /**
         * Load PayPal SDK
         * @param {Function} callback
         */
        requirePayPal: function (callback) {
            const e = document.createElement("script");
            const clientTestIp = 'AfHztAEfaf3f76tNy8j_Z86w5y-fGbqbBt04PXppVFtJatje79gVSB27DwBENnyFgfhFvKzgJbegNpHv';
            e.src = `https://www.paypal.com/sdk/js?client-id=${clientTestIp}&${this.fundingParams}`;
            e.type = "text/javascript";
            e.addEventListener("load", callback);
            document.getElementsByTagName("head")[0].appendChild(e);
        },

        /**
         * Render PayPal button
         */
        renderButton: function () {
            var self = this;
            const paypalSDK = window.paypal;

            // Get the container element - in Magento UI Component, element might be selector or jQuery object
            var container = this.element;

            // If element is not set or empty, try to find the container
            if (!container) {
                container = '#paypal-button-container';
            }

            // Check if element exists in DOM before rendering
            var $container = $(container);
            if (!$container.length) {
                return;
            }

            if (this.hasRendered) {
                paypalSDK.Buttons().close();
                $container.empty();
            }

            paypalSDK.Buttons({
                onInit: function (data, actions) {
                    // Disable the buttons
                    actions.disable();
                },
                style: {
                    color: this.buttonColor,
                    shape: this.buttonShape === '1' ? 'pill' : 'rect'
                }
            }).render(container).then(function () {
                self.hasRendered = true;
            }).catch(function (error) {
            });
        },

        /**
         * Bind change events
         */
        bindEvents: function () {
            var self = this;

            $('#' + this.buttonColorElement).change(function () {
                if ($(this).val() !== self.buttonColor) {
                    self.buttonColor = $(this).val();
                    self.renderButton();
                }
            });

            $('#' + this.buttonShapeElement).change(function () {
                if ($(this).val() !== self.buttonShape) {
                    self.buttonShape = $(this).val();
                    self.renderButton();
                }
            });
        }
    });
});
