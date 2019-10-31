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
        var BUSINESS_METHOD_BOTH = 3;
        var COUNTRY_FORMAT_DE = 'DE';
        var COUNTRY_FORMAT_AT = 'AT';
        var COUNTRY_FORMAT_NL = 'NL';


        /**
         * Add validation methods
         */
        $.validator.addMethod(
            'IBAN',
            function (value) {
                var patternIBAN = new RegExp('^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}$');
                return (patternIBAN.test(value) && isValidIBAN(value));
            },
            $.mage.__('Enter Valid IBAN')
        );

        return Component.extend(
            {
                defaults                : {
                    template : 'TIG_Buckaroo/payment/tig_buckaroo_klarna',
                    businessMethod: null,
                    paymentMethod: null,
                    telephoneNumber: null,
                    selectedGender: null,
                    selectedBusiness: 1,
                    firstName: '',
                    lastName: '',
                    CustomerName: null,
                    BillingName: null,
                    country: '',
                    dateValidate: null,
                    CocNumber: null,
                    CompanyName:null,
                    bankaccountnumber: '',
                    termsUrl: 'https://www.klarna.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsValidate: false,
                    genderValidate: null,
                    invoiceText: ''
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.klarna.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                paymentFee : window.checkoutConfig.payment.buckaroo.klarna.paymentFee,

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
                            'paymentMethod',
                            'telephoneNumber',
                            'selectedGender',
                            'selectedBusiness',
                            'firstname',
                            'lastname',
                            'CustomerName',
                            'BillingName',
                            'country',
                            'dateValidate',
                            'CocNumber',
                            'CompanyName',
                            'bankaccountnumber',
                            'termsUrl',
                            'termsValidate',
                            'genderValidate',
                            'dummy',
                            'invoiceText'
                        ]
                    );

                    this.businessMethod = window.checkoutConfig.payment.buckaroo.klarna.businessMethod;
                    this.paymentMethod  = window.checkoutConfig.payment.buckaroo.klarna.paymentMethod;

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

                    this.updateTermsUrl = function(country) {
                        this.billingAddress = country;
                        var newUrl = '';
                        var newInvoice = '';
                        var fee = this.paymentFee.toString().replace(".",",");

                        switch (this.billingAddress) {
                            case COUNTRY_FORMAT_DE:
                                newUrl = 'https://cdn.klarna.com/1.0/shared/content/legal/terms/0/de_de/invoice?fee='+fee;
                                newInvoice = 'Rechnungsbedingungen';
                                break;
                            case COUNTRY_FORMAT_AT:
                                newUrl = 'https://cdn.klarna.com/1.0/shared/content/legal/terms/0/de_at/invoice?fee='+fee;
                                newInvoice = 'Rechnungsbedingungen';
                                break;
                            case COUNTRY_FORMAT_NL:
                                newUrl = 'https://cdn.klarna.com/1.0/shared/content/legal/terms/0/nl_nl/invoice?fee='+fee;
                                newInvoice = 'Factuurvoorwaarden';
                                break;
                            default:
                                newUrl = 'https://cdn.klarna.com/1.0/shared/content/legal/terms/0/en_us/invoice?';
                                newInvoice = 'Invoice payment terms';
                                break;
                        }

                        this.termsUrl(newUrl);
                        this.invoiceText(newInvoice);
                    };

                    if (quote.billingAddress()) {
                        this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                        this.updateTermsUrl(quote.billingAddress().countryId);
                    }

                    quote.billingAddress.subscribe(
                        function(newAddress) {
                            if (this.getCode() !== this.isChecked() ||
                                !newAddress ||
                                !newAddress.getKey()
                            ) {
                                return;
                            }

                            if (newAddress.firstname !== this.firstName || newAddress.lastname !== this.lastName) {
                                this.updateBillingName(newAddress.firstname, newAddress.lastname);
                            }

                            if (newAddress.countryId !== this.country) {
                                this.updateTermsUrl(newAddress.countryId);
                            }
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

                    /**
                     * Check if TelephoneNumber is filled in. If not - show field
                     */
                    this.hasTelephoneNumber = ko.computed(
                        function () {
                            var telephone = quote.billingAddress() ? quote.billingAddress().telephone : null;
                            return telephone != '' && telephone != '-';
                        }
                    );

                    /**
                     * Repair IBAN value to uppercase
                     */
                    this.bankaccountnumber.extend({ uppercase: true });

                    /**
                     * Validation on the input fields
                     */

                    var runValidation = function () {
                        $('.' + this.getCode() + ' [data-validate]').filter(':not([name*="agreement"])').valid();
                        this.selectPaymentMethod();
                    };

                    this.telephoneNumber.subscribe(runValidation,this);
                    this.dateValidate.subscribe(runValidation,this);
                    this.CocNumber.subscribe(runValidation,this);
                    this.CompanyName.subscribe(runValidation,this);
                    this.bankaccountnumber.subscribe(runValidation,this);
                    this.termsValidate.subscribe(runValidation,this);
                    this.genderValidate.subscribe(runValidation,this);
                    this.dummy.subscribe(runValidation,this);

                    /**
                     * Check if the required fields are filled. If so: enable place order button (true) | if not: disable place order button (false)
                     */
                    this.buttoncheck = ko.computed(
                        function () {
                            this.telephoneNumber();
                            this.selectedGender();
                            this.BillingName();
                            this.dateValidate();
                            this.bankaccountnumber();
                            this.termsValidate();
                            this.CocNumber();
                            this.CompanyName();
                            this.genderValidate();
                            this.dummy();
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
                        this.updateTermsUrl(quote.billingAddress().countryId);
                    }

                    return true;
                },

                /**
                 * Run validation function
                 */

                validate: function () {
                    return $('.' + this.getCode() + ' [data-validate]:not([name*="agreement"])').valid();
                },

                getData: function () {
                    var business = this.businessMethod;

                    if (business == BUSINESS_METHOD_BOTH) {
                        business = this.selectedBusiness();
                    }

                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone" : this.telephoneNumber(),
                            "customer_gender" : this.genderValidate(),
                            "customer_billingName" : this.BillingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_iban": this.bankaccountnumber(),
                            "termsCondition" : this.termsValidate(),
                            "CompanyName" : this.CompanyName(),
                            "COCNumber" : this.CocNumber(),
                            "selectedBusiness" : business
                        }
                    };
                }
            }
        );
    }
);
