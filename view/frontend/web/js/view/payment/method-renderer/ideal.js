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
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_ideal',
                    selectedBank: '',
                    validationState: {}
                },
                bankTypes: window.checkoutConfig.payment.buckaroo.ideal.banks,
                redirectAfterPlaceOrder: false,
                selectionType:  window.checkoutConfig.payment.buckaroo.ideal.selectionType,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.ideal.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.ideal.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('ideal'),
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                showIssuers :  window.checkoutConfig.payment.buckaroo.ideal.showIssuers,

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
                    this._super().observe(['selectedBank', 'validationState']);

                    /** Check used to see form is valid **/
                    this.buttoncheck = ko.computed(
                        function () {
                            if (this.showIssuers === false) {
                                return true;
                            }
                            const state = this.validationState();
                            const valid = [
                                'issuer',
                            ].map((field) => {
                                if (state[field] !== undefined) {
                                    return state[field];
                                }
                                return false;
                            }).reduce(
                                function (prev, cur) {
                                    return prev && cur
                                },
                                true
                            )
                            return valid;
                        },
                        this
                    );



                    return this;
                },

                validateField(data, event) {
                    const isValid = $(event.target).valid();
                    let state = this.validationState();
                    state['issuer'] = isValid;
                    this.validationState(state);
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
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "issuer" : this.selectedBank()
                        }
                    };
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.ideal.allowedCurrencies;

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








