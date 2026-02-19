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
        'Magento_Checkout/js/model/quote',
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
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
        checkoutData,
        selectPaymentMethodAction,
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
                subtext: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_googlepay.subtext,
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

                    console.log('[GooglePay Client] placeOrder called, submit:', this.submit);

                    if (googlepayPay.isOsc()) {
                        var validationResult = additionalValidators.validate();
                        if (!validationResult) {
                            return false;
                        }
                    }

                    if (!this.submit) {
                        console.log('[GooglePay Client] Submit is false, trying to trigger Google Pay button');
                        // Trigger Google Pay button click
                        var button = document.querySelector('#google-pay-button-container button');
                        console.log('[GooglePay Client] Google Pay button found:', button);
                        if (button) {
                            console.log('[GooglePay Client] Clicking Google Pay button');
                            button.click();
                        } else {
                            console.error('[GooglePay Client] Google Pay button not found in DOM!');
                        }
                        return false;
                    }

                    console.log('[GooglePay Client] Submit is true, proceeding with order placement');

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
                 * Select payment method
                 */
                selectPaymentMethod: function () {
                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);

                    return true;
                },

                /**
                 * Show Google Pay button
                 */
                showPayButton: function () {
                    console.log('[GooglePay Client] showPayButton called');
                    googlepayPay.setIsOnCheckout(true);
                    googlepayPay.setQuote(quote);
                    console.log('[GooglePay Client] Calling googlepayPay.showPayButton()');
                    googlepayPay.showPayButton();
                    console.log('[GooglePay Client] googlepayPay.showPayButton() completed');
                },

                /**
                 * Check if payment should be done in base currency
                 */
                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_googlepay.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                /**
                 * Get the text for paying with the base currency
                 */
                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                },

                /**
                 * Get payment data
                 */
                getData: function () {
                    var transactionResult = googlepayPay.transactionResult();
                    console.log('[GooglePay Client] getData - transactionResult:', transactionResult);
                    var paymentData = this.formatPaymentData(transactionResult);
                    console.log('[GooglePay Client] getData - formatted paymentData:', paymentData);
                    console.log('[GooglePay Client] getData - paymentData is null?', paymentData === null);
                    console.log('[GooglePay Client] getData - paymentData is undefined?', typeof paymentData === 'undefined');

                    var result = {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "googlepayPaymentData": paymentData
                        }
                    };

                    console.log('[GooglePay Client] getData - final result:', result);
                    return result;
                },

                /**
                 * Format Google Pay payment data for backend
                 */
                formatPaymentData: function (paymentData) {
                    if (null === paymentData || 'undefined' === typeof paymentData || !paymentData) {
                        if (window.console && window.console.error) {
                            console.error('[Buckaroo Google Pay Client] formatPaymentData: Invalid payment data', paymentData);
                        }
                        return null;
                    }

                    try {
                        // Extract payment token from Google Pay response
                        if (!paymentData.paymentMethodData || !paymentData.paymentMethodData.tokenizationData) {
                            if (window.console && window.console.error) {
                                console.error('[Buckaroo Google Pay Client] Missing tokenization data', paymentData);
                            }
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
                        if (window.console && window.console.error) {
                            console.error('[Buckaroo Google Pay Client] Error formatting payment data:', error);
                        }
                        return null;
                    }
                }
            }
        );
    }
);
