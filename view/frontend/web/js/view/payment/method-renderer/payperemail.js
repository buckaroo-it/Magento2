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
        'Magento_Checkout/js/action/select-payment-method'
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

        return Component.extend(
            {
                defaults                : {
                    template : 'TIG_Buckaroo/payment/tig_buckaroo_payperemail',
                    selectedGender: null,
                    firstName: null,
                    lastName: null,
                    email: null,
                    CustomerFirstName: null,
                    CustomerLastName: null,
                    CustomerEmail: null,
                    BillingFirstName: null,
                    BillingLastName: null,
                    BillingEmail: null,
                    genderValidate: null
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.payperemail.paymentFeeLabel,
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
                    this._super().observe(
                        [
                            'selectedGender',
                            'firstName',
                            'lastName',
                            'email',
                            'CustomerFirstName',
                            'CustomerLastName',
                            'CustomerEmail',
                            'BillingFirstName',
                            'BillingLastName',
                            'BillingEmail',
                            'genderValidate',
                            'dummy'
                        ]
                    );

                    this.firstName      = quote.billingAddress().firstname;
                    this.lastName       = quote.billingAddress().lastname;
                    this.email          = customerData.email || quote.guestEmail;

                    /**
                     * Observe customer first & lastname
                     */
                    this.CustomerFirstName = ko.computed(
                        function () {
                            return this.firstName;
                        },
                        this
                    );
                    this.BillingFirstName(this.CustomerFirstName());

                    this.CustomerLastName = ko.computed(
                        function () {
                            return this.lastName;
                        },
                        this
                    );
                    this.BillingLastName(this.CustomerLastName());

                    this.CustomerEmail = ko.computed(
                        function () {
                            return this.email;
                        },
                        this
                    );
                    this.BillingEmail(this.CustomerEmail());

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
                     * Validation on the input fields
                     */
                    var runValidation = function () {
                        $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])').valid();
                        this.selectPaymentMethod();
                    };

                    this.BillingFirstName.subscribe(runValidation,this);
                    this.BillingLastName.subscribe(runValidation,this);
                    this.BillingEmail.subscribe(runValidation,this);
                    this.genderValidate.subscribe(runValidation,this);

                    var check = function ()
                    {
                        return (
                            this.selectedGender() !== null &&
                            this.BillingFirstName() !== null &&
                            this.BillingLastName() !== null &&
                            this.BillingEmail() !== null &&
                            this.genderValidate() !== null &&
                            this.validate()
                        );
                    };

                    /**
                     * Check if the required fields are filled. If so: enable place order button (true) | if not: disable place order button (false)
                     */
                    this.buttoncheck = ko.computed(
                        function () {
                            this.selectedGender();
                            this.BillingFirstName();
                            this.BillingLastName();
                            this.BillingEmail();
                            this.genderValidate();
                            this.dummy();
                            return check.bind(this)();
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
                 *          version (TIG_Buckaroo/js/action/place-order) to prevent redirect and handle the response.
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
                    return $('.' + this.getCode() + ' .payment [data-validate]:not([name*="agreement"])').valid();
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_gender" : this.genderValidate(),
                            "customer_billingFirstName" : this.BillingFirstName(),
                            "customer_billingLastName" : this.BillingLastName(),
                            "customer_email" : this.BillingEmail()
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

