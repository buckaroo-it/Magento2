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
define(
    [
        'jquery',
        'ko',
        'mage/url',
        'Magento_Checkout/js/model/resource-url-manager',
        'buckaroo/applepay/shipping-handler',
        'Magento_Checkout/js/model/shipping-rate-service',
        'mage/translate',
        'BuckarooSDK'
    ],
    function (
        $,
        ko,
        urlBuilder,
        resourceUrlManager,
        shippingHandler
    ) {
        'use strict';

        var transactionResult = ko.observable(null);

        return {
            transactionResult : transactionResult,
            canShowMethod: ko.observable(null),
            applepayOptions : null,
            isOnCheckout : false,
            quote : null,
            shippingGroups: {},
            payment: null,

            showPayButton: function () {
                if (typeof window.checkoutConfig === 'undefined') {
                    return;
                }

                BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid).then(
                    function (applePaySupported) {
                        if (applePaySupported) {
                            this.generateApplepayOptions();

                            this.payment = new BuckarooSdk.ApplePay.ApplePayPayment('#apple-pay-wrapper', this.applepayOptions);
                            this.payment.showPayButton('black', 'buy');
                        }
                    }.bind(this)
                );
            },

            updateOptions: function () {
                if (this.payment === null) {
                    return;
                }

                this.generateApplepayOptions();
                this.payment.options = this.applepayOptions;
            },

            canShowApplePay:function () {
                BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid).then(
                    function (applePaySupported) {
                        this.canShowMethod(applePaySupported);
                    }.bind(this)
                );

                return this.canShowMethod();
            },

            /**
             * @param newQuote
             */
            setQuote: function (newQuote) {
                this.quote = newQuote;
            },

            /**
             * @param isOnCheckout
             */
            setIsOnCheckout: function (isOnCheckout) {
                this.isOnCheckout = isOnCheckout;
            },

            generateApplepayOptions: function () {
                var self = this;
                var lineItemsType = 'pending';
                var shippingMethods = self.availableShippingMethodInformation();
                var shippingContactCallback = self.onSelectedShippingContact.bind(this);
                var shipmentMethodCallback = self.onSelectedShipmentMethod.bind(this);
                var requiredContactFields = ["name", "postalAddress", "phone"];

                var country = window.checkoutConfig.payment.buckaroo.applepay.cultureCode.toUpperCase();
                if (null !== this.quote.shippingAddress()) {
                    country = this.quote.shippingAddress().countryId;
                }

                if (!this.isOnCheckout && !window.isCustomerLoggedIn) {
                    requiredContactFields.push("email");
                }

                if (this.isOnCheckout) {
                    lineItemsType = 'final';
                    shippingMethods = [];
                    shippingContactCallback = null;
                    shipmentMethodCallback = null;
                    requiredContactFields = [];
                }

                this.applepayOptions = new BuckarooSdk.ApplePay.ApplePayOptions(
                    window.checkoutConfig.payment.buckaroo.applepay.storeName,
                    country,
                    window.checkoutConfig.payment.buckaroo.applepay.currency,
                    window.checkoutConfig.payment.buckaroo.applepay.cultureCode,
                    window.checkoutConfig.payment.buckaroo.applepay.guid,
                    self.processLineItems(lineItemsType),
                    self.processTotalLineItems(lineItemsType),
                    "shipping",
                    shippingMethods,
                    self.captureFunds.bind(this),
                    shipmentMethodCallback,
                    shippingContactCallback,
                    requiredContactFields,
                    requiredContactFields
                );
            },

            /**
             * @param type
             *
             * @returns {{amount: string, label, type: string}[]}
             */
            processLineItems: function (type = 'final') {
                var subTotal = '0.00';
                var shippingInclTax = '0.00';
                var totals = this.getQuoteTotals();

                if ('subtotal' in totals && 'shipping' in totals) {
                    subTotal = parseFloat(totals['subtotal']).toFixed(2);
                    shippingInclTax = parseFloat(totals['shipping']).toFixed(2);
                }

                var lineItems = [
                    {label: $.mage.__('Subtotal'), amount: subTotal, type: type},
                    {label: $.mage.__('Delivery costs'), amount: shippingInclTax, type: type}
                ];

                if ('discount' in totals && totals['discount'] < 0) {
                    var discountTotal = parseFloat(totals['discount']).toFixed(2);
                    lineItems.push({label: $.mage.__('Discount'), amount: discountTotal, type: type});
                }

                return lineItems;
            },

            /**
             * @param type
             *
             * @returns {{amount: string, label: *, type: string}}
             */
            processTotalLineItems: function (type = 'final') {
                var grandTotal = '0.00';
                var storeName = window.checkoutConfig.payment.buckaroo.applepay.storeName;
                var totals = this.getQuoteTotals();

                if ('grand_total' in totals) {
                    grandTotal = parseFloat(totals['grand_total']).toFixed(2);
                }

                return {label: storeName, amount: grandTotal, type: type};
            },

            /**
             * @returns {Array}
             */
            getQuoteTotals: function () {
                var totals = {};

                if (typeof this.quote.totals() === 'undefined') {
                    return totals;
                }

                totals['subtotal'] = this.quote.totals().subtotal_incl_tax;
                totals['discount'] = this.quote.totals().discount_amount;
                totals['shipping'] = this.quote.totals().shipping_incl_tax;
                totals['grand_total'] = this.quote.totals().grand_total;

                var customGrandTotal = this.quote.totals().custom_grand_total;

                if (customGrandTotal !== undefined && customGrandTotal) {
                    return totals;
                }

                var segments = this.quote.totals().total_segments;

                for (let i in segments) {
                    if (segments[i]['code'] === 'grand_total') {
                        totals['grand_total'] = segments[i]['value'];
                    }
                }

                return totals;
            },

            availableShippingMethodInformation: function () {
                var shippingMethods = [];

                $.each(this.shippingGroups, function (index, rate) {
                    var shippingInclTax = parseFloat(rate['price_incl_tax']).toFixed(2);

                    shippingMethods.push({
                        label: rate['carrier_title'],
                        amount: shippingInclTax,
                        identifier: rate['method_code'],
                        detail: rate['method_title']
                    });
                });

                return shippingMethods;
            },

            onSelectedShipmentMethod: function (event) {
                var newShippingMethod = this.shippingGroups[event.identifier];
                this.updateQuoteRate(newShippingMethod);

                var authorizationResult = {
                    newTotal: this.processTotalLineItems(),
                    newLineItems: this.processLineItems()
                };

                return Promise.resolve(authorizationResult);
            },

            onSelectedShippingContact: function (event) {
                var newShippingAddress = shippingHandler.setShippingAddress(event);
                this.updateShippingMethods(newShippingAddress);

                var authorizationResult = {
                    errors: [],
                    newShippingMethods: this.availableShippingMethodInformation(),
                    newTotal: this.processTotalLineItems(),
                    newLineItems: this.processLineItems()
                };

                return Promise.resolve(authorizationResult);
            },

            updateShippingMethods: function (address) {
                var serviceUrl = resourceUrlManager.getUrlForEstimationShippingMethodsForNewAddress(this.quote);
                var payload = JSON.stringify({
                    address: {
                        'street': address.street,
                        'city': address.city,
                        'region_id': address.regionId,
                        'region': address.region,
                        'country_id': address.countryId,
                        'postcode': address.postcode,
                        'firstname': address.firstname,
                        'lastname': address.lastname,
                        'company': address.company,
                        'telephone': address.telephone,
                        'custom_attributes': address.customAttributes,
                        'save_in_address_book': address.saveInAddressBook
                    }
                });

                $.ajax({
                    url: urlBuilder.build(serviceUrl),
                    type: 'POST',
                    data: payload,
                    global: false,
                    contentType: 'application/json',
                    async: false
                }).done(function (result) {
                    this.shippingGroups = {};
                    var firstLoop = true;

                    $.each(result, function (index, rate) {
                        this.shippingGroups[rate['method_code']] = rate;

                        if (firstLoop) {
                            this.updateQuoteRate(rate);
                            firstLoop = false;
                        }
                    }.bind(this));
                }.bind(this));
            },

            updateQuoteRate: function (newRate) {
                shippingHandler.selectShippingMethod(newRate);

                var subtotal = this.quote.totals().subtotal_incl_tax;
                this.quote.totals().shipping_incl_tax = newRate['price_incl_tax'];
                this.quote.totals().grand_total = subtotal + newRate['price_incl_tax'];
                this.quote.totals().custom_grand_total = true;
            },

            /**
             * @param payment
             * @returns {Promise<{errors: Array, status: *}>}
             */
            captureFunds: function (payment) {
                var authorizationResult = {
                    status: ApplePaySession.STATUS_SUCCESS,
                    errors: []
                };

                this.transactionResult(payment);

                return Promise.resolve(authorizationResult);
            }
        };
    }
);
