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
        'Magento_Ui/js/lib/knockout/bindings/datepicker'
        /*,
         'jquery/validate'*/
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

        /**
         *  constants for backend settings
         */
        var BUSINESS_METHOD_B2C = 1;
        var BUSINESS_METHOD_B2B = 2;

        return Component.extend(
            {
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_billink',
                    businessMethod: null,
                    selectedGender: null,
                    chamberOfCommerce: null,
                    firstName: '',
                    lastName: '',
                    CustomerName: null,
                    BillingName: null,
                    country: '',
                    dateValidate: null,
                    genderValidate: null,
                    chamberOfCommerceValidate: null,
                    VATNumberValidate: null,
                    phoneValidate: null,
                    showNLBEFieldsValue: true,
                    showСhamberOfCommerceValue: null,
                    showVATNumberValue: null,
                    showFrenchTosValue: null,
                    showPhoneValue: null,
                    termsValidate: false,
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.billink.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                currentCustomerAddressId : null,

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
                    this._super().observe(
                        [
                            'businessMethod',
                            'selectedGender',
                            'firstname',
                            'lastname',
                            'CustomerName',
                            'BillingName',
                            'country',
                            'dateValidate',
                            'genderValidate',
                            'chamberOfCommerceValidate',
                            'VATNumberValidate',
                            'phoneValidate',
                            'dummy',
                            'showNLBEFieldsValue',
                            'showСhamberOfCommerceValue',
                            'showVATNumberValue',
                            'showFrenchTosValue',
                            'showPhoneValue',
                            'termsValidate',
                        ]
                    );

                    this.showPhone = ko.computed(
                        function () {
                            if (this.showPhoneValue() !== null) {
                                return this.showPhoneValue();
                            }
                        },
                        this
                    );

                    this.showNLBEFields = ko.computed(
                        function () {
                            if (this.showNLBEFieldsValue() !== null) {
                                return this.showNLBEFieldsValue();
                            }
                        },
                        this
                    );

                    this.showСhamberOfCommerce = ko.computed(
                        function () {
                            if (this.showСhamberOfCommerceValue() !== null) {
                                return this.showСhamberOfCommerceValue();
                            }
                        },
                        this
                    );

                    this.showVATNumber = ko.computed(
                        function () {
                            if (this.showVATNumberValue() !== null) {
                                return this.showVATNumberValue();
                            }
                        },
                        this
                    );

                    this.showFrenchTos = ko.computed(
                        function () {
                            if (this.showFrenchTosValue() !== null) {
                                return this.showFrenchTosValue();
                            }
                        },
                        this
                    );

                    this.updateShowFields = function () {
                        if (this.country === null) {
                            return;
                        }

                        this.showNLBEFieldsValue(false);
                        this.showСhamberOfCommerceValue(false);
                        this.showVATNumberValue(false);
                        this.showPhoneValue(false);

                        this.showNLBEFieldsValue(true);

                        if (this.businessMethod == BUSINESS_METHOD_B2C) {
                            this.showPhoneValue(true);
                        }

                        if (this.businessMethod == BUSINESS_METHOD_B2B) {
                            this.showСhamberOfCommerceValue(true);
                            this.showVATNumberValue(true);
                        }

                    };

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
                        this.phoneValidate(quote.billingAddress().telephone);
                        this.updateShowFields();
                    }

                    quote.billingAddress.subscribe(
                        function(newAddress) {
                            this.businessMethod = quote.billingAddress() && quote.billingAddress().company ? BUSINESS_METHOD_B2B : BUSINESS_METHOD_B2C;

                            if (this.getCode() !== this.isChecked() ||
                                !newAddress ||
                                !newAddress.getKey()
                            ) {
                                return;
                            }

                            if (this.currentCustomerAddressId != newAddress.getKey()) {
                                this.currentCustomerAddressId = newAddress.getKey();
                                this.phoneValidate(newAddress.telephone);
                            }

                            if (newAddress.firstname !== this.firstName || newAddress.lastname !== this.lastName) {
                                this.updateBillingName(newAddress.firstname, newAddress.lastname);
                            }

                            this.updateShowFields();
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

                    this.validatePhone = function() {

                        function returnSuccess() {
                            $('#' + self.getCode() + '_Telephone-error').hide();
                            $('#' + self.getCode() + '_Telephone').removeClass('mage-error');
                            return true;
                        }

                        function returnError() {
                            setTimeout(function () {
                                $('#' + self.getCode() + '_Telephone-error').show();
                                $('#' + self.getCode() + '_Telephone').addClass('mage-error');
                            }, 200);
                            return false;
                        }

                        if ((this.country == 'NL' || this.country == 'BE') || this.phoneValidate()) {
                            var lengths = {
                                'NL': {
                                    min: 10,
                                    max: 12
                                },
                                'BE': {
                                    min: 9,
                                    max: 10
                                },
                                'DE': {
                                    min: 11,
                                    max: 14
                                }/*,
                                'FI': {
                                    min: 5,
                                    max: 12
                                },*/
                            };

                            if (!this.phoneValidate()) {
                                return returnError();
                            }

                            if (this.phoneValidate().match(/\+/g)) {
                                return returnError();
                            }

                            if (lengths.hasOwnProperty(this.country)) {
                                if (lengths[this.country].min && (this.phoneValidate().length < lengths[this.country].min)) {
                                    return returnError();
                                }
                                if (lengths[this.country].max && (this.phoneValidate().length > lengths[this.country].max)) {
                                    return returnError();
                                }
                            }

                        }
                        return returnSuccess();
                    };

                    /**
                     * Validation on the input fields
                     */

                    var runValidation = function () {
                        var elements = $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])');
                        // if (this.country != 'NL' && this.country != 'BE') {
                            elements = elements.filter(':not([name*="customer_gender"])');
                        // }
                        elements.valid();

                        if (this.calculateAge(this.dateValidate()) >= 18) {
                            $('#' + this.getCode() + '_DoB-error').hide();
                            $('#' + this.getCode() + '_DoB').removeClass('mage-error');
                        } else {
                            setTimeout(function() {
                                $('#' + self.getCode() + '_DoB-error').show();
                                $('#' + self.getCode() + '_DoB').addClass('mage-error');
                            },200);
                        }
                    };

                    this.termsValidate.subscribe(runValidation,this);
                    this.dateValidate.subscribe(runValidation,this);
                    this.genderValidate.subscribe(runValidation,this);
                    this.chamberOfCommerceValidate.subscribe(runValidation,this);
                    this.VATNumberValidate.subscribe(runValidation,this);
                    this.phoneValidate.subscribe(runValidation,this);
                    this.dummy.subscribe(runValidation,this);

                    this.calculateAge = function (specifiedDate) {
                        if (specifiedDate && (specifiedDate.length > 0)) {
                            var dateReg = /^\d{2}[./-]\d{2}[./-]\d{4}$/;
                            if (specifiedDate.match(dateReg)) {
                                var birthday = +new Date(
                                    specifiedDate.substr(6, 4),
                                    specifiedDate.substr(3, 2) - 1,
                                    specifiedDate.substr(0, 2),
                                    0, 0, 0
                                );
                                return ~~((Date.now() - birthday) / (31557600000));
                            }
                        }
                        return false;
                    }

                    /**
                     * Check if the required fields are filled. If so: enable place order button (true) | if not: disable place order button (false)
                     */
                    this.buttoncheck = ko.computed(
                        function () {
                            var result =
                                (!this.showNLBEFields() || this.selectedGender() !== null) &&
                                (!this.showСhamberOfCommerce() || this.chamberOfCommerceValidate() !== null) &&
                                (!this.showVATNumber() || this.VATNumberValidate() !== null) &&
                                this.BillingName() !== null &&
                                (!this.showNLBEFields() || this.dateValidate() !== null) &&
                                (!this.showPhone() || ((this.phoneValidate() !== null) && (this.validatePhone()))) &&
                                this.termsValidate() !== false &&
                                this.validate()  &&
                                (this.calculateAge(this.dateValidate()) >= 18)

                            return result;
                        },
                        this
                    );

                    return this;
                },

                /**
                 * Place order.
                 *
                 * @todo To override the script used for placeOrderAction, we need to override the placeOrder method
                 *          on our parent class (Magento_Checkout/js/view/payment/default) so we can
                 *
                 *          placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own
                 *          version (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
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

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);

                    if (quote.billingAddress()) {
                        this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                    }

                    return true;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone" : this.phoneValidate(),
                            "customer_gender" : this.genderValidate(),
                            "customer_chamberOfCommerce" : this.chamberOfCommerceValidate(),
                            "customer_VATNumber" : this.VATNumberValidate(),
                            "customer_billingName" : this.BillingName(),
                            "customer_DoB" : this.dateValidate(),
                            "termsCondition": this.termsValidate(),
                        }
                    };
                }
            }
        );
    }
);
