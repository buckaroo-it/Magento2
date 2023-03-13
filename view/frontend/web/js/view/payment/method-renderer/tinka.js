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
                defaults                : {
                    template : 'Buckaroo_Magento2/payment/buckaroo_magento2_tinka',
                    billingName: null,
                    country: '',
                    dateValidate: null,
                    showNLBEFields: true,
                    activeAddress: null,
                    value:'',
                    isDateValid: false,
                    showPhone: false,
                    phone: null
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
                            'dateValidate',
                            'value',
                            'isDateValid',
                            'phone'
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

                    this.showNLBEFields = ko.computed(
                        function () {
                            return this.country() === 'NL' || this.country() === 'BE'
                        },
                        this
                    );

                    this.billingName = ko.computed(
                        function () {
                            return this.activeAddress().firstname + " " + this.activeAddress().lastname;
                        },
                        this
                    );

                    this.buttoncheck = ko.computed(
                        function () {
                            if(this.showNLBEFields()) {
                                return this.isDateValid();
                            }
                            return true;
                        },
                        this
                    );

                    this.showPhone =  ko.computed(
                        function () {
                            return this.activeAddress().telephone === null ||
                            this.activeAddress().telephone === undefined ||
                            this.activeAddress().telephone.trim().length === 0
                        },
                        this
                    );

                    this.dateValidate.subscribe(this.isDobValid,this);
                    return this;
                },

                isDobValid() {
                    let isDateValid = false;

                    if($(`#buckaroo_magento2_tinka_DoB`).length) {
                        isDateValid = $(`#buckaroo_magento2_tinka_DoB`).valid();
                    }
                    this.isDateValid(isDateValid);
                },

                validateField(element) {
                    return $(element).valid();
                },



                getActiveValidationFields() {
                    let fields = [];
                    if(this.showPhone()) {
                        fields.push('buckaroo_magento2_tinka_Telephone')
                    }

                    if(this.showNLBEFields()) {
                        fields.push('buckaroo_magento2_tinka_DoB')
                    }
                    return fields;
                },


                validate() {
                    let fields = this.getActiveValidationFields();

                    const valid = fields.map(
                        function(field) {
                            return this.validateField(field)
                        },
                        this
                    ).reduce(
                        function(prev, cur) {
                            return prev && cur
                        },
                        true
                    );
                    return valid;
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

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_billingName" : this.billingName(),
                            "customer_DoB" : this.dateValidate(),
                            "customer_telephone": this.phone()
                        }
                    };
                }
            }
        );
    }
);
