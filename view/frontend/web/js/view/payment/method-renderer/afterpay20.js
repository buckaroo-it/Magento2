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
        'Magento_Customer/js/model/customer',
        'Magento_Ui/js/lib/knockout/bindings/datepicker',
        'Magento_Checkout/js/action/select-billing-address',
        "mage/cookies"
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
        checkoutCommon,
        customer,
        selectBillingAddress
    ) {
        'use strict';

        $.validator.addMethod('phoneValidation', function (value) {
                var countryId = quote.billingAddress().countryId;
                var lengths = {
                    'NL': {
                        min: 10,
                        max: 12
                    },
                    'BE': {
                        min: 9,
                        max: 12
                    },
                    'DE': {
                        min: 11,
                        max: 14
                    }
                };
                if (!value) {
                    return false;
                }

                value = value.replace(/^\+|(00)/, '');
                value = value.replace(/\(0\)|\s|-/g, '');

                if (value.match(/\+/)) {
                    return false;
                }

                if (value.match(/[^0-9]/)) {
                    return false;
                }

                if (lengths.hasOwnProperty(countryId)) {
                    if (lengths[countryId].min && (value.length < lengths[countryId].min)) {
                        return false;
                    }
                    if (lengths[countryId].max && (value.length > lengths[countryId].max)) {
                        return false;
                    }
                }

                return true;
            },
            $.mage.__('Phone number should be correct.')
        );

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_afterpay20',
                    identificationNumber: null,
                    firstName: '',
                    lastName: '',
                    CustomerName: null,
                    BillingName: null,
                    country: '',
                    customerCoc:'',
                    dateValidate: null,
                    termsUrl: 'https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsValidate: false,
                    identificationValidate: null,
                    phoneValidate: null,
                    showNLBEFieldsValue: true,
                    showIdentificationValue: null,
                    showFrenchTosValue: null,
                    showPhoneValue: null,
                    showCOC: false,
                    value:""
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.afterpay20.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                currentCustomerAddressId : null,
                isCustomerLoggedIn: customer.isLoggedIn,
                isB2B: window.checkoutConfig.payment.buckaroo.afterpay20.is_b2b,
                /**
                 * @override
                 */
                initialize: function (options) {
                    var self = this;
                    if (checkoutData.getSelectedPaymentMethod() == options.index) {
                        window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                    }

                    return this._super(options);
                },

                initObservable: function () {
                    this._super().observe(
                        [
                            'firstname',
                            'lastname',
                            'CustomerName',
                            'BillingName',
                            'country',
                            'dateValidate',
                            'termsUrl',
                            'termsValidate',
                            'identificationValidate',
                            'phoneValidate',
                            'dummy',
                            'showNLBEFieldsValue',
                            'showIdentificationValue',
                            'showFrenchTosValue',
                            'showPhoneValue',
                            'customerCoc',
                            'showCOC',
                            'value'
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
                        if (this.isCustomerLoggedIn() && !this.isOsc() && (this.country === null)) {
                            return;
                        }

                        this.showNLBEFieldsValue(false);
                        this.showIdentificationValue(false);
                        this.showPhoneValue(false);

                        if ((!this.isCustomerLoggedIn() && this.isOsc()) || ((this.country === 'NL' || this.country === 'BE'))) {
                            this.showNLBEFieldsValue(true);
                        }

                        if (this.country === 'FI') {
                            this.showIdentificationValue(true);
                        }

                        if (
                            (!this.isCustomerLoggedIn() && this.isOsc())
                            ||
                            (this.country === 'NL' || this.country === 'BE')
                            ||
                            this.phoneValidate()
                        ) {
                            this.showPhoneValue(true);
                        }
                    };

                    /**
                     * Observe customer first & lastname
                     * bind them together, so they could appear in the frontend
                     */
                    this.updateBillingName = function(firstname, lastname) {
                        if (!firstname && !lastname) {
                            return false;
                        }

                        this.firstName = firstname;
                        this.lastName = lastname;

                        this.CustomerName = ko.computed(
                            function () {
                                return (this.firstName ? this.firstName : "") + (this.lastName ? " " + this.lastName : "");
                            },
                            this
                        );

                        this.BillingName(this.CustomerName());
                    };

                    if (quote.billingAddress()) {
                        this.updateBillingName(quote.billingAddress().firstname, quote.billingAddress().lastname);
                        this.updateTermsUrl(quote.billingAddress().countryId);
                        this.phoneValidate(quote.billingAddress().telephone);
                        this.updateShowFields();
                    }

                    quote.shippingAddress.subscribe(
                        function(newAddress) {
                            if (!this.isCustomerLoggedIn() && this.isOsc()) {
                                if (newAddress.telephone) {
                                    this.phoneValidate();
                                }
                                this.updateBillingName(newAddress.firstname, newAddress.lastname);
                            }
                        }.bind(this)
                    );

                    quote.billingAddress.subscribe(
                        function(newAddress) {
                            if (this.getCode() !== this.isChecked() ||
                                !newAddress ||
                                !newAddress.getKey()
                            ) {
                                return;
                            }

                            if (this.currentCustomerAddressId != newAddress.getKey()) {
                                this.currentCustomerAddressId = newAddress.getKey();
                                this.phoneValidate();
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
                    this.showCOC = ko.computed(
                        function() {

                            let shipping = quote.shippingAddress();
                            let billing = quote.billingAddress();

                            return this.isB2B && (
                                (shipping && shipping.countryId == 'NL' && shipping.company && shipping.company.trim().length > 0) ||
                                (billing && billing.countryId == 'NL' && billing.company && billing.company.trim().length > 0)
                            )
                            
                        },
                        this
                    )

                    /**
                     * Validation on the input fields
                     */

                    var runValidation = function () {
                        var elements = $('.' + this.getCode() + ' .payment [data-validate]').filter(':not([name*="agreement"])');

                        let self = this;
                        if(elements !== undefined){
                            elements.valid();
                        }

                        if (this.calculateAge(this.dateValidate()) >= 18) {
                            $('#' + this.getCode() + '_DoB-error').hide();
                            $('#' + this.getCode() + '_DoB').removeClass('mage-error');
                        } else {
                            setTimeout(function () {
                                $('#' + self.getCode() + '_DoB-error').show();
                                $('#' + self.getCode() + '_DoB').addClass('mage-error');
                            }, 200);
                        }
                    };

                    this.dateValidate.subscribe(runValidation, this);
                    this.termsValidate.subscribe(runValidation, this);
                    this.identificationValidate.subscribe(runValidation, this);
                    this.phoneValidate.subscribe(runValidation, this);
                    this.dummy.subscribe(runValidation, this);

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
                                (!this.showIdentification() || this.identificationValidate() !== null) &&
                                this.BillingName() !== null &&
                                (!this.showNLBEFields() || this.dateValidate() !== null) &&
                                (!this.showPhone() || ((this.phoneValidate() !== null))) &&
                                this.termsValidate() !== false &&
                                this.validate() &&
                                (
                                    (this.country != 'NL' && this.country != 'BE')
                                    ||
                                    (this.calculateAge(this.dateValidate()) >= 18)
                                )
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

                    if (!quote.billingAddress()) {
                        selectBillingAddress(quote.shippingAddress());
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);

                        //resave dpd cookies with '/' path , otherwise in some cases they won't be available at backend side
                        var dpdCookies = [
                            'dpd-selected-parcelshop-street',
                            'dpd-selected-parcelshop-zipcode',
                            'dpd-selected-parcelshop-city',
                            'dpd-selected-parcelshop-country'
                        ];
                        dpdCookies.forEach(function(item) {
                            var value = $.mage.cookies.get(item);
                            if (value) {
                                $.mage.cookies.clear(item);
                                $.mage.cookies.set(item, value, { path: '/' });
                            }
                        });

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
                        this.showPhone();
                    }

                    return true;
                },

                /**
                 * Run validation function
                 */

                validate: function () {
                    if (
                        document.querySelector('.action.primary.checkout')
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
                            "customer_telephone": this.phoneValidate(),
                            "customer_identificationNumber": this.identificationValidate(),
                            "customer_billingName": this.BillingName(),
                            "customer_DoB": this.dateValidate(),
                            "termsCondition": this.termsValidate(),
                            "customer_coc": this.customerCoc(),
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

                    this.termsUrl(newUrl);
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
                },

                isOsc: function () {
                    return document.querySelector('.action.primary.checkout.iosc-place-order-button');
                }
            }
        );
    }
);
