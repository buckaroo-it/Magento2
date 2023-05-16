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

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_payperemail',
                    selectedGender: null,
                    firstName: null,
                    middleName: null,
                    lastName: null,
                    email: null,
                    validationState : {}
                },
                redirectAfterPlaceOrder: true,
                paymentFeeLabel: window.checkoutConfig.payment.buckaroo.payperemail.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.payperemail.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('payperemail'),
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,
                genderList: window.checkoutConfig.payment.buckaroo.payperemail.genderList,
                /**
                 * @override
                 */
                initialize: function (options) {
                    if (checkoutData.getSelectedPaymentMethod() == options.index) {
                        window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                    }

                    return this._super(options);
                },

                initObservable: function () {
                    this._super().observe(
                        [
                            'selectedGender',
                            'firstName',
                            'middleName',
                            'lastName',
                            'email',
                            'validationState'
                        ]
                    );
                    quote.billingAddress.subscribe(function (address) {
                        if(address !== null) {
                            this.firstName(address.firstname || '');
                            this.lastName(address.lastname || '');
                            this.middleName(address.middlename || '');

                            this.updateState(
                                'buckaroo_magento2_payperemail_BillingFirstName',
                                (address.firstname && address.firstname.length > 0) || false
                            );
                            this.updateState(
                                'buckaroo_magento2_payperemail_BillingLastName',
                                (address.lastname && address.lastname.length > 0) || false
                            );
                        }
                    }, this);

                    if (typeof customerData === 'object' && customerData.hasOwnProperty('email')) {
                        this.email(customerData.email);
                        this.updateState(
                            'buckaroo_magento2_payperemail_Email',
                            customerData.email.length > 0
                        );
                    }

                    if(quote.guestEmail) {
                        this.email(quote.guestEmail);
                        this.updateState(
                            'buckaroo_magento2_payperemail_Email',
                            quote.guestEmail.length > 0
                        );
                    }

                    /** Check used to see form is valid **/
                    this.buttoncheck = ko.computed(
                        function () {
                            const state = this.validationState();
                            const valid =[
                                "buckaroo_magento2_payperemail_genderSelect",
                                "buckaroo_magento2_payperemail_BillingFirstName",
                                "buckaroo_magento2_payperemail_BillingLastName",
                                "buckaroo_magento2_payperemail_Email"
                            ].map((field) => {
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
                validateField(data, event) {
                    const isValid = $(event.target).valid();
                    let state = this.validationState();
                    state[event.target.id] = isValid;
                    this.validationState(state);
                },


                updateState(id, isValid) {
                    let state = this.validationState();
                    state[id] = isValid;
                    this.validationState(state);
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

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;
                    response = $.parseJSON(response);
                    checkoutCommon.redirectHandle(response);
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                /**
                 * Run validation function
                 */

                validate: function () {
                    return $('.' + this.getCode() + ' .payment-method-second-col form').valid();
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_gender": this.selectedGender(),
                            "customer_billingFirstName": this.firstName(),
                            "customer_billingMiddleName": this.middleName(),
                            "customer_billingLastName": this.lastName(),
                            "customer_email": this.email()
                        }
                    };
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.payperemail.allowedCurrencies;

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

