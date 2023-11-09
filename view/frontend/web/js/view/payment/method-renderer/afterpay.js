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
        /*,
         'jquery/validate'*/
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


        /**
         *  constants for backend settings
         */
        var BUSINESS_METHOD_B2C = 1;
        var BUSINESS_METHOD_B2B = 2;
        var BUSINESS_METHOD_BOTH = 3;

        var PAYMENT_METHOD_ACCEPTGIRO = 1;
        var PAYMENT_METHOD_DIGIACCEPT = 2;


        /**
         * Validate IBAN and BIC number
         * This function check if the checksum if correct
         */
        function isValidIBAN($v)
        {
            $v = $v.replace(/^(.{4})(.*)$/,"$2$1"); //Move the first 4 chars from left to the right
            //Convert A-Z to 10-25
            $v = $v.replace(
                /[A-Z]/g,
                function ($e) {
                    return $e.charCodeAt(0) - 'A'.charCodeAt(0) + 10;
                }
            );
            var $sum = 0;
            var $ei = 1; //First exponent
            for (var $i = $v.length - 1; $i >= 0; $i--) {
                $sum += $ei * parseInt($v.charAt($i),10); //multiply the digit by it's exponent
                $ei = ($ei * 10) % 97; //compute next base 10 exponent  in modulus 97
            }
            return $sum % 97 == 1;
        }

        /**
         * Add validation methods
         */
        $.validator.addMethod(
            'IBAN',
            function (value) {
                var patternIBAN = new RegExp('^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}$');
                return (patternIBAN.test(value) && isValidIBAN(value));
            },
            $.mage.__('Enter Valid IBAN')
        );

        $.validator.addMethod('validateAge', function (value) {
            if (value && (value.length > 0)) {
                var dateReg = /^\d{2}[./-]\d{2}[./-]\d{4}$/;
                if (value.match(dateReg)) {
                    var birthday = +new Date(
                        value.substr(6, 4),
                        value.substr(3, 2) - 1,
                        value.substr(0, 2),
                        0,
                        0,
                        0
                    );
                    return ~~((Date.now() - birthday) / (31557600000)) >= 18;
                }
            }
            return false;
        },
        $.mage.__('You should be at least 18 years old.'));

        return Component.extend(
            {
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_afterpay',
                    telephoneNumber: null,
                    selectedBusiness: 1,
                    billingName: '',
                    country: '',
                    dateValidate: null,
                    cocNumber: null,
                    companyName:null,
                    bankAccountNumber: '',
                    termsUrl: 'https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsValidate: true,
                    value:"",
                    validationState: {
                        'buckaroo_magento2_afterpay_TermsCondition': true
                    }
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.afterpay.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.afterpay.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('afterpay'),

                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                dp: datePicker,
                businessMethod : window.checkoutConfig.payment.buckaroo.afterpay.businessMethod,
                paymentMethod : window.checkoutConfig.payment.buckaroo.afterpay.paymentMethod,
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
                            'telephoneNumber',
                            'selectedBusiness',
                            'dateValidate',
                            'cocNumber',
                            'companyName',
                            'bankAccountNumber',
                            'termsValidate',
                            'value',
                            'validationState'
                        ]
                    );

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                            quote.billingAddress().countryId == 'NL' &&
                            window.checkoutConfig.payment.buckaroo.afterpay.showFinancialWarning
                        },
                        this
                    );


                    this.billingName = ko.computed(
                        function () {
                            if (quote.billingAddress() !== null) {
                                return quote.billingAddress().firstname + " " + quote.billingAddress().lastname;
                            }
                        },
                        this
                    );

                    this.termsUrl =  ko.computed(
                        function () {
                            if (quote.billingAddress() !== null) {
                                let newUrl = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
                                if (this.paymentMethod == PAYMENT_METHOD_DIGIACCEPT) {
                                    newUrl = this.getDigiacceptUrl(quote.billingAddress().countryId);
                                }
                                return newUrl;
                            }
                        },
                        this
                    );

                    /**
                     * Check if TelephoneNumber is filled in. If not - show field
                     */
                    this.hasTelephoneNumber = ko.computed(
                        function () {
                            var telephone = quote.billingAddress() ? quote.billingAddress().telephone : null;
                            return telephone != '' && telephone != '-';
                        }
                    );

                    /**
                     * Repair IBAN value to uppercase
                     */
                    this.bankAccountNumber.extend({ uppercase: true });

                    this.dateValidate.subscribe(function () {
                        const dobId = 'buckaroo_magento2_afterpay_DoB';
                        const isValid = $(`#${dobId}`).valid();
                        let state = this.validationState();
                        state[dobId] = isValid;
                        this.validationState(state);
                    }, this);

                    this.buttoncheck = ko.computed(
                        function () {
                            const state = this.validationState();
                            const valid = this.getActiveValidationFields().map((field) => {
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
                    state[event.target.id] = isValid;
                    this.validationState(state);
                },

                getActiveValidationFields() {
                    let fields = [
                        'buckaroo_magento2_afterpay_TermsCondition',
                    ];
                    if (!this.hasTelephoneNumber()) {
                        fields.push('buckaroo_magento2_afterpay_Telephone')
                    }

                    if (this.businessMethod == BUSINESS_METHOD_B2C
                        || (
                            this.businessMethod == BUSINESS_METHOD_BOTH &&
                            this.selectedBusiness() == BUSINESS_METHOD_B2C
                        )
                    ) {
                        fields.push('buckaroo_magento2_afterpay_DoB')
                    if (this.paymentMethod == PAYMENT_METHOD_ACCEPTGIRO) {
                        fields.push('buckaroo_magento2_afterpay_IBAN')
                    }
                    } else {
                        fields = fields.concat(
                            [
                                'buckaroo_magento2_afterpay_COCNumber',
                                'buckaroo_magento2_afterpay_CompanyName'
                            ]
                        )
                    }

                    return fields;
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
                    response = $.parseJSON(response);
                    checkoutCommon.redirectHandle(response);
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);

                    return true;
                },

                /**
                 * Run validation function
                 */

                validate: function () {
                    return $('.' + this.getCode() + ' .payment-method-second-col form').valid();
                },

                getData: function () {
                    var business = this.businessMethod;

                    if (business == BUSINESS_METHOD_BOTH) {
                        business = this.selectedBusiness();
                    }

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone" : this.telephoneNumber(),
                            "customer_billingName" : this.billingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_iban": this.bankAccountNumber(),
                            "termsCondition" : this.termsValidate(),
                            "companyName" : this.companyName(),
                            "cOCNumber" : this.cocNumber(),
                            "selectedBusiness" : business
                        }
                    };
                },
                getDigiacceptUrl :function (country) {
                    var businessMethod = this.getBusinessMethod();
                    var url = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';

                    if (country === 'BE' && businessMethod == BUSINESS_METHOD_B2C) {
                        url = 'https://www.afterpay.be/be/footer/betalen-met-afterpay/betalingsvoorwaarden';
                    }

                    if (country === 'NL' && businessMethod == BUSINESS_METHOD_B2C) {
                        url = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
                    }

                    if (country === 'NL' && businessMethod == BUSINESS_METHOD_B2B) {
                        url = 'https://www.afterpay.nl/nl/algemeen/zakelijke-partners/betalingsvoorwaarden-zakelijk';
                    }

                    return url;
                },

                getBusinessMethod : function () {
                    var businessMethod = BUSINESS_METHOD_B2C;

                    if (this.businessMethod == BUSINESS_METHOD_B2B
                        || (this.businessMethod == BUSINESS_METHOD_BOTH && this.selectedBusiness() == BUSINESS_METHOD_B2B)
                    ) {
                        businessMethod = BUSINESS_METHOD_B2B;
                    }

                    return businessMethod;
                }
            }
        );
    }
);
