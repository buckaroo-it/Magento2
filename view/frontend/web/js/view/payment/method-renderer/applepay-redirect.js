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
        'mage/translate',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'buckaroo/checkout/common',
        'buckaroo/applepay/pay',
        'BuckarooSdk'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        $t,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon,
        applepayPay
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_applepay_redirect'
                },
                redirectAfterPlaceOrder: false,
                paymentFeeLabel: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay.paymentFeeLabel,
                subtext: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay.subtext,
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,

                /**
                 * @override
                 */
                initObservable: function () {
                    this._super();
                    
                    // Initialize Apple Pay availability check for redirect mode
                    applepayPay.canShowApplePay();

                    return this;
                },

                canShowPaymentMethod: ko.computed(function () {
                    return applepayPay.canShowMethod();
                }),

                /**
                 * Handle redirect mode order placement
                 */
                placeOrder: function (data, event) {
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
                 * After place order callback
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
                 * @override
                 */
                selectPaymentMethod: function () {
                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                /**
                 * @override
                 */
                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                        }
                    };
                },

                /**
                 * Check if payment should be done in base currency.
                 * @returns {boolean}
                 */
                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay.allowedCurrencies;
                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                /**
                 * Get the text for paying with the base currency.
                 * @returns {string}
                 */
                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');
                    return text.replace('%s', this.baseCurrencyCode);
                }
            }
        );
    }
);
