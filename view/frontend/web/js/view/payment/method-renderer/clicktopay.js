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
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'buckaroo/checkout/common',
        'Magento_Checkout/js/model/totals',
        'BuckarooSdk'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon,
        totals
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_clicktopay'
                },
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,
                buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_clicktopay,

                initObservable: function () {
                    this._super().observe(['transientToken', 'identifier']);
                    this.transientToken('');
                    this.identifier('');
                    return this;
                },

                /**
                 * Initialize the BuckarooSdk ClickToPay CaptureContext and render the Drop-in UI.
                 * Only runs when this payment method is the selected one.
                 * Overrides BuckarooSdk.TokenApi.getAccessToken to proxy through Magento (avoids CORS).
                 */
                initializeCaptureContext: function () {
                    var self = this;
                    var config = this.buckaroo;

                    if (this.getCode() !== this.isChecked()) {
                        return;
                    }

                    if (!config || !config.merchantIdentifier) {
                        return;
                    }

                    if (typeof BuckarooSdk === 'undefined' || !BuckarooSdk.ClickToPay) {
                        return;
                    }

                    var buttonWrapperId = '#buckaroo-clicktopay-button';
                    var screenWrapperId = '#buckaroo-clicktopay-screen';

                    if (!$(buttonWrapperId).length || !$(screenWrapperId).length) {
                        return;
                    }

                    var grandTotal = totals.totals() ? totals.totals().grand_total : 0;

                    BuckarooSdk.TokenApi.getAccessToken = function () {
                        return $.ajax({
                            url: window.BASE_URL + 'buckaroo/clicktopay/token',
                            method: 'POST',
                            data: { form_key: window.FORM_KEY }
                        }).then(function (response) {
                            return response.access_token;
                        });
                    };

                    try {
                        var captureContextOptions = new BuckarooSdk.ClickToPay.CaptureContextOptions(
                            config.merchantIdentifier,
                            config.targetOrigins,
                            config.country,
                            config.locale,
                            {
                                currency: config.currency,
                                totalAmount: grandTotal
                            },
                            function (paymentData) {
                                self.transientToken(paymentData.transientToken || '');
                                self.identifier(paymentData.identifier || '');
                                self.placeOrder(null, null);
                            }
                        );

                        var captureContext = new BuckarooSdk.ClickToPay.CaptureContext(
                            buttonWrapperId,
                            screenWrapperId,
                            captureContextOptions
                        );

                        // The GenerateCaptureContext API returns PascalCase keys
                        // (Successful, ScriptUrl, Jwt, Identifier, ErrorReason) but the
                        // SDK reads camelCase. Normalize the response so the SDK's
                        // success check and Drop-in UI initialization work.
                        var originalGenerateCaptureContext = captureContext.generateCaptureContext;
                        captureContext.generateCaptureContext = function (accessToken) {
                            return originalGenerateCaptureContext.call(this, accessToken)
                                .then(function (response) {
                                    return self.normalizeCaptureContext(response);
                                });
                        };

                        captureContext.generateAndLoadCaptureContext('', '');
                    } catch (e) {
                        console.error('[ClicktoPay] SDK initialization failed:', e);
                    }
                },

                /**
                 * Map a capture-context response to camelCase top-level keys.
                 * The API returns PascalCase (Successful, ScriptUrl, Jwt, ...) while the
                 * SDK reads camelCase; lowercasing the first letter is idempotent for
                 * already-camelCase responses. Returns a new object (no mutation).
                 */
                normalizeCaptureContext: function (response) {
                    if (!response || typeof response !== 'object') {
                        return response;
                    }

                    return Object.keys(response).reduce(function (accumulator, key) {
                        var camelKey = key.charAt(0).toLowerCase() + key.slice(1);
                        accumulator[camelKey] = response[key];
                        return accumulator;
                    }, {});
                },

                /**
                 * Place order after CTP payment callback has populated transientToken.
                 */
                placeOrder: function (data, event) {
                    var self = this;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);

                        var placeOrder = placeOrderAction(
                            this.getData(),
                            this.redirectAfterPlaceOrder,
                            this.messageContainer
                        );

                        $.when(placeOrder).fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                                self.transientToken('');
                            }
                        ).done(this.afterPlaceOrder.bind(this));

                        return true;
                    }

                    return false;
                },

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.responseData;
                    checkoutCommon.redirectHandle(response);
                },

                selectPaymentMethod: function () {
                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    var self = this;
                    setTimeout(function () {
                        self.initializeCaptureContext();
                    }, 0);
                    return true;
                },

                validate: function () {
                    return this.transientToken().length > 0;
                },

                getData: function () {
                    return {
                        'method': this.item.method,
                        'po_number': null,
                        'additional_data': {
                            'transient_token': this.transientToken(),
                            'identifier': this.identifier()
                        }
                    };
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = (this.buckaroo && this.buckaroo.allowedCurrencies)
                        ? this.buckaroo.allowedCurrencies
                        : [];

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');
                    return text.replace('%s', this.baseCurrencyCode);
                }
            }
        );
    }
);
