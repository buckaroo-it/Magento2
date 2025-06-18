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
        'buckaroo/applepay/pay',
        'buckaroo/checkout/common',
        'BuckarooSdk',
        'mage/url'
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
        applepayPay,
        checkoutCommon,
        BuckarooSdk,
        url
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_applepay'
                },
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,
                subtext: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay.subtext,
                submit: false,
                redirectAfterPlaceOrder: false,

                initObservable: function () {
                    this._super().observe([]);

                    // Check integration mode and use appropriate template
                    if (this.getIntegrationMode() === '1') {
                        this.template = 'Buckaroo_Magento2/payment/buckaroo_magento2_applepay_redirect';
                        this.redirectAfterPlaceOrder = false;
                    } else {
                        // Inline mode - initialize Apple Pay components
                        this.initializeInlineMode();
                    }

                    return this;
                },

                /**
                 * Initialize inline mode with Apple Pay SDK
                 */
                initializeInlineMode: function () {
                    try {
                        applepayPay.canShowApplePay();

                        applepayPay.transactionResult.subscribe(
                            function () {
                                this.submit = true;
                                this.placeOrder(null, null);
                            }.bind(this)
                        );

                        quote.totals.subscribe(
                            function () {
                                if (applepayPay.canShowApplePay()) {
                                    applepayPay.updateOptions();
                                }
                            }.bind(this)
                        );

                        $(window).on('hashchange', function () {
                            var hashString = window.location.hash.replace('#', '');

                            if (hashString === 'payment' && applepayPay.canShowApplePay()) {
                                applepayPay.updateOptions();
                            }
                        }.bind(this));
                    } catch (e) {
                        console.error('Apple Pay inline mode initialization failed:', e);
                    }
                },

                /**
                 * Check if payment method can be shown
                 */
                canShowPaymentMethod: function () {
                    if (this.getIntegrationMode() === '1') {
                        // For redirect mode, always show if method is enabled
                        return true;
                    } else {
                        // For inline mode, check Apple Pay support
                        return applepayPay.canShowMethod();
                    }
                },

                /**
                 * Get integration mode from config
                 */
                getIntegrationMode: function () {
                    return window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay.integrationMode || '0';
                },

                /**
                 * Get subtext styling
                 */
                getSubtextStyle: function () {
                    var config = this.buckaroo;
                    if (config === undefined) {
                        return {};
                    }
                    var subtextColor = config.subtext_color || '#757575';
                    var subtextStyle = config.subtext_style || 'regular';

                    var style = { color: subtextColor };
                    if (subtextStyle === 'bold') {
                        style.fontWeight = 'bold';
                    }

                    if (subtextStyle === 'italic') {
                        style.fontStyle = 'italic';
                    }
                    return style;
                },

                /**
                 * Place order with mode-specific handling
                 */
                placeOrder: function (data, event) {
                    var self = this,
                        placeOrder;

                    if (this.getIntegrationMode() === '1') {
                        // Redirect mode - simple place order without Apple Pay SDK
                        return this.placeRedirectOrder(data, event);
                    } else {
                        // Inline mode - Apple Pay SDK handling
                        return this.placeInlineOrder(data, event);
                    }
                },

                /**
                 * Handle redirect mode order placement
                 */
                placeRedirectOrder: function (data, event) {
                    var self = this;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);

                        var placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

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
                 * Handle inline mode order placement
                 */
                placeInlineOrder: function (data, event) {
                    var self = this;


                    if (applepayPay.isOsc()) {
                        var validationResult = additionalValidators.validate();
                        if (!validationResult) {
                            return false;
                        }
                    }

                    if (!this.submit) {
                        var child = document.querySelector('.apple-pay-button');
                        if (child) {
                            child.click();
                        }
                        return false;
                    }

                    this.submit = false;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);
                        var placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder, this.messageContainer);

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
                 * After place order handler with mode-specific logic
                 */
                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;

                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse Buckaroo response:', e);
                            this.redirectToSuccess();
                            return;
                        }
                    }

                    // Handle redirect if RequiredAction is present
                    if (response && response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        window.location.replace(response.RequiredAction.RedirectURL);
                    } else {
                        this.redirectToSuccess();
                    }
                },

                /**
                 * Redirect to success page
                 */
                redirectToSuccess: function () {
                    window.location.replace(url.build('checkout/onepage/success/'));
                },

                /**
                 * Select payment method
                 */
                selectPaymentMethod: function () {
                    if (this.getIntegrationMode() === '0') {
                        applepayPay.devLog('==========applepaydebug/71');
                    }
                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);

                    return true;
                },

                /**
                 * Show Apple Pay button (inline mode only)
                 */
                showPayButton: function () {
                    if (this.getIntegrationMode() === '0') {
                        applepayPay.setIsOnCheckout(true);
                        applepayPay.setQuote(quote);
                        applepayPay.showPayButton();
                    }
                },

                /**
                 * Check if should pay with base currency
                 */
                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay.allowedCurrencies;
                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                /**
                 * Get pay with base currency text
                 */
                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');
                    return text.replace('%s', this.baseCurrencyCode);
                },

                /**
                 * Get payment data
                 */
                getData: function () {
                    if (this.getIntegrationMode() === '1') {
                        // Redirect mode - minimal data
                        return {
                            "method": this.item.method,
                            "po_number": null,
                            "additional_data": {}
                        };
                    } else {
                        // Inline mode - include Apple Pay transaction data
                        var transactionResult = applepayPay.transactionResult();
                        var transactionData = this.formatTransactionResponse(transactionResult);

                        return {
                            "method": this.item.method,
                            "po_number": null,
                            "additional_data": {
                                "applepayTransaction": transactionData,
                                "billingContact": transactionResult && transactionResult.billingContact ?
                                    JSON.stringify(transactionResult.billingContact) : ''
                            }
                        };
                    }
                },

                /**
                 * Format transaction response for inline mode
                 */
                formatTransactionResponse: function (transactionResult) {
                    if (!transactionResult || this.getIntegrationMode() === '1') {
                        return '';
                    }

                    return JSON.stringify({
                        token: {
                            paymentData: transactionResult.token.paymentData,
                            paymentMethod: transactionResult.token.paymentMethod,
                            transactionIdentifier: transactionResult.token.transactionIdentifier
                        }
                    });
                }
            }
        );
    }
);
