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

        return Component.extend(
            {
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_tinka',
                    billingName: null,
                    dateValidate: null,
                    value:'',
                    phone: null,
                },
                redirectAfterPlaceOrder : true,
                dp: datePicker,
               

                initObservable: function () {
                    this._super().observe(
                        [
                            'dateValidate',
                            'value',
                            'phone'
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

                    this.activeAddress = ko.computed(
                        function () {
                            if (quote.billingAddress()) {
                                return quote.billingAddress();
                            }
                            return quote.shippingAddress();
                        }
                    );
                    
                    this.country = ko.computed(
                        function () {
                            return this.activeAddress().countryId;
                        },
                        this
                    );

                    this.showNLBEFields = ko.computed(
                        function () {
                            return this.country() === 'NL' || this.country() === 'BE'
                        },
                        this
                    );

                    this.billingName = ko.computed(
                        function () {
                            return this.activeAddress().firstname + " " + this.activeAddress().lastname;
                        },
                        this
                    );

                    this.showPhone =  ko.computed(
                        function () {
                            return this.activeAddress().telephone === null ||
                            this.activeAddress().telephone === undefined ||
                            this.activeAddress().telephone.trim().length === 0
                        },
                        this
                    );


                    return this;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_billingName" : this.billingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_telephone": this.phone()
                        }
                    };
                }
            }
        );
    }
);
