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
        'buckaroo/checkout/common'
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
        checkoutCommon
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_creditcard'
                },
                creditcards: [],
                redirectAfterPlaceOrder: false,
                creditcardIssuer: null,
                selectedBank: null,
                selectedBankDropDown: null,
                selectionType: null,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.creditcard.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.creditcard.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('creditcard'),
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                paymentFlow : window.checkoutConfig.payment.buckaroo.creditcard.paymentFlow,

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
                    this._super().observe(['selectedCard', 'creditcards', 'selectionType']);

                    this.creditcards = ko.observableArray(window.checkoutConfig.payment.buckaroo.creditcard.cards);

                    this.selectionType  = window.checkoutConfig.payment.buckaroo.creditcard.selectionType;

                    /**
                     * observe radio buttons
                     * check if selected
                     */
                    var self = this;
                    this.setSelectedCard = function (value) {
                        self.selectedCard(value);
                        self.selectPaymentMethod();
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

                    $('.iosc-place-order-button').on('click', function (e) {
                        if (self.selectedCard() == null) {
                            self.messageContainer.addErrorMessage({'message': $t('You need select a card')});
                        }
                    });

                    return this;
                },

                setSelectedBankDropDown: function () {
                    var el = document.getElementById("buckaroo_magento2_creditcard_issuer");
                    this.selectedCard(el.options[el.selectedIndex].value);
                    this.selectPaymentMethod();
                    return true;
                },

                /**
                 * Place order.
                 *
                 * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
                 * (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
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
                    checkoutCommon.redirectHandle(response);
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


                    if (this.creditcardIssuer) {
                        selectedCardCode = this.creditcardIssuer;
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








