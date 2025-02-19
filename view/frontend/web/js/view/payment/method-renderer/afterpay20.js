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
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'ko',
        'buckaroo/checkout/datepicker',
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
        datePicker,
        customer,
        selectBillingAddress,
    ) {
        'use strict';

        $.validator.addMethod('validateCOC', function (value) {
            if (!value) {
                return false;
            }

            if (value.match(/[^0-9]/)) {
                return false;
            }

            return value.length <= 8;
        },
            $.mage.__('Invalid COC number'));

        const bkIsPhoneValid = function (value) {
            if (quote.billingAddress() === null) {
                return false;
            }
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

            value = value.replace(/^(\+|00)/, '');
            value = value.replace(/(\(0\)|\s|-)/g, '');

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
        }

        $.validator.addMethod(
            'phoneValidation',
            bkIsPhoneValid,
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
                        0,
                        0,
                        0
                    );
                    return ~~((Date.now() - birthday) / (31557600000)) >= 18;
                }
            }
            return false;
        },
            $.mage.__('You should be at least 18 years old.'));

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_afterpay20',
                    activeAddress: null,
                    identificationNumber: null,
                    country: '',
                    customerCoc: '',
                    dateValidate: null,
                    termsSelected: true,
                    identificationValidate: null,
                    phone: null,
                    showIdentification: false,
                    showCOC: false,
                    value: "",
                },
                redirectAfterPlaceOrder: true,
                isCustomerLoggedIn: customer.isLoggedIn,
                dp: datePicker,


                initObservable: function () {
                    this._super().observe(
                        [
                            'dateValidate',
                            'termsSelected',
                            'identificationValidate',
                            'phone',
                            'customerCoc',
                            'value'
                        ]
                    );

                    this.showFinancialWarning = ko.computed(
                        function () {
                            return quote.billingAddress() !== null &&
                                quote.billingAddress().countryId == 'NL' &&
                                this.buckaroo.showFinancialWarning
                        },
                        this
                    );

                    this.activeAddress = ko.computed(
                        function () {
                            if (quote.billingAddress()) {
                                return quote.billingAddress();
                            }
                            return quote.shippingAddress();
                        }
                    );

                    this.country = ko.computed(
                        function () {
                            return this.activeAddress().countryId;
                        },
                        this
                    );

                    this.showCOC = ko.computed(
                        function () {

                            let shipping = quote.shippingAddress();
                            let billing = quote.billingAddress();

                            return this.buckaroo.is_b2b && (
                                (shipping && shipping.countryId == 'NL' && shipping.company && shipping.company.trim().length > 0) ||
                                (billing && billing.countryId == 'NL' && billing.company && billing.company.trim().length > 0)
                            )
                        },
                        this
                    );


                    this.showPhone = ko.computed(function () {
                        return (!this.isCustomerLoggedIn() && this.isOsc()) ||
                            quote.billingAddress() === null ||
                            (['NL', 'BE'].indexOf(quote.billingAddress().countryId) !== -1 && !bkIsPhoneValid(quote.billingAddress().telephone))
                    },
                        this);

                    this.showNLBEFields = ko.computed(
                        function () {
                            return !this.showCOC() &&
                                (
                                    ['NL', 'BE', 'DE'].indexOf(this.country()) != -1 ||
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

                    this.termsUrl = ko.computed(
                        function () {
                            return this.getTermsUrl(this.country(), this.showCOC());
                        },
                        this
                    );

                    return this;
                },


                /**
                 * Place order.
                 *
                 * @todo To override the script used for placeOrderAction, we need to override the placeOrder method
                 *          on our parent class (buckaroo/checkout/payment/default) so we can
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
                        dpdCookies.forEach(function (item) {
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
                        this.messageContainer.addErrorMessage(
                            {
                                'message': $t("Please make sure all fields are filled in correctly before proceeding.")
                            }
                        );
                    }
                    return false;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_telephone": this.phone(),
                            "customer_identificationNumber": this.identificationValidate(),
                            "customer_DoB": this.dateValidate(),
                            "termsCondition": this.termsSelected(),
                            "customer_coc": this.customerCoc(),
                        }
                    };
                },

                getTermsUrl: function (country, b2b) {
                    let lang = 'nl_nl';
                    let url = 'https://documents.riverty.com/terms_conditions/payment_methods/invoice';
                    const cc = country.toLowerCase()

                    if (b2b === false) {
                        if (country === 'BE') {
                            lang = 'be_nl';
                        }

                        if (['NL', 'DE'].indexOf(country) !== -1) {
                            lang = `${cc}_${cc}`;
                        }

                        if (['AT', 'CH'].indexOf(country) !== -1) {
                            const cc = country.toLowerCase()
                            lang = `${cc}_de`;
                        }

                        if (['DK', 'FI', 'SE', 'NO'].indexOf(country) !== -1) {
                            const cc = country.toLowerCase()
                            lang = `${cc}_en`;
                        }
                    } else {
                        url = 'https://documents.riverty.com/terms_conditions/payment_methods/b2b_invoice';
                        if (['NL', 'DE'].indexOf(country) !== -1) {
                            lang = `${cc}_${cc}`;
                        }

                        if (['AT', 'CH'].indexOf(country) !== -1) {
                            lang = `${cc}_de`;
                        }
                    }

                    return `${url}/${lang}/`;
                },

                getFrenchTos: function () {
                    return $.mage
                        .__('(Or click here for the French translation: <a target="_blank" href="%s">terms and conditions</a>.)')
                        .replace('%s', 'https://documents.riverty.com/terms_conditions/payment_methods/invoice/be_fr/');
                },

                isOsc: function () {
                    return document.querySelector('.action.primary.checkout.iosc-place-order-button');
                }
            }
        );
    }
);
