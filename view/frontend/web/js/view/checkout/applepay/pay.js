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
        'underscore',
        'Magento_Checkout/js/model/shipping-rate-service',
        'mage/translate',
        'BuckarooSDK'
    ],
    function (
        $,
        ko,
        urlBuilder,
        resourceUrlManager,
        shippingHandler,
        _
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

            showPayButton: function (payMode) {
                //console.log('==============6', payMode); //ZAK
                this.payMode = payMode;
                this.productSelected = {};

                if (typeof window.checkoutConfig === 'undefined') {
                    return;
                }

                if ((this.payMode == 'product') || (this.payMode == 'cart')) {
                    this.setIsOnCheckout(false);
                } else {
                    this.setIsOnCheckout(true);
                }

                BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid).then(
                    function (applePaySupported) {
                        //console.log('==============8',applePaySupported); //ZAK

                        //move to inner block
                        if (this.payMode == 'product') {
                            this.initProductViewWatchers();
                        }

                        if (applePaySupported) {
                            this.generateApplepayOptions();

                            this.payment = new BuckarooSdk.ApplePay.ApplePayPayment('#apple-pay-wrapper', this.applepayOptions);
                            this.payment.showPayButton('black', 'buy');
                            if (this.payMode == 'product') {
                                this.payment.button.off("click");
                                var self = this;
                                this.payment.button.on("click", function (e) {
                                    //console.log('==============24'); //ZAK
                                    var dataForm = $('#product_addtocart_form');
                                    dataForm.validation('isValid');
                                    setTimeout(function () {
                                        if ($('.mage-error:visible').length == 0) {
                                            self.payment.beginPayment(e);
                                        }
                                    }, 100);
                                });
                            }
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

                var country = window.checkoutConfig.payment.buckaroo.applepay.country;
                //console.log('==============11', this.quote, country); //ZAK
                if (this.quote && (null !== this.quote.shippingAddress())) {
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
                    requiredContactFields = ["postalAddress"];
                }

                //console.log('==============13', country); //ZAK
                //console.log(requiredContactFields); //ZAK

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
            processLineItems: function (type = 'final', directTotals = false) {
                var subTotal = '0.00';
                var shippingInclTax = '0.00';
                var totals = directTotals ? directTotals : this.getQuoteTotals();

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
            processTotalLineItems: function (type = 'final', directTotals = false) {
                var grandTotal = '0.00';
                var storeName = window.checkoutConfig.payment.buckaroo.applepay.storeName;
                var totals = directTotals ? directTotals : this.getQuoteTotals();

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

                if (!this.quote || typeof this.quote.totals() === 'undefined') {
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

            timeoutRedirect: function (url = false) {
                //console.log('==============38', url);//ZAK
                /** Set Timeout to prevent Safari from crashing and reload window to show error in Magento. */
                setTimeout(
                    function() {
                        if (url) {
                            window.location.href = url;
                        } else {
                            window.location.reload();
                        }
                    }, 1500
                )
            },

            onSelectedShipmentMethod: function (event) {
                if ((this.payMode == 'product') || (this.payMode == 'cart')) {
                    var update = $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/updateShippingMethods'),
                        type: 'POST',
                        data: {
                            wallet: {
                                identifier: event.identifier
                            }
                        },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function(data, type) {
                            var result = JSON.parse(data);
                            if (result.success == 'true') {

                                var authorizationResult = {
                                    newTotal: this.processTotalLineItems('final', result.data.totals),
                                    newLineItems: this.processLineItems('final', result.data.totals)
                                };

                                //console.log('==============37');//ZAK
                                //console.log(authorizationResult);//ZAK

                                return JSON.stringify(authorizationResult);
                            } else {
                                this.timeoutRedirect();
                            }
                        }.bind(this),
                    })
                        .fail(function() {
                            this.timeoutRedirect();
                        }.bind(this));

                    return update;

                } else {

                    var newShippingMethod = this.shippingGroups[event.identifier];
                    this.updateQuoteRate(newShippingMethod);

                    var authorizationResult = {
                        newTotal: this.processTotalLineItems(),
                        newLineItems: this.processLineItems()
                    };

                    return Promise.resolve(authorizationResult);
                }
            },

            onSelectedShippingContact: function (event) {
                if (this.payMode == 'product') {

                    var update = $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/add'),
                        type: 'POST',
                        data: {
                            product: this.productSelected,
                            wallet: event
                        },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function(data, type) {
                            var result = JSON.parse(data);
                            if (result.success == 'true') {

                                this.shippingGroups = {};
                                $.each(result.data.shipping_methods, function (index, rate) {
                                    this.shippingGroups[rate['method_code']] = rate;
                                }.bind(this));

                                var authorizationResult = {
                                    errors: [],
                                    newShippingMethods: this.availableShippingMethodInformation(),
                                    newTotal: this.processTotalLineItems('final', result.data.totals),
                                    newLineItems: this.processLineItems('final', result.data.totals)
                                };

                                //console.log('==============30');//ZAK
                                //console.log(authorizationResult);//ZAK

                                return JSON.stringify(authorizationResult);
                            } else {
                                this.timeoutRedirect();
                            }
                        }.bind(this),
                    })
                    .fail(function() {
                        this.timeoutRedirect();
                    }.bind(this));

                    return update;
                } else if (this.payMode == 'cart') {

                    var update = $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/getShippingMethods'),
                        type: 'POST',
                        data: {
                            wallet: event
                        },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function(data, type) {
                            var result = JSON.parse(data);
                            if (result.success == 'true') {

                                this.shippingGroups = {};
                                $.each(result.data.shipping_methods, function (index, rate) {
                                    this.shippingGroups[rate['method_code']] = rate;
                                }.bind(this));

                                var authorizationResult = {
                                    errors: [],
                                    newShippingMethods: this.availableShippingMethodInformation(),
                                    newTotal: this.processTotalLineItems('final', result.data.totals),
                                    newLineItems: this.processLineItems('final', result.data.totals)
                                };

                                //console.log('==============53');//ZAK
                                //console.log(authorizationResult);//ZAK

                                return JSON.stringify(authorizationResult);
                            } else {
                                this.timeoutRedirect();
                            }
                        }.bind(this),
                    })
                    .fail(function() {
                        this.timeoutRedirect();
                    }.bind(this));

                    return update;
                } else {
                    var newShippingAddress = shippingHandler.setShippingAddress(event);
                    this.updateShippingMethods(newShippingAddress);
                }

                var authorizationResult = {
                    errors: [],
                    newShippingMethods: this.availableShippingMethodInformation(),
                    newTotal: this.processTotalLineItems(),
                    newLineItems: this.processLineItems()
                };

                return Promise.resolve(authorizationResult);
            },

            updateShippingMethods: function (address) {
                //console.log('==============16');//ZAK
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
                //console.log('==========pvo11',payment); //ZAK

                if ((this.payMode == 'product') || (this.payMode == 'cart')) {

                    var authorizationFailedResult = {
                        status: ApplePaySession.STATUS_FAILURE,
                        errors: []
                    };


                    var update = $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/saveOrder'),
                        type: 'POST',
                        data: {
                            payment: payment,
                            extra: this.getData(payment)
                        },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function(data, type) {
                            var result = JSON.parse(data);
                            if (result.success == 'true') {
                                if (result.data && result.data.RequiredAction !== undefined && result.data.RequiredAction.RedirectURL !== undefined) {
                                    this.timeoutRedirect(result.data.RequiredAction.RedirectURL);
                                } else {
                                    this.timeoutRedirect();
                                }
                                this.payMode = '';
                                return JSON.stringify(authorizationResult);
                            } else {
                                this.timeoutRedirect();
                            }
                        }.bind(this),
                    })
                    .fail(function() {
                        this.timeoutRedirect();
                    }.bind(this));

                    return update;

                } else {

                    this.transactionResult(payment);

                    return Promise.resolve(authorizationResult);
                }
            },

            getData: function (payment) {
                var transactionResult = payment;
                var transactionData = this.formatTransactionResponse(transactionResult);

                return {
                    "method": 'buckaroo_magento2_applepay',
                    "po_number": null,
                    "additional_data": {
                        "applepayTransaction" : transactionData,
                        "billingContact" : transactionResult && transactionResult.billingContact ?
                            JSON.stringify(transactionResult.billingContact) : ''
                    }
                };
            },

            /**
             * @param response
             * @returns {string|null}
             */
            formatTransactionResponse: function (response) {
                if (null === response || 'undefined' === response) {
                    return null;
                }

                var paymentData = response.token.paymentData;

                var formattedData = {
                    "paymentData": {
                        "version": paymentData.version,
                        "data": paymentData.data,
                        "signature": paymentData.signature,
                        "header": {
                            "ephemeralPublicKey": paymentData.header.ephemeralPublicKey,
                            "publicKeyHash": paymentData.header.publicKeyHash,
                            "transactionId": paymentData.header.transactionId,
                        }
                    }
                };

                return JSON.stringify(formattedData);
            },

            initProductViewWatchers: function () {
                //console.log('==============initProductViewWatchers'); //ZAK

                this.productSelected.id = $('.price-box').attr('data-product-id');
                this.productSelected.qty = $('#qty').val();
                var self = this;

                $('#qty').change(function() {
                    self.productSelected.qty = $(this).val();
                });

                $('.product-options-wrapper div').click(function() {
                    var selected_options = {};
                    $('div.swatch-attribute').each(function(k,v){
                        var attribute_id    = $(v).attr('attribute-id');
                        var option_selected = $(v).attr('option-selected');
                        if(!attribute_id || !option_selected){ return;}
                        selected_options[attribute_id] = option_selected;
                    });

                    self.productSelected.selected_options = selected_options;
                });
            }


        };
    }
);
