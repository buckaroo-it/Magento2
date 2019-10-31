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
        'TIG_Buckaroo/js/action/place-order',
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'TIG_Buckaroo/payment/tig_buckaroo_creditcard'
                },
                creditcards: [],
                redirectAfterPlaceOrder: false,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.creditcard.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,

                /**
                 * @override
                 */
                initialize : function (options) {
                    if (checkoutData.getSelectedPaymentMethod() == options.index) {
                        window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                    }

                    return this._super(options);
                },

                initObservable: function () {
                    this._super().observe(['selectedCard', 'creditcards']);

                    this.creditcards = ko.observableArray(window.checkoutConfig.payment.buckaroo.creditcard.cards);

                    /**
                     * observe radio buttons
                     * check if selected
                     */
                    var self = this;
                    this.setSelectedCard = function (value) {
                        self.selectedCard(value);
                        return true;
                    };

                    /**
                     * Check if the required fields are filled. If so: enable place order button (true) | ifnot: disable place order button (false)
                     */
                    this.buttoncheck = ko.computed(
                        function () {
                            return this.selectedCard();
                        },
                        this
                    );

                    return this;
                },

                /**
                 * Place order.
                 *
                 * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
                 * (TIG_Buckaroo/js/action/place-order) to prevent redirect and handle the response.
                 */
                placeOrder: function (data, event) {
                    var self = this,
                        placeOrder;

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

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;
                    response = $.parseJSON(response);
                    if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        window.location.replace(response.RequiredAction.RedirectURL);
                    }
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                getData: function () {
                    var selectedCardCode = null;
                    if (this.selectedCard()) {
                        selectedCardCode = this.selectedCard().code;
                    }

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "card_type" : selectedCardCode
                        }
                    };
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.creditcard.allowedCurrencies;

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








