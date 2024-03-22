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
        'ko',
        'Magento_Checkout/js/model/quote',
    ],
    function (
        $,
        Component,
        ko,
        quote,
    ) {
        'use strict';

        /**
         * Validate IBAN and BIC number
         * This function check if the checksum if correct
         */

        function isValidIBAN($v) {
            $v = $v.replace(/^(.{4})(.*)$/, "$2$1"); //Move the first 4 chars from left to the right
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
                $sum += $ei * parseInt($v.charAt($i), 10); //multiply the digit by it's exponent
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
                var patternIBAN = new RegExp('^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]{1,16})$');
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
                    bicnumber: ''
                },

                initObservable: function () {
                    this._super().observe([
                        'bankaccountholder',
                        'bankaccountnumber',
                        'bicnumber',
                        'validationState'
                    ]);

                    this.isnl = ko.computed(function () {
                        return quote.billingAddress() !== null && quote.billingAddress().countryId == 'NL'
                    }, this);

                    /**
                     * Repair IBAN value to uppercase
                     */
                    this.bankaccountnumber.extend({ uppercase: true });

                    return this;
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
            }
        );
    }
);


