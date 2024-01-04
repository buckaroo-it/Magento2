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
        'buckaroo/checkout/common',
        'buckaroo/checkout/datepicker'
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
        checkoutCommon,
        datePicker
    ) {
        'use strict';

        const validPhone = function (value) {
            if (quote.billingAddress() === null) {
                return false;
            }
            let countryId = quote.billingAddress().countryId;
            var lengths = {
                'NL': {
                    min: 10,
                    max: 12
                },
                'BE': {
                    min: 9,
                    max: 12
                },
                'DE': {
                    min: 11,
                    max: 14
                }
            };
            if (!value) {
                return false;
            }

            value = value.replace(/^(\+|00)/, '');
            value = value.replace(/(\(0\)|\s|-)/g, '');

            if (value.match(/\+/)) {
                return false;
            }

            if (value.match(/[^0-9]/)) {
                return false;
            }

            if (lengths.hasOwnProperty(countryId)) {
                if (lengths[countryId].min && (value.length < lengths[countryId].min)) {
                    return false;
                }
                if (lengths[countryId].max && (value.length > lengths[countryId].max)) {
                    return false;
                }
            }

            return true;
        };
        $.validator.addMethod(
            'in3phoneValidation',
            validPhone ,
            $.mage.__('Phone number should be correct.')
        );

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_capayablein3',
                    billingName : null,
                    dateValidate : '',
                    value: '',
                    phone: null,
                    validationState : {},
                },
                redirectAfterPlaceOrder: false,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.capayablein3.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.capayablein3.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('capayablein3'),
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                logo:  window.checkoutConfig.payment.buckaroo.capayablein3.logo,
                dp: datePicker,

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
                    this._super().observe([
                        'billingName',
                        'dateValidate',
                        'value',
                        'phone',
                        'validationState'
                    ]);

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                            quote.billingAddress().countryId == 'NL' &&
                            window.checkoutConfig.payment.buckaroo.capayablein3.showFinancialWarning
                        },
                        this
                    );

                    this.showPhone = ko.computed(
                        function () {
                            return quote.billingAddress() === undefined ||
                            quote.billingAddress() === null ||
                            validPhone(quote.billingAddress().telephone) === false
                        },
                        this
                    );

                    this.billingName = ko.computed(
                        function () {
                            if (quote.billingAddress() !== null) {
                                return quote.billingAddress().firstname + " " + quote.billingAddress().lastname
                            }
                            return '';
                        },
                        this
                    );


                    /** Check used to see form is valid **/
                    this.buttoncheck = ko.computed(
                        function () {
                            const state = this.validationState();
                            const valid =this.getActiveFields().map((field) => {
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

                    this.dateValidate.subscribe(function () {
                        const id = 'buckaroo_magento2_capayablein3_DoB';
                        const isValid = $(`#${id}`).valid();
                        let state = this.validationState();
                        state[id] = isValid;
                        this.validationState(state);
                    }, this);

                    return this;
                },
                getActiveFields() {
                    let fields = [
                        'buckaroo_magento2_capayablein3_DoB',
                    ];
                    if (this.showPhone()) {
                        fields.push('buckaroo_magento2_capayablein3_Telephone');
                    }
                    return fields;
                },

                validateField(data, event) {
                    const isValid = $(event.target).valid();
                    let state = this.validationState();
                    state[event.target.id] = isValid;
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

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.capayablein3.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                },

                validate: function () {
                    return $('.' + this.getCode() + ' .payment-method-second-col form').valid();
                },

                getData : function () {
                    let telephone = quote.billingAddress().telephone;
                    if (validPhone(this.phone())) {
                        telephone = this.phone();
                    }
                    return {
                        "method" : this.item.method,
                        "additional_data": {
                            "customer_billingName" : this.billingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_telephone" : telephone
                        }
                    };
                }
            }
        );
    }
);
