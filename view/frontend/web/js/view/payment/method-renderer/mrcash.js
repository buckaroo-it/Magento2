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
        'buckaroo/checkout/common',
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
        utils,
        checkoutCommon
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
        $.validator.addMethod('bkValidateYear', function (value) {
                if(value.length === 0) {
                    return false; 
                }
                const parts = value.split("/");
                return BuckarooClientSideEncryption.V001.validateYear(parts[1]);
            },
            $.mage.__('Enter a valid year number.')
        );
        $.validator.addMethod('bkValidateMonth', function (value) {
                if(value.length === 0) {
                    return false; 
                }

                const parts = value.split("/");
                return BuckarooClientSideEncryption.V001.validateMonth(parts[0]);
            },
            $.mage.__('Enter a valid month number.')
        );

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_mrcash',
                    cardNumber      : '',
                    cardHolderName  : null,
                    expireDate      : '',
                    validationState : {},
                    clientSideMode  : 'cc',
                    isMobileMode    : false,
                    encryptedCardData : null
                },
                redirectAfterPlaceOrder: false,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.mrcash.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.mrcash.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('mrcash'),
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                useClientSide : window.checkoutConfig.payment.buckaroo.mrcash.useClientSide,
                isTestMode: window.checkoutConfig.payment.buckaroo.mrcash.isTestMode,

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
                            'cardNumber',
                            'cardHolderName',
                            'expireDate',
                            'validationState',
                            'clientSideMode'
                        ]
                    );
                    this.setTestParameters()
                    this.isMobileMode = ko.computed(
                        function () {
                            return this.useClientSide && this.clientSideMode() == 'mobile';
                        },
                        this
                    );

                    this.formatedCardNumber = ko.computed({
                        read: function () {
                            let cardNumber = this.cardNumber();
                            if(cardNumber.length) {
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
                            if(expireDate.length) {
                                return expireDate.replace(
                                    /^([1-9]\/|[2-9])$/g, '0$1/' // 3 > 03/
                                  ).replace(
                                    /^(0[1-9]|1[0-2])$/g, '$1/' // 11 > 11/
                                  ).replace(
                                    /^([0-1])([3-9])$/g, '0$1/$2' // 13 > 01/3
                                  ).replace(
                                    /^(0?[1-9]|1[0-2])([0-9]{2})$/g, '$1/$2' // 141 > 01/41
                                  ).replace(
                                    /^([0]+)\/|[0]+$/g, '0' // 0/ > 0 and 00 > 0
                                  ).replace(
                                    /[^\d\/]|^[\/]*$/g, '' // To allow only digits and `/`
                                  ).replace(
                                    /\/\//g, '/' // Prevent entering more than 1 `/`
                                  );
                            }
                            return '';
                        },
                        write: function (value) {
                            this.expireDate(value);
                        },
                        owner: this
                    });

                    return this;
                },


                validateField(data, event) {
                    const isValid = $(event.target).valid();
                    let state = this.validationState();
                    state[event.target.id] = isValid;
                    this.validationState(state);
                },

                getData: function () {
                    return {
                        "method":  this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_encrypteddata" : this.encryptedCardData,
                            "client_side_mode" : this.clientSideMode()
                        }
                    }
                },

                encryptCardData: function () {
                    return new Promise(function(resolve) {
                        const parts = this.expireDate().split("/");
                        const month = parts[0];
                        const year = parts[1];

                        BuckarooClientSideEncryption.V001.encryptCardData(
                            this.cardNumber(),
                            year,
                            month,
                            '',
                            this.cardHolderName(),
                            function(encryptedCardData) {
                                this.encryptedCardData = encryptedCardData;
                                resolve()
                            }.bind(this));
                    }.bind(this))
                },

                setClientSideMode: function (mode) {
                    this.clientSideMode(mode)
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
                        this.encryptCardData().then(function() {
                            placeOrder = placeOrderAction(self.getData(), self.redirectAfterPlaceOrder, self.messageContainer);
    
                            $.when(placeOrder).fail(
                                function () {
                                    self.isPlaceOrderActionAllowed(true);
                                }
                            ).done(self.afterPlaceOrder.bind(self));
                        })
                        return true;
                    }
                    return false;
                },

                validate: function () {
                    return this.isMobileMode() || this.useClientSide  == false || $('.' + this.getCode() + ' .payment-method-second-col form').valid();
                },

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;
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

                    selectPaymentMethodAction({
                        "method": this.item.method,
                        "po_number": null,
                    });
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
                setTestParameters() {
                        if (this.useClientSide && this.isTestMode) {
                        this.cardNumber('67034200554565015')
                        this.cardHolderName('Test Acceptation')
                        this.expireDate('01/' + (new Date(new Date().setFullYear(new Date().getFullYear() + 1)).getFullYear().toString().substr(-2)))
                    }
                }
                
            }
        );
    }
);
