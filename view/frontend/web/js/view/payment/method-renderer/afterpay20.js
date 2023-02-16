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
        'mage/translate',
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
        "mage/cookies",
    ],
    function (
        $,
        $t,
        Component,
        additionalValidators,
        placeOrderAction,
        quote,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon,
        customer,
        selectBillingAddress,
    ) {
        'use strict';

        $.validator.addMethod('validateCOC', function (value) {
            var countryId = quote.billingAddress().countryId;
            var lengths = {
                'NL':  8,
                'BE': 10
            };
            if (!value) {
                return false;
            }

            if (value.match(/[^0-9]/)) {
                return false;
            }

            if (!lengths.hasOwnProperty(countryId)) {
                return false;
            }

            return value.length <= lengths[countryId];
        },
        $.mage.__('Invalid COC number')
        );

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

        $.validator.addMethod('validateAge', function (value) {
            if (value && (value.length > 0)) {
                var dateReg = /^\d{2}[./-]\d{2}[./-]\d{4}$/;
                if (value.match(dateReg)) {
                    var birthday = +new Date(
                        value.substr(6, 4),
                        value.substr(3, 2) - 1,
                        value.substr(0, 2),
                        0, 0, 0
                    );
                    return ~~((Date.now() - birthday) / (31557600000)) >= 18;
                }
            }
            return false;
        },
        $.mage.__('You should be at least 18 years old.')
        );

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_afterpay20',
                    activeAddress: null,
                    identificationNumber: null,
                    billingName: null,
                    country: '',
                    customerCoc:'',
                    dateValidate: null,
                    termsUrl: 'https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/',
                    termsValidate: false,
                    identificationValidate: null,
                    phoneValidate: null,
                    showCOC: false,
                    value:"",
                    buttoncheck: true
                },
                redirectAfterPlaceOrder : true,
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.afterpay20.paymentFeeLabel,
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                isCustomerLoggedIn: customer.isLoggedIn,
                isB2B: window.checkoutConfig.payment.buckaroo.afterpay20.is_b2b,
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
                            'dateValidate',
                            'termsValidate',
                            'identificationValidate',
                            'phoneValidate',
                            'customerCoc',
                            'value',
                            'buttoncheck'
                        ]
                    );

                    this.activeAddress = ko.computed(
                        function() {
                            if(quote.billingAddress()) {
                                return quote.billingAddress();
                            }
                            return quote.shippingAddress();
                        }
                    );
                    
                    this.country = ko.computed(
                        function() {
                            return this.activeAddress().countryId;
                        },
                        this
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
                    );


                    this.showPhone = ko.computed(function () {
                            return  (!this.isCustomerLoggedIn() && this.isOsc()) ||
                            (this.country() === 'NL' || this.country() === 'BE') ||
                            this.phoneValidate()
                        },
                        this
                    );

                    this.showNLBEFields = ko.computed(
                        function () {
                            return !this.showCOC() &&
                            (
                                (this.country() === 'NL' || this.country() === 'BE') ||
                                (!this.isCustomerLoggedIn() && this.isOsc())
                            );
                        },
                        this
                    );

                    this.showIdentification = ko.computed(
                        function () {
                            return this.country() === 'FI';
                        },
                        this
                    );

                    this.showFrenchTos = ko.computed(
                        function () {
                            return this.country() === 'BE'
                        },
                        this
                    );

                    this.billingName = ko.computed(
                        function () {
                            return this.activeAddress().firstname + " " + this.activeAddress().lastname;
                        },
                        this
                    );

                    this.termsUrl = ko.computed(
                        function () {
                            return this.getTermsUrl(this.country());
                        },
                        this
                    );
                    
                    this.activeAddress.subscribe(function(address) {
                        if(address.phone) {
                            this.phoneValidate(address.phone)
                        }
                    }, this)

                    this.dateValidate.subscribe(function() {
                       this.validateField('DoB');
                    }, this);
                    this.customerCoc.subscribe(function() {
                        this.validateField('coc');
                     }, this);
                    this.termsValidate.subscribe(function() {
                       this.validateField('TermsCondition');
                    }, this);
                    this.identificationValidate.subscribe(function() {
                       this.validateField('Identificationnumber');
                    }, this);
                    this.phoneValidate.subscribe(function() {
                       this.validateField('Telephone');
                    }, this);

                    return this;
                },

                validateAll() {
                    let fields = ['TermsCondition'];
                    if(this.showPhone()) {
                        fields.push('Telephone')
                    }

                    if(this.showIdentification()) {
                        fields.push('Identificationnumber')
                    }

                    if(this.showCOC()) {
                        fields.push('coc')
                    }

                    if(this.showNLBEFields()) {
                        fields.push('DoB')
                    }

                    const valid = fields.map(
                        function(field) {
                            return this.validateField(field)
                        },
                        this
                    ).reduce(
                        function(prev, cur) {
                            return prev && cur
                        },
                        this
                    );
                    return valid;
                },

                validateField: function(id) {
                    this.buttoncheck(true);
                    this.messageContainer.clear();
                    const isValid = $(`#buckaroo_magento2_afterpay20_${id}`).valid();
                    this.buttoncheck(isValid);
                    return isValid;
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

                    if (this.validateAll() && additionalValidators.validate()) {
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
                    } else {
                        this.buttoncheck(false);
                        this.messageContainer.addErrorMessage(
                            {
                                'message': $t("Please make sure all fields are filled in correctly before proceeding.")
                            }
                        );
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
                    if (
                        document.querySelector('.action.primary.checkout')
                        &&
                        !$('.action.primary.checkout').is(':visible')
                    ) {
                        return true;
                    }
                    var elements = $('.' + this.getCode() + ' .payment [data-validate]:not([name*="agreement"])');
                    if (elements.length) {
                        return elements.valid();
                    }
                    return true;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone": this.phoneValidate(),
                            "customer_identificationNumber": this.identificationValidate(),
                            "customer_billingName": this.billingName(),
                            "customer_DoB": this.dateValidate(),
                            "termsCondition": this.termsValidate(),
                            "customer_coc": this.customerCoc(),
                        }
                    };
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
