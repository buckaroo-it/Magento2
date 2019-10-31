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
        'TIG_Buckaroo/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        quote,
        ko,
        checkoutData,
        selectPaymentMethodAction
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'TIG_Buckaroo/payment/tig_buckaroo_capayablepostpay',
                    selectedGender : null,
                    genderValidate : null,
                    firstname : '',
                    lastname : '',
                    CustomerName : null,
                    BillingName : null,
                    dateValidate : null,
                    selectedOrderAs : 1,
                    CocNumber : null,
                    CompanyName : null
                },
                redirectAfterPlaceOrder: true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.capayablepostpay.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,

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
                        'selectedGender',
                        'genderValidate',
                        'firstname',
                        'lastname',
                        'CustomerName',
                        'BillingName',
                        'dateValidate',
                        'selectedOrderAs',
                        'CocNumber',
                        'CompanyName'
                    ]);

                    // Observe and store the selected gender
                    var self = this;
                    this.setSelectedGender = function (value) {
                        self.selectedGender(value);
                        return true;
                    };

                    /**
                     * Observe customer first & lastname and bind them together, so they could appear in the frontend
                     */
                    this.updateBillingName = function(firstname, lastname) {
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
                        function(newAddress) {
                            if (this.getCode() === this.isChecked() &&
                                newAddress &&
                                newAddress.getKey() &&
                                (newAddress.firstname !== this.firstName || newAddress.lastname !== this.lastName)
                            ) {
                                this.updateBillingName(newAddress.firstname, newAddress.lastname);
                            }
                        }.bind(this)
                    );

                    /**
                     * Validation on the input fields
                     */
                    var runValidation = function () {
                        $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])').valid();
                        this.selectPaymentMethod();
                    };

                    this.genderValidate.subscribe(runValidation,this);
                    this.dateValidate.subscribe(runValidation,this);
                    this.CocNumber.subscribe(runValidation,this);
                    this.CompanyName.subscribe(runValidation,this);

                    this.buttoncheck = ko.computed(function () {
                        var validation = this.selectedGender() !== null &&
                            this.genderValidate() !== null &&
                            this.BillingName() !== null &&
                            this.dateValidate() !== null;

                        if (this.selectedOrderAs() == 2 || this.selectedOrderAs() == 3) {
                            validation = validation && this.CocNumber() !== null && this.CompanyName() !== null;
                        }

                        return (validation && this.validate());
                    }, this);

                    return this;
                },

                /**
                 * Place order.
                 *
                 * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
                 * (TIG_Buckaroo/js/action/place-order) to prevent redirect and handle the response.
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
                    if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        window.location.replace(response.RequiredAction.RedirectURL);
                    }
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);

                    if (quote.billingAddress()) {
                        this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                    }

                    return true;
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.capayablepostpay.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                },

                validate: function () {
                    return $('.' + this.getCode() + ' .payment [data-validate]:not([name*="agreement"])').valid();
                },

                getData : function() {
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
