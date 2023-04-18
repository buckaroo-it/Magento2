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
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        quote
    ) {
        'use strict';

        /**
         * Validate IBAN and BIC number
         * This function check if the checksum if correct
         */

        function isValidIBAN($v)
        {
            $v = $v.replace(/^(.{4})(.*)$/,"$2$1"); //Move the first 4 chars from left to the right
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
                $sum += $ei * parseInt($v.charAt($i),10); //multiply the digit by it's exponent
                $ei = ($ei * 10) % 97; //compute next base 10 exponent  in modulus 97
            }
            return $sum % 97 == 1;
        }

        /**
         * Add validation methods
         * */

        $.validator.addMethod(
            'IBAN',
            function (value) {
                var patternIBAN = new RegExp('^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}$');
                return (patternIBAN.test(value) && isValidIBAN(value));
            },
            $.mage.__('Enter Valid IBAN')
        );

        $.validator.addMethod(
            'BIC',
            function (value) {
                var patternBIC = new RegExp('^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$');
                return patternBIC.test(value);
            },
            $.mage.__('Enter Valid BIC number')
        );

        /**
         * check country requires IBAN or BIC field
         * */

        return Component.extend(
            {
                /**
                 * Include template
                 */

                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_sepadirectdebit',
                    bankaccountholder: '',
                    bankaccountnumber: '',
                    bicnumber: '',
                    isnl: false,
                    validationState : {},
                },
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.sepadirectdebit.paymentFeeLabel,
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
                        'bankaccountholder', 
                        'bankaccountnumber',
                        'bicnumber',
                        'validationState'
                    ]);

                    this.isnl = ko.computed(function () {
                        return quote.billingAddress() !== null &&  quote.billingAddress().countryId == 'NL'
                    }, this);


                    /**
                     * Repair IBAN value to uppercase
                     */
                    this.bankaccountnumber.extend({ uppercase: true });

                    /** Check used to see form is valid **/
                    this.buttoncheck = ko.computed(
                        function () {
                            const state = this.validationState();
                            const valid =this.getActiveFields().map((field) => {
                                if(state[field] !== undefined) {
                                    return state[field];
                                }
                                return false;
                            }).reduce(
                                function(prev, cur) {
                                    return prev && cur
                                },
                                true
                            )
                            return valid;
                        },
                        this
                    );
                    
                    return this;
                },

                getActiveFields() {
                    let fields = [
                        'bankaccountholder',
                        'bankaccountnumber',
                    ];
                    if(!this.isnl()) {
                        fields.push('bicnumber');
                    }
                    return fields;
                },
                validateField(data, event) {
                    const isValid = $(event.target).valid();
                    let state = this.validationState();
                    state[event.target.id] = isValid;
                    this.validationState(state);
                },
                /**
                 * Run function
                 */

                validate: function () {
                    return $('.' + this.getCode() + ' .payment-method-second-col form').valid();
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_bic": this.bicnumber(),
                            "customer_iban": this.bankaccountnumber(),
                            "customer_account_name": this.bankaccountholder()
                        }
                    };
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.sepadirectdebit.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                }
            }
        );
    }
);


