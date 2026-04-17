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
        'buckaroo/checkout/datepicker-enhanced',
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

        $.validator.addMethod('validate-date-flexible', function (value) {
            if (!value) return false;
            var dateReg = /^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4}$/;
            if (value.match(dateReg)) {
                var parts = value.split(/[\/\-\.]/);
                var day = parseInt(parts[0], 10);
                var month = parseInt(parts[1], 10);
                var year = parseInt(parts[2], 10);
                if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > new Date().getFullYear()) {
                    return false;
                }
                var date = new Date(year, month - 1, day);
                return date.getDate() === day && date.getMonth() === (month - 1) && date.getFullYear() === year;
            }
            return false;
        }, $.mage.__('Please use this date format: dd/mm/yyyy, dd-mm-yyyy or dd.mm.yyyy.'));

        $.validator.addMethod('validateAge', function (value) {
            if (value && (value.length > 0)) {
                var dateReg = /^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4}$/;
                if (value.match(dateReg)) {
                    var parts = value.split(/[\/\-\.]/);
                    var day = parseInt(parts[0], 10);
                    var month = parseInt(parts[1], 10);
                    var year = parseInt(parts[2], 10);

                    if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > new Date().getFullYear()) {
                        return false;
                    }

                    var birthday = new Date(year, month - 1, day, 0, 0, 0);

                    if (birthday.getDate() !== day || birthday.getMonth() !== (month - 1) || birthday.getFullYear() !== year) {
                        return false;
                    }

                    return ~~((Date.now() - birthday) / (31557600000)) >= 18;
                }
            }
            return false;
        },
        $.mage.__('You should be at least 18 years old.'));

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
            'phoneValidation',
            validPhone ,
            $.mage.__('Phone number should be correct.')
        );


        return Component.extend(
            {
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_billink',
                    selectedGender: null,
                    date: '',
                    phone: '',
                    cocNumber:'',
                    vatNumber: '',
                    dob:null,
                    showPhone: false,
                    showB2B: false,
                    showFrenchTosValue: null,
                    value: ""
                },
                redirectAfterPlaceOrder : true,
                dp: datePicker,

                filterDobInput: function (data, event) {
                    var input = event.target;
                    var filtered = input.value.replace(/[^\d\/\-\.]/g, '');
                    if (input.value !== filtered) {
                        var pos = input.selectionStart - (input.value.length - filtered.length);
                        input.value = filtered;
                        input.setSelectionRange(pos, pos);
                    }
                    return true;
                },

                initObservable: function () {
                    this._super().observe(
                        [
                            'selectedGender',
                            'phone',
                            'cocNumber',
                            'vatNumber',
                            'dob',
                            'showFrenchTosValue',
                            'value'
                        ]
                    );
                    this.billingName = ko.computed(
                        function () {
                            if (quote.billingAddress() !== null) {
                                if (quote.billingAddress().company && quote.billingAddress().company.trim().length > 0) {
                                    return quote.billingAddress().company;
                                }
                                return quote.billingAddress().firstname + " " + quote.billingAddress().lastname;
                            }
                            return '';
                        },
                        this
                    );

                    this.showPhone = ko.computed(
                        function () {
                            return (
                                quote.billingAddress() === null ||
                                !validPhone(quote.billingAddress().telephone)
                            );
                        },
                        this
                    );

                    this.isPostNLPickup = ko.computed(
                        function () {
                            let shippingMethod = quote.shippingMethod();
                            if (!shippingMethod || !shippingMethod.method_code) {
                                return false;
                            }

                            let methodCode = shippingMethod.method_code.toLowerCase();
                            return (methodCode.indexOf('postnl') !== -1 || methodCode.indexOf('post_nl') !== -1) &&
                                   (methodCode.indexOf('pickup') !== -1 || methodCode.indexOf('pakjegemak') !== -1);
                        },
                        this
                    );

                    this.showB2B = ko.computed(
                        function () {
                            let shipping = quote.shippingAddress();
                            let billing = quote.billingAddress();
                            let isPostNLPickup = this.isPostNLPickup();

                            if (isPostNLPickup) {
                                return this.buckaroo.is_b2b && (
                                    billing && billing.company && billing.company.trim().length > 0
                                );
                            }

                            return this.buckaroo.is_b2b && (
                                (shipping && shipping.company && shipping.company.trim().length > 0) ||
                                (billing && billing.company && billing.company.trim().length > 0)
                            )
                        },
                        this
                    );

                    return this;
                },

                getDobPlaceholder: function () {
                    var formats = {
                        'NL': 'DD-MM-YYYY',
                        'BE': 'DD/MM/YYYY',
                        'FR': 'DD/MM/YYYY',
                        'DE': 'DD.MM.YYYY',
                        'AT': 'DD.MM.YYYY',
                        'IT': 'DD/MM/YYYY',
                        'ES': 'DD/MM/YYYY',
                        'PT': 'DD/MM/YYYY',
                        'LU': 'DD/MM/YYYY'
                    };
                    var countryId = quote.billingAddress() ? quote.billingAddress().countryId : null;
                    return formats[countryId] || 'DD/MM/YYYY';
                },

                getData: function () {
                    let phone = this.phone();
                    if (!this.showPhone() && quote.billingAddress() !== null) {
                        phone = quote.billingAddress().telephone;
                    }

                    let customerGender = this.showB2B() ? this.selectedGender() : 'unknown';

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_billingName" : this.billingName(),
                            "customer_telephone" : phone,
                            "customer_gender" : customerGender,
                            "customer_chamberOfCommerce" : this.cocNumber(),
                            "customer_VATNumber" : this.vatNumber(),
                            "customer_DoB" : this.dob()
                        }
                    };
                },

                validate: function () {
                    return this._super();
                }
            }
        );
    }
);
