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
        'Magento_Checkout/js/model/payment/additional-validators',
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
        additionalValidators,
        _
    ) {
        'use strict';

        var transactionResult = ko.observable(null);

        return {
            transactionResult: transactionResult,
            canShowMethod: ko.observable(null),
            applepayOptions: null,
            isOnCheckout: false,
            quote: null,
            shippingGroups: {},
            payment: null,
            selectedShippingMethod: null,

            showPayButton: function (payMode) {
                // Set pay mode and product selection.
                this.payMode = payMode;
                this.productSelected = {};

                if (typeof window.checkoutConfig === 'undefined') {
                    return;
                }

                // Set checkout mode based on payMode.
                this.setIsOnCheckout((this.payMode !== 'product' && this.payMode !== 'cart'));

                BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid)
                    .then(function (applePaySupported) {
                        if (this.payMode === 'product') {
                            this.initProductViewWatchers();
                        }

                        if (applePaySupported) {
                            this.generateApplepayOptions();
                            this.payment = new BuckarooSdk.ApplePay.ApplePayPayment('#apple-pay-wrapper', this.applepayOptions);
                            this.payment.showPayButton(
                                window.checkoutConfig.payment.buckaroo.applepay.buttonStyle || 'black',
                                'buy'
                            );

                            if (this.payMode === 'product') {
                                var self = this;
                                this.payment.button.off("click");
                                this.payment.button.on("click", function (e) {
                                    var dataForm = $('#product_addtocart_form');
                                    dataForm.validation('isValid');
                                    setTimeout(function () {
                                        if ($('.mage-error:visible').length === 0) {
                                            self.payment.beginPayment(e);
                                        }
                                    }, 100);
                                });
                            }
                        }
                    }.bind(this))
                    .catch(function (error) {
                    }.bind(this));
            },

            updateOptions: function () {
                if (this.payment === null) {
                    return;
                }
                this.generateApplepayOptions();
                this.payment.options = this.applepayOptions;
            },

            canShowApplePay: function () {
                return BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid)
                    .then(function (applePaySupported) {
                        this.canShowMethod(applePaySupported);
                        return applePaySupported;
                    }.bind(this))
                    .catch(function (error) {
                        this.canShowMethod(false);
                        return false;
                    }.bind(this));
            },

            setQuote: function (newQuote) {
                this.quote = newQuote;
            },

            setIsOnCheckout: function (isOnCheckout) {
                this.isOnCheckout = isOnCheckout;
            },

            generateApplepayOptions: function () {
                var self = this;
                var lineItemsType = this.isOnCheckout ? 'final' : 'pending';
                var shippingMethods = this.isOnCheckout ? [] : self.availableShippingMethodInformation();
                var shippingContactCallback = this.isOnCheckout ? null : self.onSelectedShippingContact.bind(this);
                var shipmentMethodCallback = this.isOnCheckout ? null : self.onSelectedShipmentMethod.bind(this);
                var requiredBillingContactFields = ["postalAddress", "name", "phone", "email"];
                var requiredShippingContactFields = ["postalAddress", "name", "phone", "email"];

                var country = window.checkoutConfig.payment.buckaroo.applepay.country;
                if (this.quote && (null !== this.quote.shippingAddress())) {
                    country = this.quote.shippingAddress().countryId;
                }

                if (!this.isOnCheckout && !window.isCustomerLoggedIn) {
                    requiredBillingContactFields.push("email");
                    requiredShippingContactFields.push("email");
                }

                if (this.isOnCheckout) {
                    requiredBillingContactFields = ["postalAddress"];
                    requiredShippingContactFields = [];
                    if (window.checkoutConfig.payment.buckaroo.applepay.dontAskBillingInfoInCheckout) {
                        requiredBillingContactFields = [];
                    }
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
                    requiredBillingContactFields,
                    requiredShippingContactFields
                );
            },

            processLineItems: function (type = 'final', directTotals = false) {
                var totals = directTotals || this.getQuoteTotals();
                var subTotal = totals.subtotal ? parseFloat(totals.subtotal).toFixed(2) : '0.00';
                var shippingInclTax = totals.shipping ? parseFloat(totals.shipping).toFixed(2) : '0.00';

                var lineItems = [
                    { label: $.mage.__('Subtotal'), amount: subTotal, type: type },
                    { label: $.mage.__('Delivery costs'), amount: shippingInclTax, type: type }
                ];

                if (totals.discount && totals.discount < 0) {
                    var discountTotal = parseFloat(totals.discount).toFixed(2);
                    lineItems.push({ label: $.mage.__('Discount'), amount: discountTotal, type: type });
                }
                return lineItems;
            },

            processTotalLineItems: function (type = 'final', directTotals = false) {
                var totals = directTotals || this.getQuoteTotals();
                var grandTotal = totals.grand_total ? parseFloat(totals.grand_total).toFixed(2) : '0.00';
                var storeName = window.checkoutConfig.payment.buckaroo.applepay.storeName;

                if (isNaN(grandTotal)) {
                    grandTotal = '0.00';
                }

                return { label: storeName, amount: grandTotal, type: type };
            },

            getQuoteTotals: function () {
                var totals = {};
                if (!this.quote || typeof this.quote.totals() === 'undefined') {
                    return totals;
                }
                totals['subtotal'] = this.quote.totals().subtotal_incl_tax;
                totals['discount'] = this.quote.totals().discount_amount;
                totals['shipping'] = this.quote.totals().shipping_incl_tax;
                totals['grand_total'] = this.quote.totals().grand_total;

                if (!this.quote.totals().custom_grand_total) {
                    var segments = this.quote.totals().total_segments;
                    for (var i in segments) {
                        if (segments[i]['code'] === 'grand_total') {
                            totals['grand_total'] = segments[i]['value'];
                        }
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
                setTimeout(function () {
                    if (url) {
                        window.location.href = url;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            },

            onSelectedShipmentMethod: function (event) {
                if (this.payMode === 'product' || this.payMode === 'cart') {
                    this.selectedShippingMethod = event;
                    return $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/updateShippingMethods'),
                        type: 'POST',
                        data: { wallet: { identifier: event.identifier } },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function (data) {
                            var result = JSON.parse(data);
                            if (result.success === true) {
                                var authorizationResult = {
                                    newTotal: this.processTotalLineItems('final', result.data.totals),
                                    newLineItems: this.processLineItems('final', result.data.totals)
                                };
                                return JSON.stringify(authorizationResult);
                            } else {
                                this.timeoutRedirect();
                            }
                        }.bind(this)
                    })
                        .fail(function (jqXHR, textStatus, errorThrown) {
                            this.timeoutRedirect();
                        }.bind(this));
                } else {
                    var newShippingMethod = this.shippingGroups[event.identifier];
                    this.updateQuoteRate(newShippingMethod);
                    return Promise.resolve({
                        newTotal: this.processTotalLineItems(),
                        newLineItems: this.processLineItems()
                    });
                }
            },

            onSelectedShippingContact: function (event) {
                if (this.payMode === 'product') {
                    return $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/add'),
                        type: 'POST',
                        data: { product: this.productSelected, wallet: event },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function (data) {
                            var result = JSON.parse(data);
                            if (result.success === true) {
                                this.shippingGroups = {};
                                $.each(result.data.shipping_methods, function (index, rate) {
                                    this.shippingGroups[rate['method_code']] = rate;
                                }.bind(this));
                                return JSON.stringify({
                                    errors: [],
                                    newShippingMethods: this.availableShippingMethodInformation(),
                                    newTotal: this.processTotalLineItems('final', result.data.totals),
                                    newLineItems: this.processLineItems('final', result.data.totals)
                                });
                            } else {
                                this.timeoutRedirect();
                            }
                        }.bind(this)
                    })
                        .fail(function (jqXHR, textStatus, errorThrown) {
                            this.timeoutRedirect();
                        }.bind(this));
                } else if (this.payMode === 'cart') {
                    return $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/getShippingMethods'),
                        type: 'POST',
                        data: { wallet: event },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function (data) {
                            var result = JSON.parse(data);
                            if (result.success === true) {
                                this.shippingGroups = {};
                                $.each(result.data.shipping_methods, function (index, rate) {
                                    this.shippingGroups[rate['method_code']] = rate;
                                }.bind(this));
                                return JSON.stringify({
                                    errors: [],
                                    newShippingMethods: this.availableShippingMethodInformation(),
                                    newTotal: this.processTotalLineItems('final', result.data.totals),
                                    newLineItems: this.processLineItems('final', result.data.totals)
                                });
                            } else {
                                this.timeoutRedirect();
                                return '';
                            }
                        }.bind(this)
                    })
                        .fail(function (jqXHR, textStatus, errorThrown) {
                            this.timeoutRedirect();
                        }.bind(this));
                } else {
                    var newShippingAddress = shippingHandler.setShippingAddress(event);
                    this.updateShippingMethods(newShippingAddress);
                    return Promise.resolve({
                        errors: [],
                        newShippingMethods: this.availableShippingMethodInformation(),
                        newTotal: this.processTotalLineItems(),
                        newLineItems: this.processLineItems()
                    });
                }
            },

            updateShippingMethods: function (address) {
                var serviceUrl = resourceUrlManager.getUrlForEstimationShippingMethodsForNewAddress(this.quote);
                var payload = JSON.stringify({
                    address: {
                        street: address.street,
                        city: address.city,
                        region_id: address.regionId,
                        region: address.region,
                        country_id: address.countryId,
                        postcode: address.postcode,
                        firstname: address.firstname,
                        lastname: address.lastname,
                        company: address.company,
                        telephone: address.telephone,
                        custom_attributes: address.customAttributes,
                        save_in_address_book: address.saveInAddressBook
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
                var subtotal = parseFloat(this.quote.totals().subtotal_incl_tax);
                var shippingCost = parseFloat(newRate['price_incl_tax']);
                var newGrandTotal = (subtotal + shippingCost).toFixed(2);
                this.quote.totals().shipping_incl_tax = shippingCost;
                this.quote.totals().grand_total = newGrandTotal;
                this.quote.totals().custom_grand_total = true;
            },

            captureFunds: function (payment) {
                var authorizationResult = {
                    status: ApplePaySession.STATUS_SUCCESS,
                    errors: []
                };

                if ((this.payMode === 'product') || (this.payMode === 'cart')) {
                    return $.ajax({
                        url: urlBuilder.build('buckaroo/applepay/saveOrder'),
                        type: 'POST',
                        data: {
                            payment: payment,
                            extra: this.getData(payment)
                        },
                        global: false,
                        dataType: 'json',
                        async: false,
                        dataFilter: function (data) {
                            var result = JSON.parse(data);
                            if (result.success === true) {
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
                        }.bind(this)
                    })
                        .fail(function (jqXHR, textStatus, errorThrown) {
                            this.timeoutRedirect();
                        }.bind(this));
                } else {
                    this.transactionResult(payment);
                    return Promise.resolve(authorizationResult);
                }
            },

            getData: function (payment) {
                var transactionData = this.formatTransactionResponse(payment);
                return {
                    "method": 'applepay',
                    "po_number": null,
                    "shippingMethod": this.selectedShippingMethod,
                    "additional_data": {
                        "applepayTransaction": transactionData,
                        "billingContact": payment && payment.billingContact ? JSON.stringify(payment.billingContact) : ''
                    }
                };
            },

            formatTransactionResponse: function (response) {
                if (response === null || typeof response === 'undefined') {
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
                this.productSelected = {};
                this.productSelected.id = $('.price-box').attr('data-product-id');
                this.productSelected.qty = $('#qty').val();
                var self = this;
                $('#qty').change(function () {
                    self.productSelected.qty = $(this).val();
                });
                $('.product-options-wrapper div').click(function () {
                    var selected_options = {};
                    $('div.swatch-attribute').each(function (k, v) {
                        var attribute_id = $(v).attr('attribute-id') || $(v).attr('data-attribute-id');
                        var option_selected = $(v).attr('option-selected') || $(v).attr('data-option-selected');
                        if (attribute_id && option_selected) {
                            selected_options[attribute_id] = option_selected;
                        }
                    });
                    self.productSelected.selected_options = selected_options;
                });
            },

            isOsc: function () {
                return this.getOscButton();
            },

            getOscButton: function () {
                return document.querySelector('.action.primary.checkout.iosc-place-order-button');
            },

            devLog: function (msg, params) {
                // Debug logging is disabled for production.
            }
        };
    }
);
