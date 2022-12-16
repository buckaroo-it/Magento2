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
        'buckaroo/checkout/common',
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
        selectPaymentMethodAction,
        checkoutCommon
    ) {
        'use strict';

        return Component.extend(
            {
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_tinka',
                    telephoneNumber: null,
                    identificationNumber: null,
                    firstName: '',
                    lastName: '',
                    CustomerName: null,
                    BillingName: null,
                    country: '',
                    dateValidate: null,
                    termsUrl: 'https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsValidate: false,
                    showNLBEFieldsValue: true,
                    showIdentificationValue: null,
                    showFrenchTosValue: null,
                    value:''
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.tinka.paymentFeeLabel,
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
                            'telephoneNumber',
                            'firstname',
                            'lastname',
                            'CustomerName',
                            'BillingName',
                            'country',
                            'dateValidate',
                            // 'termsUrl',
                            'termsValidate',
                            'dummy',
                            'showNLBEFieldsValue',
                            'showIdentificationValue',
                            'showFrenchTosValue',
                            'value'
                        ]
                    );

                    this.showNLBEFields = ko.computed(
                        function () {
                            if (this.showNLBEFieldsValue() !== null) {
                                return this.showNLBEFieldsValue();
                            }
                        },
                        this
                    );

                    this.showIdentification = ko.computed(
                        function () {
                            if (this.showIdentificationValue() !== null) {
                                return this.showIdentificationValue();
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
                        this.showIdentificationValue(false);

                        if (this.country === 'NL' || this.country === 'BE') {
                            this.showNLBEFieldsValue(true);
                        }

                        if (this.country === 'FI') {
                            this.showIdentificationValue(true);
                        }
                    };

                    /**
                     * Observe customer first & lastname
                     * bind them together, so they could appear in the frontend
                     */
                    this.updateBillingName = function (firstname, lastname) {
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
                        this.updateTermsUrl(quote.billingAddress().countryId);
                        this.updateShowFields();
                    }

                    quote.billingAddress.subscribe(
                        function (newAddress) {
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

                            this.updateShowFields();
                        }.bind(this)
                    );

                    /**
                     * Check if TelephoneNumber is filled in. If not - show field
                     */
                    this.hasTelephoneNumber = ko.computed(
                        function () {
                            var telephone = quote.billingAddress() ? quote.billingAddress().telephone : null;
                            return telephone != '' && telephone != '-' && telephone != null;
                        }
                    );

                    /**
                     * Validation on the input fields
                     */

                    var runValidation = function () {
                        let self = this;
                        var elements = $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])');
                        elements.valid();

                        if (this.calculateAge(this.dateValidate()) >= 18) {
                            $('#' + this.getCode() + '_DoB-error').hide();
                            $('#' + this.getCode() + '_DoB').removeClass('mage-error');
                        } else {
                            setTimeout(function () {
                                $('#' + self.getCode() + '_DoB-error').show();
                                $('#' + self.getCode() + '_DoB').addClass('mage-error');
                            },200);
                        }
                    };

                    this.telephoneNumber.subscribe(runValidation,this);
                    this.dateValidate.subscribe(runValidation,this);
                    this.termsValidate.subscribe(runValidation,this);
                    this.dummy.subscribe(runValidation,this);

                    this.calculateAge = function (specifiedDate) {
                        if (specifiedDate && (specifiedDate.length > 0)) {
                            var dateReg = /  ^ \d{2}[./ - ]\d{2}[./ - ]\d{4}$ / ;
                            if (specifiedDate.match(dateReg)) {
                                var birthday = +new Date(
                                    specifiedDate.substr(6, 4),
                                    specifiedDate.substr(3, 2) - 1,
                                    specifiedDate.substr(0, 2),
                                    0,
                                    0,
                                    0
                                );
                                return ~~((Date.now() - birthday) / (31557600000));
                            }
                        }
                        return false;
                    }

                    /**
                     * Check if the required fields are filled. If so: enable plasce order button (true) | if not: disable place order button (false)
                     */
                    this.buttoncheck = ko.computed(
                        function () {
                            return (this.telephoneNumber() !== null || this.hasTelephoneNumber()) &&
                                this.BillingName() !== null &&
                                (!this.showNLBEFields() || this.dateValidate() !== null) &&
                                (
                                    (this.country != 'NL' && this.country != 'BE')
                                    ||
                                    (this.calculateAge(this.dateValidate()) >= 18)
                                )
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

                magentoTerms: function () {
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
                    checkoutCommon.redirectHandle(response);
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
                    if (document.querySelector('.action.primary.checkout')
                        &&
                        !$('.action.primary.checkout').is(':visible')
                    ) {
                        return true;
                    }
                    var elements = $('.' + this.getCode() + ' .payment [data-validate]:not([name*="agreement"])');
                    return elements.valid();
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone" : this.telephoneNumber(),
                            "customer_billingName" : this.BillingName(),
                            "customer_DoB" : this.dateValidate(),
                            "termsCondition" : this.termsValidate(),
                        }
                    };
                },

                updateTermsUrl: function (country, tosCountry = false) {
                    this.country = country;
                    var newUrl = this.getTermsUrl(tosCountry);

                    this.showFrenchTosValue(false);

                    if (this.country === 'BE') {
                        this.showFrenchTosValue(true);
                    }

                    // this.termsUrl(newUrl);
                },

                getTermsUrl: function (tosCountry = false) {
                    var tosUrl = 'https://documents.myafterpay.com/consumer-terms-conditions/';

                    if (tosCountry !== false) {
                        tosUrl += tosCountry + '/';
                        return tosUrl;
                    }

                    switch (this.country) {
                        case 'DE':
                            tosCountry = 'de_de';
                            break;
                        case 'AT':
                            tosCountry = 'de_at';
                            break;
                        case 'NL':
                            tosCountry = 'nl_nl';
                            break;
                        case 'BE':
                            tosCountry = 'nl_be';
                            break;
                        case 'FI':
                            tosCountry = 'fi_fi';
                            break;
                        default:
                            tosCountry = 'en_nl';
                            break;
                    }

                    tosUrl += tosCountry + '/';

                    return tosUrl;
                },

                getFrenchTos: function () {
                    var tosUrl = this.getTermsUrl('fr_be');
                    var tosText = '(Or click here for the French translation: '
                        + '<a target="_blank" href="%s">terms and condition</a>.)';

                    tosText = $.mage.__(tosText);
                    tosText = tosText.replace('%s', tosUrl);

                    return tosText;
                }
            }
        );
    }
);
