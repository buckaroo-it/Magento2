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
        'ko'
    ],
    function (
        $,
        Component,
        quote,
        ko
    ) {
        'use strict';

        const validPhone = function (value) {
            if (quote.billingAddress() === null) {
                return false;
            }
            var lengths = {
                'NL': {
                    min: 10,
                    max: 12
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

            let countryId = quote.billingAddress().countryId;
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
            'zakelijkOpRekeningPhoneValidation',
            validPhone,
            $.mage.__('Phone number should be correct.')
        );

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_zakelijkoprekening',
                    billingName: null,
                    phone: null,
                    cocNumber: '',
                    showTooltip: false
                },
                redirectAfterPlaceOrder: false,

                initObservable: function () {
                    this._super().observe([
                        'billingName',
                        'phone',
                        'cocNumber',
                        'showTooltip'
                    ]);

                    this.showPhone = ko.computed(
                        function () {
                            return quote.billingAddress() === undefined ||
                                quote.billingAddress() === null ||
                                validPhone(quote.billingAddress().telephone) === false;
                        },
                        this
                    );

                    this.billingName = ko.computed(
                        function () {
                            if (quote.billingAddress() !== null) {
                                let company = quote.billingAddress().company;
                                if (company && company.trim().length > 0) {
                                    return company;
                                }
                                return quote.billingAddress().firstname + ' ' + quote.billingAddress().lastname;
                            }
                            return '';
                        },
                        this
                    );

                    return this;
                },

                toggleTooltip: function () {
                    this.showTooltip(!this.showTooltip());
                    return false;
                },

                getTooltipText: function () {
                    return this.buckaroo.tooltipText;
                },

                getTooltipUrl: function () {
                    return this.buckaroo.tooltipUrl;
                },

                getData: function () {
                    let telephone = quote.billingAddress().telephone;
                    if (validPhone(this.phone())) {
                        telephone = this.phone();
                    }

                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'customer_billingName': this.billingName(),
                            'customer_telephone': telephone,
                            'customer_chamberOfCommerce': this.cocNumber()
                        }
                    };
                }
            }
        );
    }
);
