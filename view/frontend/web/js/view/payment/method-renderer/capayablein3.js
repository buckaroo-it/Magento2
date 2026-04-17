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
        'buckaroo/checkout/datepicker-enhanced'
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
                },
                redirectAfterPlaceOrder: false,
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

                getMessageText: function () {
                    return $.mage
                        .__('Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van ' +
                            window.checkoutConfig.payment.buckaroo.buckaroo_magento2_capayablein3.title +
                            '. Door verder te gaan, accepteer je de <a target="_blank" href="%s">Algemene&nbsp;Voorwaarden</a> en bevestig je dat je de <a target="_blank" href="%f">Privacyverklaring</a> en <a target="_blank" href="%c">Cookieverklaring</a> hebt gelezen.')
                        .replace('%s', 'https://payin3.eu/nl/legal/')
                        .replace('%f', 'https://payin3.eu/nl/privacyverklaringen/')
                        .replace('%c', 'https://payin3.eu/nl/cookiebeleid/');
                },

                initObservable: function () {
                    this._super().observe([
                        'billingName',
                        'dateValidate',
                        'value',
                        'phone'
                    ]);

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                            quote.billingAddress().countryId == 'NL' &&
                            this.buckaroo.showFinancialWarning
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
