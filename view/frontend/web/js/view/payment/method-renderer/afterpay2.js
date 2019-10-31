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
        var BUSINESS_METHOD_B2C = 1;
        var BUSINESS_METHOD_B2B = 2;
        var BUSINESS_METHOD_BOTH = 3;

        var PAYMENT_METHOD_ACCEPTGIRO = 1;
        var PAYMENT_METHOD_DIGIACCEPT = 2;


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
                    template : 'TIG_Buckaroo/payment/tig_buckaroo_afterpay2',
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
                    termsUrl: 'https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsValidate: false,
                    genderValidate: null
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.afterpay2.paymentFeeLabel,
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
                            'dummy'
                        ]
                    );

                    this.businessMethod = window.checkoutConfig.payment.buckaroo.afterpay2.businessMethod;
                    this.paymentMethod  = window.checkoutConfig.payment.buckaroo.afterpay2.paymentMethod;

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
                        this.country = country;
                        var newUrl = '';

                        switch (this.paymentMethod) {
                            case PAYMENT_METHOD_ACCEPTGIRO:
                                newUrl = getAcceptgiroUrl();
                                break;
                            case PAYMENT_METHOD_DIGIACCEPT:
                                newUrl = getDigiacceptUrl();
                                break;
                            default:
                                newUrl = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
                                break;
                        }

                        this.termsUrl(newUrl);
                    };

                    var getAcceptgiroUrl = function() {
                        if (this.country === 'NL') {
                            return 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
                        }

                        return 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
                    }.bind(this);

                    var getDigiacceptUrl = function() {
                        var businessMethod = getBusinessMethod();
                        var url = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';

                        if (this.country === 'BE' && businessMethod == BUSINESS_METHOD_B2C) {
                            url = 'https://www.afterpay.be/be/footer/betalen-met-afterpay/betalingsvoorwaarden';
                        }

                        if (this.country === 'NL' && businessMethod == BUSINESS_METHOD_B2C) {
                            url = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
                        }

                        if (this.country === 'NL' && businessMethod == BUSINESS_METHOD_B2B) {
                            url = 'https://www.afterpay.nl/nl/algemeen/zakelijke-partners/betalingsvoorwaarden-zakelijk';
                        }

                        return url;
                    }.bind(this);

                    var getBusinessMethod = function() {
                        var businessMethod = BUSINESS_METHOD_B2C;

                        if (this.businessMethod == BUSINESS_METHOD_B2B
                            || (this.businessMethod == BUSINESS_METHOD_BOTH && this.selectedBusiness() == BUSINESS_METHOD_B2B)
                        ) {
                            businessMethod = BUSINESS_METHOD_B2B;
                        }

                        return businessMethod;
                    }.bind(this);

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

                    var updateSelectedBusiness = function () {
                        this.updateTermsUrl(this.country);
                    };

                    this.selectedBusiness.subscribe(updateSelectedBusiness, this);

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
                        $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])').valid();
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
                     * Create a function to check if all the required fields, in specific conditions, are filled in.
                     * Within checkB2C - hide IBAN unless paymentMethod = Acceptgiro (1).
                     * Within checkB2B - show all possible fields except IBAN.
                     */

                    var checkB2C = function () {
                        return (
                        (this.telephoneNumber() !== null || this.hasTelephoneNumber) &&
                        this.selectedGender() !== null &&
                        this.BillingName() !== null &&
                        this.dateValidate() !== null &&
                        this.termsValidate() !== false &&
                        this.genderValidate() !== null &&
                        (
                        (
                        this.paymentMethod == PAYMENT_METHOD_ACCEPTGIRO &&
                        this.bankaccountnumber().length > 0
                        ) ||
                        this.paymentMethod == PAYMENT_METHOD_DIGIACCEPT
                        ) &&
                        this.validate()
                        );
                    };

                    var checkB2B = function () {
                        return (
                        (this.telephoneNumber() !== null || this.hasTelephoneNumber) &&
                        this.selectedGender() !== null &&
                        this.BillingName() !== null &&
                        this.dateValidate() !== null &&
                        this.CocNumber() !== null &&
                        this.CompanyName() !== null &&
                        this.termsValidate() !== false &&
                        this.genderValidate() !== null &&
                        this.validate()
                        );
                    };

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

                            /**
                             * Run If Else function to select the right fields to validate.
                             * Other fields will be ignored.
                             */
                            if (this.businessMethod == BUSINESS_METHOD_B2C
                                || (this.businessMethod == BUSINESS_METHOD_BOTH
                                && this.selectedBusiness() == BUSINESS_METHOD_B2C)
                            ) {
                                return checkB2C.bind(this)();
                            } else {
                                return checkB2B.bind(this)();
                            }
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
                    return $('.' + this.getCode() + ' .payment [data-validate]:not([name*="agreement"])').valid();
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
