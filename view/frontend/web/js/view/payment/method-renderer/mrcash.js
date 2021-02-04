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
        'mageUtils',
        'BuckarooClientSideEncryption'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        utils
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
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_mrcash',
                    CardNumber      : null,
                    CardHolderName  : null,
                    ExpirationYear  : null,
                    ExpirationMonth : null,
                    EncryptedData   : null
                },
                redirectAfterPlaceOrder: false,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.mrcash.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                useClientSide : window.checkoutConfig.payment.buckaroo.mrcash.useClientSide,
                clientSideMode: 'cc',
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
                    if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        if (this.isMobileMode()) {
                            var data =  {};
                            data['transaction_key'] = response.key;

                            utils.submit({
                                url: window.checkoutConfig.payment.buckaroo.mrcash.redirecturl,
                                data: response
                            });
                        } else {
                            window.location.replace(response.RequiredAction.RedirectURL);
                        }
                    }
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.mrcash.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                },

                initObservable: function () {
                    /** Observed fields **/
                    this._super().observe(
                        [
                            'CardNumber',
                            'CardHolderName',
                            'ExpirationYear',
                            'ExpirationMonth',
                            'EncryptedData'
                        ]
                    );

                    /**
                     * Subscribe the fields to validate them on changes.
                     * The .valid() method inside validateIndividual will force the $.validator to run.
                     **/
                    this.CardNumber.subscribe(this.validateIndividual, 'buckaroo_magento2_mrcash_cardnumber');
                    this.CardHolderName.subscribe(this.validateIndividual, 'buckaroo_magento2_mrcash_cardholdername');

                    /** Check used to see if input is valid **/
                    this.buttoncheck = ko.computed(
                        function () {
                            return (
                                this.CardNumber() !== null &&
                                this.CardHolderName() !== null &&
                                this.ExpirationYear() !== null &&
                                this.ExpirationMonth() !== null &&
                                this.EncryptedData() !== null &&
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

                validateMonth: function() {
                    $('#buckaroo_magento2_mrcash_expirationmonth').valid();
                    this.encryptCardDetails();
                },

                validateYear: function() {
                    $('#buckaroo_magento2_mrcash_expirationyear').valid();
                    this.encryptCardDetails();
                },

                /**
                 * Run validation function
                 */
                validate: function () {
                    if (this.isCcMode()) {
                        var elements = $('.' + this.getCode() + ' [data-validate]:not([name*="agreement"])');
                        return elements.valid();
                    } else {
                        return true;
                    }
                },

                /** Encrypt the creditcard details using Buckaroo's encryption system. **/
                encryptCardDetails: function () {
                    var self = this;

                    if (this.CardNumber() == null ||
                        this.ExpirationYear() == null ||
                        this.ExpirationMonth() == null ||
                        this.CardHolderName() == null
                    ) {
                        return;
                    }

                    if (this.validate()) {
                        var cardNumber  = this.CardNumber().replace(/\s+/g, '');
                        var year        = this.ExpirationYear();
                        var month       = this.ExpirationMonth();
                        var cardholder  = this.CardHolderName();

                        self.selectPaymentMethodAction = selectPaymentMethodAction;

                        var getEncryptedData = function(cardNumber, year, month, cardholder) {
                            BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
                                year,
                                month,
                                '',
                                cardholder,
                                function(encryptedCardData) {
                                    self.EncryptedData(encryptedCardData);
                                    self.selectPaymentMethodAction(self.getData());
                                });
                        };
                        getEncryptedData(cardNumber, year, month, cardholder);
                        selectPaymentMethodAction(this.getData());
                    }
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_encrypteddata" : this.EncryptedData(),
                            "client_side_mode" : this.clientSideMode
                        }
                    };
                },

                setClientSideMode: function (mode) {
                    this.clientSideMode = mode;
                    return true;
                },

                isCcMode: function () {
                    return this.useClientSide && (this.clientSideMode == 'cc');
                },

                isMobileMode: function () {
                    return this.useClientSide && (this.clientSideMode == 'mobile');
                }
            }
        );
    }
);








