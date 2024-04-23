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
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/quote',
        'ko',
        'buckaroo/checkout/datepicker'
    ],
    function (
        Component,
        quote,
        ko,
        datePicker
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_capayablepostpay',
                    selectedGender : null,
                    genderValidate : null,
                    firstname : '',
                    lastname : '',
                    CustomerName : null,
                    BillingName : null,
                    dateValidate : null,
                    selectedOrderAs : 1,
                    CocNumber : null,
                    CompanyName : null,
                    value:''
                },
                redirectAfterPlaceOrder: true,
                dp: datePicker,
                

                initObservable: function () {
                    this._super().observe([
                        'selectedGender',
                        'genderValidate',
                        'firstname',
                        'lastname',
                        'CustomerName',
                        'BillingName',
                        'dateValidate',
                        'selectedOrderAs',
                        'CocNumber',
                        'CompanyName',
                        'value'
                    ]);

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                            quote.billingAddress().countryId == 'NL' &&
                            this.buckaroo.showFinancialWarning
                        },
                        this
                    );


                    // Observe and store the selected gender
                    var self = this;
                    this.setSelectedGender = function (value) {
                        self.selectedGender(value);
                        return true;
                    };

                    /**
                     * Observe customer first & lastname and bind them together, so they could appear in the frontend
                     */
                    this.updateBillingName = function (firstname, lastname) {
                        this.firstName = firstname;
                        this.lastName = lastname;

                        this.CustomerName = ko.computed(
                            function () {
                                return this.firstName + " " + this.lastName;
                            },
                            this
                        );

                        this.BillingName(this.CustomerName());
                    };

                    if (quote.billingAddress()) {
                        this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                    }

                    quote.billingAddress.subscribe(
                        function (newAddress) {
                            if (this.getCode() === this.isChecked() &&
                                newAddress &&
                                newAddress.getKey() &&
                                (newAddress.firstname !== this.firstName || newAddress.lastname !== this.lastName)
                            ) {
                                this.updateBillingName(newAddress.firstname, newAddress.lastname);
                            }
                        }.bind(this)
                    );


                    return this;
                },

                getData : function () {
                    return {
                        "method" : this.item.method,
                        "additional_data": {
                            "customer_gender" : this.genderValidate(),
                            "customer_billingName" : this.BillingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_orderAs" : this.selectedOrderAs(),
                            "customer_cocnumber" : this.CocNumber(),
                            "customer_companyName" : this.CompanyName()
                        }
                    };
                }
            }
        );
    }
);
