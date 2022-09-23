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
        'buckaroo/checkout/common'
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
        checkoutCommon
    ) {
        'use strict';

        const validPhone = function (value, countryId = null) {
           if (countryId === null) {
               countryId = quote.billingAddress().countryId;
           }
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
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_capayablein3',
                    firstname : '',
                    lastname : '',
                    CustomerName : null,
                    BillingName : null,
                    dateValidate : '',
                    value: '',
                    phone: null
                },
                redirectAfterPlaceOrder: false,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.capayablein3.paymentFeeLabel,
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
                        'firstname',
                        'lastname',
                        'CustomerName',
                        'BillingName',
                        'dateValidate',
                        'value',
                        'phone'
                    ]);

                    this.showPhone = ko.computed(
                        function () {
                            return quote.shippingAddress() === undefined ||
                            quote.shippingAddress() === null ||
                            validPhone(quote.shippingAddress().telephone, quote.shippingAddress().countryId) === false
                        },
                        this
                    );

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

                    this.dateValidate.subscribe(runValidation,this);

                    this.buttoncheck = ko.computed(function () {
                        var validation = 
                            this.BillingName() !== null &&
                            this.dateValidate() !== null;


                        return (validation && this.validate());
                    }, this);

                    return this;
                },

                /**
                 * Place order.
                 *
                 * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
                 * (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
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

                    if (quote.billingAddress()) {
                        this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                    }

                    return true;
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.capayablein3.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                },

                validate: function () {
                    let fieldsToValidate = $('.' + this.getCode() + ' .payment [data-validate]:not([name*="agreement"])');
                    if (fieldsToValidate.length) {
                        return fieldsToValidate.valid();
                    }
                    return true;
                },

                getData : function() {
                    let telephone = quote.shippingAddress().telephone;
                    if (validPhone(this.phone(), quote.shippingAddress().countryId)) {
                        telephone = this.phone();
                    }
                    return {
                        "method" : this.item.method,
                        "additional_data": {
                            "customer_billingName" : this.BillingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_telephone" : telephone
                        }
                    };
                }
            }
        );
    }
);
