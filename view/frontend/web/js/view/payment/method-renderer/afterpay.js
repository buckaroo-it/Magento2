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
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/quote',
        'ko',
        'buckaroo/checkout/datepicker',
        'Magento_Ui/js/lib/knockout/bindings/datepicker'
    ],
    function (
        $,
        Component,
        quote,
        ko,
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
        function isValidIBAN($v) {
            $v = $v.replace(/^(.{4})(.*)$/, "$2$1"); //Move the first 4 chars from left to the right
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
                $sum += $ei * parseInt($v.charAt($i), 10); //multiply the digit by it's exponent
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
                var patternIBAN = new RegExp('^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]{1,16})$');
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
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_afterpay',
                    telephoneNumber: null,
                    selectedBusiness: 1,
                    country: '',
                    dateValidate: null,
                    cocNumber: null,
                    companyName: null,
                    bankAccountNumber: '',
                    termsUrl: 'https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsSelected: true,
                    value:"",
                },
                redirectAfterPlaceOrder : true,
                dp: datePicker,

                initObservable: function () {
                    this._super().observe(
                        [
                            'telephoneNumber',
                            'selectedBusiness',
                            'dateValidate',
                            'cocNumber',
                            'companyName',
                            'bankAccountNumber',
                            'termsSelected',
                            'value'
                        ]
                    );

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                            quote.billingAddress().countryId == 'NL' &&
                            this.buckaroo.showFinancialWarning
                        },
                        this
                    );


                    this.termsUrl = ko.computed(
                        function () {
                            if (quote.billingAddress() !== null) {
                                return this.getTos(quote.billingAddress().countryId);
                            }
                        },
                        this
                    );


                    this.hasTelephoneNumber = ko.computed(
                        function () {
                            var telephone = quote.billingAddress() ? quote.billingAddress().telephone : null;
                            return telephone != '' && telephone != '-';
                        }
                    );

                    this.showFrenchTos = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                                quote.billingAddress().countryId === 'BE'
                        },
                        this
                    );

                    /**
                     * Repair IBAN value to uppercase
                     */
                    this.bankAccountNumber.extend({uppercase: true});

                    return this;
                },



                getData: function () {
                    var business = this.buckaroo.businessMethod;

                    if (business == BUSINESS_METHOD_BOTH) {
                        business = this.selectedBusiness();
                    }

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone": this.telephoneNumber(),
                            "customer_DoB": this.dateValidate(),
                            "customer_iban": this.bankAccountNumber(),
                            "termsCondition" : this.termsSelected(),
                            "companyName" : this.companyName(),
                            "cOCNumber" : this.cocNumber(),
                            "selectedBusiness" : business
                        }
                    };
                },
                getTos: function (country) {
                    const businessMethod = this.getBusinessMethod();
                    let lang = 'nl_nl';
                    let url = 'https://documents.riverty.com/terms_conditions/payment_methods/invoice';
                    const cc = country.toLowerCase()

                    if (businessMethod == BUSINESS_METHOD_B2C || this.buckaroo.paymentMethod == PAYMENT_METHOD_DIGIACCEPT) {
                        if (country === 'BE') {
                            lang = 'be_nl';
                        }

                        if (['NL','DE'].indexOf(country) !== -1) {
                            lang = `${cc}_${cc}`;
                        }

                        if (['AT', 'CH'].indexOf(country) !== -1) {
                            const cc = country.toLowerCase()
                            lang = `${cc}_de`;
                        }

                        if (['DK', 'FI', 'SE', 'NO'].indexOf(country) !== -1) {
                            const cc = country.toLowerCase()
                            lang = `${cc}_en`;
                        }
                    }

                    if (businessMethod == BUSINESS_METHOD_B2B && ['NL', 'DE', 'AT', 'CH'].indexOf(country) !== -1) {
                        url = 'https://documents.riverty.com/terms_conditions/payment_methods/b2b_invoice';
                        if (['NL', 'DE'].indexOf(country) !== -1) {
                            lang = `${cc}_${cc}`;
                        }

                        if (['AT', 'CH'].indexOf(country) !== -1) {
                            lang = `${cc}_de`;
                        }
                    }

                    return `${url}/${lang}/`;
                },

                getFrenchTos: function () {
                    return $.mage
                     .__('(Or click here for the French translation: <a target="_blank" href="%s">terms and conditions</a>.)')
                     .replace('%s', 'https://documents.riverty.com/terms_conditions/payment_methods/invoice/be_fr/');
                 },

                getBusinessMethod : function () {
                    var businessMethod = BUSINESS_METHOD_B2C;

                    if (this.buckaroo.businessMethod == BUSINESS_METHOD_B2B
                        || (this.buckaroo.businessMethod == BUSINESS_METHOD_BOTH && this.selectedBusiness() == BUSINESS_METHOD_B2B)
                    ) {
                        businessMethod = BUSINESS_METHOD_B2B;
                    }

                    return businessMethod;
                }
            }
        );
    }
);
