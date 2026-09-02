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
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'ko',
        'buckaroo/googlepay/pay',
        'BuckarooSdk'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        quote,
        ko,
        googlepayPay
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_googlepay_client'
                },
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,
                submit: false,

                /**
                 * Initialize observable
                 */
                initObservable: function () {
                    this._super().observe([]);

                    // Subscribe to transaction result changes
                    googlepayPay.transactionResult.subscribe(
                        function () {
                            this.submit = true;
                            this.placeOrder(null, null);
                        }.bind(this)
                    );

                    // Subscribe to quote totals changes to update Google Pay options
                    quote.totals.subscribe(
                        function () {
                            if (googlepayPay.canShowMethod()) {
                                googlepayPay.updateOptions();
                            }
                        }.bind(this)
                    );

                    // Update options when navigating to payment step
                    $(window).on('hashchange', function () {
                        var hashString = window.location.hash.replace('#', '');

                        if (hashString === 'payment' && googlepayPay.canShowMethod()) {
                            googlepayPay.updateOptions();
                        }
                    }.bind(this));

                    return this;
                },

                /**
                 * Check if payment method can be shown
                 */
                canShowPaymentMethod: ko.computed(function () {
                    return googlepayPay.canShowMethod();
                }),

                /**
                 * Place order
                 */
                placeOrder: function (data, event) {
                    var self = this,
                        placeOrder;

                    if (googlepayPay.isOsc()) {
                        var validationResult = additionalValidators.validate();
                        if (!validationResult) {
                            return false;
                        }
                    }

                    if (!this.submit) {
                        // Trigger Google Pay button click
                        var button = document.querySelector('#google-pay-button-container button');
                        if (button) {
                            button.click();
                        } else {
                        }
                        return false;
                    }

                    this.submit = false;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);
                        placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder, this.messageContainer);

                        $.when(placeOrder).fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(this.afterPlaceOrder.bind(this));

                        return true;
                    }

                    return false;
                },

                /**
                 * After place order
                 */
                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.responseData;

                    if (response && response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        window.location.replace(response.RequiredAction.RedirectURL);
                    }
                },

                /**
                 * Show Google Pay button
                 */
                showPayButton: function () {
                    googlepayPay.setIsOnCheckout(true);
                    googlepayPay.setQuote(quote);
                    googlepayPay.showPayButton();
                },

                /**
                 * Get payment data
                 */
                getData: function () {
                    var transactionResult = googlepayPay.transactionResult();
                    var paymentData = this.formatPaymentData(transactionResult);

                    var result = {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "googlepayPaymentData": paymentData
                        }
                    };

                    return result;
                },

                /**
                 * Format Google Pay payment data for backend
                 */
                formatPaymentData: function (paymentData) {
                    if (null === paymentData || 'undefined' === typeof paymentData || !paymentData) {
                        return null;
                    }

                    try {
                        // Extract payment token from Google Pay response
                        if (!paymentData.paymentMethodData || !paymentData.paymentMethodData.tokenizationData) {
                            return null;
                        }

                        var tokenizationData = paymentData.paymentMethodData.tokenizationData;

                        // Parse the token (it's a JSON string from Google)
                        var token = typeof tokenizationData.token === 'string'
                            ? JSON.parse(tokenizationData.token)
                            : tokenizationData.token;

                        return JSON.stringify({
                            paymentMethodData: {
                                type: paymentData.paymentMethodData.type,
                                description: paymentData.paymentMethodData.description,
                                info: paymentData.paymentMethodData.info,
                                tokenizationData: {
                                    type: tokenizationData.type,
                                    token: token
                                }
                            },
                            shippingAddress: paymentData.shippingAddress || null,
                            email: paymentData.email || null
                        });
                    } catch (error) {
                        return null;
                    }
                }
            }
        );
    }
);
