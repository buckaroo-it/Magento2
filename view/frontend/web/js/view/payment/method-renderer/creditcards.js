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
        'BuckarooClientSideEncryption'
    ],
    function (
        $,
        Component,
        ko,
    ) {
        'use strict';


        /**
         * Add validation methods
         */
        $.validator.addMethod('validateCardNumber', function (value) {
                return BuckarooClientSideEncryption.V001.validateCardNumber(value.replace(/\s+/g, ''));
        },
            $.mage.__('Please enter a valid creditcard number.'));

        $.validator.addMethod('validateCvc', function (value) {
                return BuckarooClientSideEncryption.V001.validateCvc(
                    value,
                    $('#buckaroo_magento2_creditcards_issuer').val()
                );
        },
            $.mage.__('Please enter a valid Cvc number.'));

        $.validator.addMethod('validateCardHolderName', function (value) {
                return BuckarooClientSideEncryption.V001.validateCardholderName(value);
        },
            $.mage.__('Please enter a valid card holder name.'));
        
        $.validator.addMethod('bkValidateYear', function (value) {
            if (value.length === 0) {
                return false;
            }
                const parts = value.split("/");
                return BuckarooClientSideEncryption.V001.validateYear(parts[1]);
        },
            $.mage.__('Enter a valid year number.'));
        $.validator.addMethod('bkValidateMonth', function (value) {
            if (value.length === 0) {
                return false;
            }

                const parts = value.split("/");
                return BuckarooClientSideEncryption.V001.validateMonth(parts[0]);
        },
            $.mage.__('Enter a valid month number.'));

        return Component.extend(
            {
                defaults: {
                    template        : 'Buckaroo_Magento2/payment/buckaroo_magento2_creditcards',
                    cardNumber      : '',
                    cvc             : '',
                    cardHolderName  : '',
                    expireDate      : '',
                    encryptedCardData : null,
                    cardIssuer      : null,
                },

                initObservable: function () {
                    /** Observed fields **/
                    this._super().observe(
                        [
                            'cardNumber',
                            'cvc',
                            'cardHolderName',
                            'expireDate',
                            'cardIssuer'
                        ]
                    );

                    this.setTestParameters()
                    this.formatedCardNumber = ko.computed({
                        read: function () {
                            let cardNumber = this.cardNumber();
                            if (cardNumber.length) {
                                return this.cardNumber().match(new RegExp('.{1,4}', 'g')).join(" ");
                            }
                            return '';
                        },
                        write: function (value) {
                            this.cardNumber(value.replace(/\s/g, ''));
                        },
                        owner: this
                    });

                    this.formatedExpirationDate = ko.computed({
                        read: function () {
                            let expireDate = this.expireDate();
                            if (expireDate.length) {
                                return expireDate.replace(
                                    /^([1-9]\/|[2-9])$/g,
                                    '0$1/' // 3 > 03/
                                ).replace(
                                    /^(0[1-9]|1[0-2])$/g,
                                    '$1/' // 11 > 11/
                                ).replace(
                                    /^([0-1])([3-9])$/g,
                                    '0$1/$2' // 13 > 01/3
                                ).replace(
                                    /^(0?[1-9]|1[0-2])([0-9]{2})$/g,
                                    '$1/$2' // 141 > 01/41
                                ).replace(
                                    /^(0+\/|0+)$/g,
                                    '0' // 0/ > 0 and 00 > 0
                                ).replace(
                                    /[^\d\/]|^[\/]*$/g,
                                    '' // To allow only digits and `/`
                                ).replace(
                                    /\/\//g,
                                    '/' // Prevent entering more than 1 `/`
                                );
                            }
                            return '';
                        },
                        write: function (value) {
                            this.expireDate(value);
                        },
                        owner: this
                    });


                    this.issuerImage = ko.computed(
                        function () {
                            var cardLogo = this.buckaroo.defaultCardImage;
                            
                            var issuer = this.buckaroo.creditcards.find(o => o.code === this.cardIssuer());
                            if (issuer) {
                                cardLogo = issuer.img;
                            }

                            return cardLogo;
                        },
                        this
                    );

                    return this;
                },

                
                validateCardNumber(data, event) {
                    this.validateField(data, event);

                    //set card issuer
                    this.cardIssuer(
                        this.determineIssuer(data.cardNumber())
                    )
                    
                    //validate the cvc if exists
                    if (this.cvc().length) {
                        $('#buckaroo_magento2_creditcards_cvc').valid();
                    }
                },


                /** Get the card issuer based on the creditcard number **/
                determineIssuer: function (cardNumber) {
                    var issuers = {
                        'amex': {
                            'regex': '^3[47][0-9]{13}$',
                            'name': 'American Express'
                        },
                        'maestro': {
                            'regex': '^(5018|5020|5038|6304|6759|6761|6763)[0-9]{8,15}$',
                            'name': 'Maestro'
                        },
                        'dankort': {
                            'regex': '^(5019|4571)[0-9]{12}$',
                            'name': 'Dankort'
                        },
                        'mastercard': {
                            'regex': '^(5[1-5]|2[2-7])[0-9]{14}$',
                            'name': 'Mastercard'
                        },
                        'visaelectron': {
                            'regex': '^(4026[0-9]{2}|417500|4508[0-9]{2}|4844[0-9]{2}|4913[0-9]{2}|4917[0-9]{2})[0-9]{10}$',
                            'name': 'Visa Electron'
                        },
                        'visa': {
                            'regex': '^4[0-9]{12}(?:[0-9]{3})?$',
                            'name': 'Visa'
                        }
                    };

                    for (var key in issuers) {
                        if (cardNumber !== undefined && cardNumber.match(issuers[key].regex)) {
                            return key;
                        }
                    }

                    return false;
                },

                getData: function() {
                    let cardIssuer = this.cardIssuer();

                    if(cardIssuer == null) {
                        cardIssuer = this.determineIssuer(this.cardNumber());
                    }
                    return {
                        "method":  this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_encrypteddata" : this.encryptedCardData,
                            "customer_creditcardcompany" : cardIssuer
                        }
                    }
                },

                encryptCardData: function () {
                    return new Promise(function (resolve) {
                        const parts = this.expireDate().split("/");
                        const month = parts[0];
                        const year = parts[1];

                        BuckarooClientSideEncryption.V001.encryptCardData(
                            this.cardNumber(),
                            year,
                            month,
                            this.cvc(),
                            this.cardHolderName(),
                            function (encryptedCardData) {
                                this.encryptedCardData = encryptedCardData;
                                resolve()
                            }.bind(this));
                    }.bind(this))
                },
                setTestParameters() {
                    if (this.buckaroo.isTestMode) {
                        this.cardNumber('4563550000000005')
                        this.cardIssuer('visa')
                        this.cardHolderName('Test Acceptation')
                        this.expireDate('01/' + (new Date(new Date().setFullYear(new Date().getFullYear() + 1)).getFullYear().toString().substr(-2)))
                        this.cvc('123')
                    }
                }
            }
        );
    }
);
