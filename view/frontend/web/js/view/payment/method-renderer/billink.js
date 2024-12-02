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
        'buckaroo/checkout/datepicker',
        'Magento_Ui/js/lib/knockout/bindings/datepicker'
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

        $.validator.addMethod('validateAge', function (value) {
                if (value && (value.length > 0)) {
                    var dateReg = /^\d{2}[./-]\d{2}[./-]\d{4}$/;
                    if (value.match(dateReg)) {
                        var birthday = +new Date(
                            value.substr(6, 4),
                            value.substr(3, 2) - 1,
                            value.substr(0, 2),
                            0, 0, 0
                        );
                        return ~~((Date.now() - birthday) / (31557600000)) >= 18;
                    }
                }
                return false;
            },
            $.mage.__('You should be at least 18 years old.')
        );

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

            value = value.replace(/^\+|(00)/, '');
            value = value.replace(/\(0\)|\s|-/g, '');

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
        $.validator.addMethod('phoneValidation', validPhone ,
            $.mage.__('Phone number should be correct.')
        );

        return Component.extend(
            {
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_billink',
                    selectedGender: null,
                    billingName: '',
                    date: '',
                    phone: '',
                    cocNumber:'',
                    vatNumber: '',
                    dob:null,
                    tos: true,
                    showPhone: false,
                    showFrenchTosValue: null,
                    validationState:{
                        'buckaroo_magento2_billink_TermsCondition': true
                    },
                    value: ""
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.billink.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.billink.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('billink'),
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                currentCustomerAddressId : null,
                genderList: window.checkoutConfig.payment.buckaroo.billink.genderList,
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
                    this._super().observe(
                        [
                            'selectedGender',
                            'phone',
                            'cocNumber',
                            'vatNumber',
                            'tos',
                            'dob',
                            'showFrenchTosValue',
                            'validationState',
                            'value'
                        ]
                    );

                    this.isB2B = ko.computed(
                        function () {
                            const billingAddress = quote.billingAddress();
                            return billingAddress && billingAddress.company;
                        },
                        this
                    );

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                                quote.billingAddress().countryId == 'NL' &&
                                window.checkoutConfig.payment.buckaroo.billink.showFinancialWarning
                        },
                        this
                    );
                    this.billingName = ko.computed(
                        function () {
                            if(this.isB2B && quote.billingAddress() !== null) {
                                return quote.billingAddress().company;
                            }
                            if(quote.billingAddress() !== null) {
                                return quote.billingAddress().firstname + " " + quote.billingAddress().lastname;
                            }
                        },
                        this
                    );

                    this.showFrenchTos = ko.computed(
                        function () {
                            return  quote.billingAddress() !== null && quote.billingAddress().countryId == 'BE'
                        },
                        this
                    );

                    this.showPhone = ko.computed(
                        function () {
                            return (
                                quote.billingAddress() === null ||
                                !validPhone(quote.billingAddress().telephone)
                            ) && !this.isB2B;
                        },
                        this
                    );

                    this.dob.subscribe(function() {
                        const dobId = 'buckaroo_magento2_billink_DoB';
                        const isValid = $(`#${dobId}`).valid();
                        let state = this.validationState();
                        state[dobId] = isValid;
                        this.validationState(state);
                    }, this);

                    this.buttoncheck = ko.computed(
                        function () {
                            const state = this.validationState();
                            const valid = this.getActiveValidationFields().map((field) => {
                                if(state[field] !== undefined) {
                                    return state[field];
                                }
                                return false;
                            }).reduce(
                                function(prev, cur) {
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
                    state[event.target.id] = isValid;
                    this.validationState(state);
                },

                getActiveValidationFields() {
                    let fields = [
                        'buckaroo_magento2_billink_TermsCondition',
                    ];
                    if(this.showPhone()) {
                        fields.push('buckaroo_magento2_billink_Telephone')
                    }

                    if(this.isB2B) {
                        fields.push('buckaroo_magento2_billink_chamberOfCommerce')
                    } else {
                        fields = fields.concat([
                            'buckaroo_magento2_billink_DoB',
                            'buckaroo_magento2_bilink_genderSelect'
                        ]);
                    }

                    return fields;
                },
                validate: function () {
                    return $('.' + this.getCode() + ' .payment-method-second-col form').valid();
                },

                /**
                 * Place order.
                 *
                 * @todo To override the script used for placeOrderAction, we need to override the placeOrder method
                 *          on our parent class (Magento_Checkout/js/view/payment/default) so we can
                 *
                 *          placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own
                 *          version (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
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
                    checkoutCommon.redirectHandle(response);
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);

                    return true;
                },

                getData: function () {
                    let phone = this.phone();
                    if(!this.showPhone() && quote.billingAddress() !== null) {
                        phone = quote.billingAddress().telephone;
                    }

                    let additionalData = {
                        "customer_telephone": phone,
                        "customer_gender": this.selectedGender(),
                        "customer_DoB": this.dob(),
                        "termsCondition": this.tos(),
                    };

                    if (this.isB2B) {
                        additionalData["customer_chamberOfCommerce"] = this.cocNumber();
                        additionalData["customer_VATNumber"] = this.vatNumber();
                    }

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": additionalData
                    };
                }

            }
        );
    }
);
