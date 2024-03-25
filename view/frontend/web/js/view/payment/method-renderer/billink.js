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
                    billingName: '',
                    date: '',
                    phone: '',
                    cocNumber:'',
                    vatNumber: '',
                    dob:null,
                    tos: true,
                    showPhone: false,
                    showFrenchTosValue: null,
                    value: ""
                },
                redirectAfterPlaceOrder : true,
                dp: datePicker,

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
                    this.billingName = ko.computed(
                        function () {
                            if ((this.buckaroo.b2b == true) && quote.billingAddress() !== null) {
                                return quote.billingAddress().company;
                            }
                            if (quote.billingAddress() !== null) {
                                return quote.billingAddress().firstname + " " + quote.billingAddress().lastname;
                            }
                        },
                        this
                    );

                    this.showPhone = ko.computed(
                        function () {
                            return (
                                quote.billingAddress() === null ||
                                !validPhone(quote.billingAddress().telephone)
                            ) && this.buckaroo.b2b != true;
                        },
                        this
                    );

                    this.showB2B = ko.computed(
                        function () {

                            let shipping = quote.shippingAddress();
                            let billing = quote.billingAddress();

                            return this.buckaroo.b2b == true && (
                                (shipping && shipping.company && shipping.company.trim().length > 0) ||
                                (billing && billing.company && billing.company.trim().length > 0)
                            )
                        },
                        this
                    );

                    return this;
                },

                getData: function () {
                    let phone = this.phone();
                    if (!this.showPhone() && quote.billingAddress() !== null) {
                        phone = quote.billingAddress().telephone;
                    }

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone" : phone,
                            "customer_gender" : this.selectedGender(),
                            "customer_chamberOfCommerce" : this.cocNumber(),
                            "customer_VATNumber" : this.vatNumber(),
                            "customer_DoB" : this.dob(),
                            "termsCondition": this.tos(),
                        }
                    };
                }
            }
        );
    }
);
