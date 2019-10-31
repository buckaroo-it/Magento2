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
        'BuckarooClientSideEncryption'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction
    ) {
        'use strict';

        /**
         * Add validation methods
         */
        $.validator.addMethod('validateCardNumber', function (value) {
                return BuckarooClientSideEncryption.V001.validateCardNumber(value.replace(/\s+/g, ''));
            },
            $.mage.__('Please enter a valid creditcard number.')
        );
        $.validator.addMethod('validateCvc', function (value) {
                return BuckarooClientSideEncryption.V001.validateCvc(value);
            },
            $.mage.__('Please enter a valid Cvc number.')
        );
        $.validator.addMethod('validateCardHolderName', function (value) {
                return BuckarooClientSideEncryption.V001.validateCardholderName(value);
            },
            $.mage.__('Please enter a valid card holder name.')
        );
        $.validator.addMethod('validateYear', function (value) {
                return BuckarooClientSideEncryption.V001.validateYear(value);
            },
            $.mage.__('Enter a valid year number.')
        );
        $.validator.addMethod('validateMonth', function (value) {
                return BuckarooClientSideEncryption.V001.validateMonth(value);
            },
            $.mage.__('Enter a valid month number.')
        );

        return Component.extend(
            {
                defaults: {
                    template        : 'TIG_Buckaroo/payment/tig_buckaroo_creditcards',
                    CardNumber      : null,
                    Cvc             : null,
                    CardHolderName  : null,
                    ExpirationYear  : null,
                    ExpirationMonth : null,
                    EncryptedData   : null,
                    issuerImage     : null,
                    CardIssuer      : null,
                    CardDesign      : window.checkoutConfig.payment.buckaroo.creditcards.useCardDesign == true
                },
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.creditcards.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                creditcards : window.checkoutConfig.payment.buckaroo.creditcards.creditcards,
                defaultCardImage : window.checkoutConfig.payment.buckaroo.creditcards.defaultCardImage,
                months : [
                    {'value' : '', 'label' : $.mage.__('Select a month') },
                    {'value' : 1, 'label' : '01'},
                    { 'value': 2, 'label' : '02'},
                    { 'value': 3, 'label' : '03'},
                    { 'value': 4, 'label' : '04'},
                    { 'value': 5, 'label' : '05'},
                    { 'value': 6, 'label' : '06'},
                    { 'value': 7, 'label' : '07'},
                    { 'value': 8, 'label' : '08'},
                    { 'value': 9, 'label' : '09'},
                    { 'value': 10, 'label' : '10'},
                    { 'value': 11, 'label' : '11'},
                    { 'value': 12, 'label' : '12'}
                ],

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
                    /** Observed fields **/
                    this._super().observe(
                        [
                            'CardNumber',
                            'Cvc',
                            'CardHolderName',
                            'ExpirationYear',
                            'ExpirationMonth',
                            'EncryptedData',
                            'issuerImage',
                            'CardIssuer'
                        ]
                    );

                    this.CardIssuer.subscribe(this.changeIssuerLogo, this);

                    /**
                     * Subscribe the fields to validate them on changes.
                     * The .valid() method inside validateIndividual will force the $.validator to run.
                     **/
                    this.CardNumber.subscribe(this.validateIndividual, 'tig_buckaroo_creditcards_cardnumber');
                    this.Cvc.subscribe(this.validateIndividual, 'tig_buckaroo_creditcards_cvc');
                    this.CardHolderName.subscribe(this.validateIndividual, 'tig_buckaroo_creditcards_cardholdername');

                    /** Check used to see if input is valid **/
                    this.buttoncheck = ko.computed(
                        function () {
                            return (
                                this.CardNumber() !== null &&
                                this.Cvc() !== null &&
                                this.CardHolderName() !== null &&
                                this.ExpirationYear() !== null &&
                                this.ExpirationMonth() !== null &&
                                this.EncryptedData() !== null &&
                                this.CardIssuer() !== null &&
                                this.validate()
                            );
                        },
                        this
                    );

                    return this;
                },

                /** Unable to translate 'Select a year' within knockout, so we create the option objects here **/
                getYears : function () {
                    var years = [{ 'value': '', 'label': $.mage.__('Select a year') }];
                    for(var i=0; i<=10; i++) {
                        years.push({'value': new Date().getFullYear() + i, 'label': new Date().getFullYear() + i});
                    }

                    return years;
                },

                /** This will run the $.validator functions that are defined at the top of this file. **/
                validateIndividual: function () {
                    $('#' + this).valid();
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                validateIssuer: function() {
                    $('#tig_buckaroo_creditcards_issuer').valid();
                    this.encryptCardDetails();
                },

                validateMonth: function() {
                    $('#tig_buckaroo_creditcards_expirationmonth').valid();
                    this.encryptCardDetails();
                },

                validateYear: function() {
                    $('#tig_buckaroo_creditcards_expirationyear').valid();
                    this.encryptCardDetails();
                },

                /**
                 * Run validation function
                 */
                validate: function () {
                    var elements = $('.' + this.getCode() + ' [data-validate]:not([name*="agreement"])');
                    return elements.valid();
                },

                changeIssuerLogo: function () {
                    var cardLogo = this.defaultCardImage;

                    var issuer = this.creditcards.find(o => o.code === this.CardIssuer());
                    if (issuer) {
                        cardLogo = issuer.img;
                    }

                    this.issuerImage(cardLogo);
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.creditcards.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
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

                /** Encrypt the creditcard details using Buckaroo's encryption system. **/
                encryptCardDetails: function () {
                    var self = this;

                    if (this.CardNumber() == null ||
                        this.ExpirationYear() == null ||
                        this.ExpirationMonth() == null ||
                        this.Cvc() == null ||
                        this.CardHolderName() == null
                    ) {
                        return;
                    }

                    if (this.validate()) {
                        var cardNumber  = this.CardNumber().replace(/\s+/g, '');
                        var year        = this.ExpirationYear();
                        var month       = this.ExpirationMonth();
                        var cvc         = this.Cvc();
                        var cardholder  = this.CardHolderName();

                        self.selectPaymentMethodAction = selectPaymentMethodAction;

                        var getEncryptedData = function(cardNumber, year, month, cvc, cardholder) {
                            BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
                                year,
                                month,
                                cvc,
                                cardholder,
                                function(encryptedCardData) {
                                    self.EncryptedData(encryptedCardData);
                                    self.selectPaymentMethodAction(self.getData());
                                });
                        };
                        getEncryptedData(cardNumber, year, month, cvc, cardholder);
                        selectPaymentMethodAction(this.getData());
                    }
                },

                /** This and getCardIssuer are currently unused. As mentioned in BUCKM2-391, this should be included in the future. **/
                processCard: function () {
                    var cardIssuerObject = this.getCardIssuer();
                    if (cardIssuerObject && cardIssuerObject.active) {
                        this.issuerImage(cardIssuerObject.img);
                    }
                    this.CardIssuerObject = cardIssuerObject;
                },

                /** Get the card issuer based on the creditcard number **/
                getCardIssuer: function () {
                    if (!this.CardNumber()) {
                        return false;
                    }

                    var issuerIdentificationNumbers = {
                        'amex': {
                            'regex': '^(34|37)[0-9]{13}$',
                            'name': 'American Express'
                        },
                        'maestro': {
                            'regex': '^(6759[0-9]{2}|676770|676774)[0-9]{6,13}$',
                            'name': 'Maestro'
                        },
                        'dankort': {
                            'regex': '^(5019|4571)[0-9]{12}$',
                            'name': 'Dankort'
                        },
                        'mastercard': {
                            'regex': '^(222[1-9]|2[3-6][0-9]{2}|27[0-1][0-9]|2720|5[1-5][0-9]{2})[0-9]{12}$',
                            'name': 'Mastercard'
                        },
                        'visaelectron': {
                            'regex': '^(4026[0-9]{2}|417500|4508[0-9]{2}|4844[0-9]{2}|4913[0-9]{2}|4917[0-9]{2})[0-9]{10}$',
                            'name': 'Visa Electron'
                        },
                        'visa': {
                            'regex': '^4[0-9]{15,18}$',
                            'name': 'Visa'
                        }
                    };

                    for (var key in issuerIdentificationNumbers) {
                        if (this.CardNumber().match(issuerIdentificationNumbers[key].regex)) {
                            return this.creditcards.find(function (creditcard) { return creditcard.code == key; });
                        }
                    }

                    return false;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_encrypteddata" : this.EncryptedData(),
                            "customer_creditcardcompany" : this.CardIssuer()
                        }
                    };
                }
            }
        );
    }
);
