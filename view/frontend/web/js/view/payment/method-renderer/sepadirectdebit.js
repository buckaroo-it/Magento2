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
                    template: 'TIG_Buckaroo/payment/tig_buckaroo_sepadirectdebit',
                    bankaccountholder: '',
                    bankaccountnumber: '',
                    bicnumber: '',
                    isnl: false,
                    minimumWords: 2
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
                    this._super().observe(['bankaccountholder', 'bankaccountnumber', 'bicnumber', 'minimumWords', 'isnl']);

                    /**
                     * check if country is NL, if so load: bank account number | ifnot load: bicnumber
                     */
                    this.updateIsNl = function(address) {
                        var isnlComputed = ko.computed(
                            function () {
                                if (address === null) {
                                    return false;
                                }

                                return address.countryId == 'NL';
                            },
                            this
                        );

                        this.isnl(isnlComputed());
                    };

                    this.updateIsNl(quote.billingAddress());

                    quote.billingAddress.subscribe(
                        function (newAddress) {
                            if (this.getCode() === this.isChecked() && newAddress && newAddress.getKey()) {
                                this.updateIsNl(newAddress);
                            }
                        }.bind(this)
                    );

                    /**
                     * Repair IBAN value to uppercase
                     */
                    this.bankaccountnumber.extend({ uppercase: true });

                    /**
                     * Run validation on the three inputfields
                     */

                    var runValidation = function () {
                        $('.' + this.getCode() + ' .payment [data-validate]').valid();
                        this.selectPaymentMethod();
                    };
                    this.bankaccountholder.subscribe(runValidation,this);
                    this.bankaccountnumber.subscribe(runValidation,this);
                    this.bicnumber.subscribe(runValidation,this);

                    /**
                     * Check if the required fields are filled. If so: enable place order button | if not: disable place order button
                     */
                    this.accountNumberIsValid = ko.computed(
                        function () {
                            var isValid = (this.bankaccountholder().length >= this.minimumWords() && this.bankaccountnumber().length > 0);

                            if (!this.isnl()) {
                                isValid = (isValid && this.bicnumber().length > 0);
                            }

                            isValid = (isValid && this.validate());

                            return isValid;
                        },
                        this
                    );
                    return this;
                },

                /**
                 * Run function
                 */

                validate: function () {
                    return $('.' + this.getCode() + ' .payment [data-validate]').valid();
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


