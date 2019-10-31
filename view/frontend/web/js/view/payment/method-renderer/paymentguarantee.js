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
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Ui/js/modal/modal',
        'Magento_Ui/js/lib/knockout/bindings/datepicker'
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
        modal
    ) {
        'use strict';

        var PAYMENT_METHOD_TRANSFER = 1;
        var PAYMENT_METHOD_IDEAL      = 2;

        /**
         * Validate IBAN and BIC number
         */
        function isValidIBAN($v){ //This function check if the checksum if correct
            $v = $v.replace(/^(.{4})(.*)$/,"$2$1"); //Move the first 4 chars from left to the right
            $v = $v.replace(/[A-Z]/g,function($e){return $e.charCodeAt(0) - 'A'.charCodeAt(0) + 10}); //Convert A-Z to 10-25
            var $sum = 0;
            var $ei = 1; //First exponent
            for(var $i = $v.length - 1; $i >= 0; $i--){
                $sum += $ei * parseInt($v.charAt($i),10); //multiply the digit by it's exponent
                $ei = ($ei * 10) % 97; //compute next base 10 exponent  in modulus 97
            }
            return $sum % 97 == 1;
        }

        /**
         * Add validation methods
         */
        $.validator.addMethod(
            'IBAN', function (value) {
                var patternIBAN = new RegExp('^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}$');
                return (patternIBAN.test(value) && isValidIBAN(value));
            },
            $.mage.__('Enter Valid IBAN')
        );

        return Component.extend({
            defaults: {
                template: 'TIG_Buckaroo/payment/tig_buckaroo_paymentguarantee',
                paymentMethod : null,
                telephoneNumber : null,
                selectedGender : null,
                firstname : '',
                lastname : '',
                CustomerName : null,
                BillingName : null,
                dateValidate : null,
                bankaccountnumber : '',
                termsValidat : null,
                termsElement : null,
                genderValidate : null
            },

            redirectAfterPlaceOrder: true,
            paymentFeeLabel : window.checkoutConfig.payment.buckaroo.paymentguarantee.paymentFeeLabel,
            currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
            baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,

            /**
             * @override
             */
            initialize : function (options) {
                if(checkoutData.getSelectedPaymentMethod() == options.index) {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                }

                return this._super(options);
            },

            initObservable: function () {
                this._super().observe([
                    'paymentMethod',
                    'telephoneNumber',
                    'selectedGender',
                    'firstname',
                    'lastname',
                    'CustomerName',
                    'BillingName',
                    'dateValidate',
                    'bankaccountnumber',
                    'termsValidate',
                    'genderValidate',
                    'dummy'
                ]);

                this.paymentMethod  = window.checkoutConfig.payment.buckaroo.paymentguarantee.paymentMethod;

                /**
                 * Observe customer first & lastname
                 * bind them together, so they could appear in the frontend
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
                 * observe radio buttons
                 * check if selected
                 */
                var self = this;
                this.setSelectedGender = function (value) {
                    self.selectedGender(value);
                    return true;
                };

                /**
                 * Check if TelephoneNumber is filled in. If not - show field
                 */
                this.hasTelephoneNumber = ko.computed( function () {
                    var telephone = quote.billingAddress() ? quote.billingAddress().telephone : null;
                    return telephone != '' && telephone != '-';
                });

                /**
                 * Repair IBAN value to uppercase
                 */
                this.bankaccountnumber.extend({ uppercase: true });

                /**
                 * Validation on the input fields
                 */
                var runValidation = function () {
                    $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])').valid();
                    this.selectPaymentMethod();
                };

                this.dateValidate.subscribe(runValidation,this);
                this.bankaccountnumber.subscribe(runValidation,this);
                this.termsValidate.subscribe(runValidation,this);
                this.genderValidate.subscribe(runValidation,this);
                this.dummy.subscribe(runValidation,this);

                var check = function ()
                {
                    return (
                        this.selectedGender() !== null &&
                        this.BillingName() !== null &&
                        this.dateValidate() !== null &&
                        this.termsValidate() !== false &&
                        this.genderValidate() !== null &&
                        this.validate()
                    );
                };

                this.buttoncheck = ko.computed(function () {
                    this.selectedGender();
                    this.BillingName();
                    this.dateValidate();
                    this.bankaccountnumber();
                    this.termsValidate();
                    this.genderValidate();
                    this.dummy();
                    return check.bind(this)();
                }, this);

                return this;

            },

            initAgreements: function (element) {
                this.termsElement = element;

                var options = {
                    'type': 'popup',
                    'modalClass': 'agreements-modal',
                    'responsive': true,
                    'innerScroll': true,
                    'trigger': '.show-modal',
                    'buttons': [
                        {
                            text: $.mage.__('Close'),
                            class: 'action secondary action-hide-popup',
                            click: function() {
                                this.closeModal();
                            }
                        }
                    ]
                };

                modal(options, $(this.termsElement));
            },

            showAgreements: function(data, event) {
                if (event) {
                    event.preventDefault();
                }

                $(this.termsElement).modal('openModal');
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

                    $.when(placeOrder).fail(function() {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            magentoTerms: function() {
                /**
                 * The agreement checkbox won't force an update of our bindings. So check for changes manually and notify
                 * the bindings if something happend. Use $.proxy() to access the local this object. The dummy property is
                 * used to notify the bindings.
                 **/
                $('.payment-methods').one(
                    'click',
                    '.' + this.getCode() + ' [name*="agreement"]',
                    $.proxy(
                        function () {
                            this.dummy.notifySubscribers();
                        },
                        this
                    )
                );

            },

            afterPlaceOrder: function () {
                var response = window.checkoutConfig.payment.buckaroo.response;
                response = $.parseJSON(response);
                if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                    window.location.replace(response.RequiredAction.RedirectURL);
                }
            },

            selectPaymentMethod: function() {
                window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);

                if (quote.billingAddress()) {
                    this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                }

                return true;
            },

            payWithBaseCurrency: function() {
                var allowedCurrencies = window.checkoutConfig.payment.buckaroo.paymentguarantee.allowedCurrencies;

                return allowedCurrencies.indexOf(this.currencyCode) < 0;
            },

            getPayWithBaseCurrencyText: function() {
                var text = $.mage.__('The transaction will be processed using %s.');

                return text.replace('%s', this.baseCurrencyCode);
            },

            /**
             * Run validation function
             */

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
                        "customer_iban": this.bankaccountnumber(),
                        "termsCondition" : this.termsValidate()
                    }
                };
            }
        });
    }
);
