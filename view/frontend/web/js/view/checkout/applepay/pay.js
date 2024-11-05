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
define([
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
], function (
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
        productSelected: {},

        showPayButton: function (payMode) {
            this.devLog('applepaydebug/6', payMode);
            this.payMode = payMode;

            if (typeof window.checkoutConfig === 'undefined') {
                return;
            }

            this.setIsOnCheckout(!(this.payMode === 'product' || this.payMode === 'cart'));

            if (this.payMode === 'product') {
                this.initProductViewWatchers();
            }

            BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid)
                .then(function (applePaySupported) {
                    this.devLog('applepaydebug/8', [
                        applePaySupported,
                        this.isOnCheckout,
                        window.checkoutConfig.payment.buckaroo.applepay
                    ]);

                    if (!applePaySupported) {
                        return;
                    }

                    this.generateApplepayOptions();

                    this.payment = new BuckarooSdk.ApplePay.ApplePayPayment('#apple-pay-wrapper', this.applepayOptions);
                    this.payment.showPayButton(
                        window.checkoutConfig.payment.buckaroo.applepay.buttonStyle || 'black',
                        'buy'
                    );

                    if (this.payMode === 'product') {
                        this.payment.button.off('click');
                        this.payment.button.on('click', function (e) {
                            this.devLog('applepaydebug/24');
                            var dataForm = $('#product_addtocart_form');
                            dataForm.validation('isValid');

                            setTimeout(function () {
                                if ($('.mage-error:visible').length === 0) {
                                    this.payment.beginPayment(e);
                                }
                            }.bind(this), 100);
                        }.bind(this));
                    }
                }.bind(this))
                .catch(function (error) {
                    this.devLog('Apple Pay support check failed:', error);
                }.bind(this));
        },

        updateOptions: function () {
            if (!this.payment) {
                return;
            }

            this.generateApplepayOptions();
            this.payment.options = this.applepayOptions;
        },

        canShowApplePay: function () {
            BuckarooSdk.ApplePay.checkApplePaySupport(window.checkoutConfig.payment.buckaroo.applepay.guid)
                .then(function (applePaySupported) {
                    this.canShowMethod(applePaySupported);
                }.bind(this))
                .catch(function () {
                    this.canShowMethod(false);
                }.bind(this));

            return this.canShowMethod();
        },

        setQuote: function (newQuote) {
            this.quote = newQuote;
        },

        setIsOnCheckout: function (isOnCheckout) {
            this.isOnCheckout = isOnCheckout;
        },

        generateApplepayOptions: function () {
            var lineItemsType = this.isOnCheckout ? 'final' : 'pending';
            var shippingMethods = this.isOnCheckout ? [] : this.availableShippingMethodInformation();
            var shippingContactCallback = this.isOnCheckout ? null : this.onSelectedShippingContact.bind(this);
            var shipmentMethodCallback = this.isOnCheckout ? null : this.onSelectedShipmentMethod.bind(this);
            var requiredBillingContactFields = this.isOnCheckout ? ['postalAddress'] : ['name', 'postalAddress', 'phone'];
            var requiredShippingContactFields = this.isOnCheckout ? [] : ['name', 'postalAddress', 'phone'];

            if (!this.isOnCheckout && !window.isCustomerLoggedIn) {
                requiredBillingContactFields.push('email');
                requiredShippingContactFields.push('email');
            }

            if (this.isOnCheckout && window.checkoutConfig.payment.buckaroo.applepay.dontAskBillingInfoInCheckout) {
                requiredBillingContactFields = [];
            }

            var country = window.checkoutConfig.payment.buckaroo.applepay.country;
            if (this.quote && this.quote.shippingAddress()) {
                country = this.quote.shippingAddress().countryId || country;
            }

            this.devLog('applepaydebug/13', [country, requiredBillingContactFields, requiredShippingContactFields]);

            this.applepayOptions = new BuckarooSdk.ApplePay.ApplePayOptions(
                window.checkoutConfig.payment.buckaroo.applepay.storeName,
                country,
                window.checkoutConfig.payment.buckaroo.applepay.currency,
                window.checkoutConfig.payment.buckaroo.applepay.cultureCode,
                window.checkoutConfig.payment.buckaroo.applepay.guid,
                this.processLineItems(lineItemsType),
                this.processTotalLineItems(lineItemsType),
                'shipping',
                shippingMethods,
                this.captureFunds.bind(this),
                shipmentMethodCallback,
                shippingContactCallback,
                requiredBillingContactFields,
                requiredShippingContactFields
            );
        },

        processLineItems: function (type = 'final', directTotals = false) {
            var totals = directTotals || this.getQuoteTotals();
            var subTotal = parseFloat(totals.subtotal || '0.00').toFixed(2);
            var shippingInclTax = parseFloat(totals.shipping || '0.00').toFixed(2);

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
            var grandTotal = parseFloat(totals.grand_total || '0.00').toFixed(2);
            if (isNaN(grandTotal)) {
                grandTotal = '0.00';
            }
            var storeName = window.checkoutConfig.payment.buckaroo.applepay.storeName;

            return { label: storeName, amount: grandTotal, type: type };
        },

        getQuoteTotals: function () {
            if (!this.quote || typeof this.quote.totals() === 'undefined') {
                return {};
            }

            var totals = {
                subtotal: this.quote.totals().subtotal_incl_tax,
                discount: this.quote.totals().discount_amount,
                shipping: this.quote.totals().shipping_incl_tax,
                grand_total: this.quote.totals().grand_total
            };

            if (this.quote.totals().custom_grand_total) {
                return totals;
            }

            var segments = this.quote.totals().total_segments || [];
            segments.forEach(function (segment) {
                if (segment.code === 'grand_total') {
                    totals.grand_total = segment.value;
                }
            });

            return totals;
        },

        availableShippingMethodInformation: function () {
            var shippingMethods = [];

            $.each(this.shippingGroups, function (index, rate) {
                var shippingInclTax = parseFloat(rate.price_incl_tax).toFixed(2);

                shippingMethods.push({
                    label: rate.carrier_title,
                    amount: shippingInclTax,
                    identifier: rate.method_code,
                    detail: rate.method_title
                });
            });

            return shippingMethods;
        },

        timeoutRedirect: function (url) {
            this.devLog('applepaydebug/38', url);
            setTimeout(function () {
                if (url) {
                    window.location.href = url;
                } else {
                    window.location.reload();
                }
            }, 1500);
        },

        onSelectedShipmentMethod: function (event) {
            this.devLog('applepaydebug/27');

            if (this.payMode === 'product' || this.payMode === 'cart') {
                return $.ajax({
                    url: urlBuilder.build('buckaroo/applepay/updateShippingMethods'),
                    type: 'POST',
                    data: {
                        wallet: {
                            identifier: event.identifier
                        }
                    },
                    global: false,
                    dataType: 'json'
                }).then(function (result) {
                    if (result.success === 'true') {
                        var authorizationResult = {
                            newTotal: this.processTotalLineItems('final', result.data.totals),
                            newLineItems: this.processLineItems('final', result.data.totals)
                        };

                        this.devLog('applepaydebug/37');

                        return authorizationResult;
                    } else {
                        this.timeoutRedirect();
                        return $.Deferred().reject();
                    }
                }.bind(this)).fail(function () {
                    this.timeoutRedirect();
                    return $.Deferred().reject();
                }.bind(this));
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
            this.devLog('applepaydebug/28');

            if (this.payMode === 'product' || this.payMode === 'cart') {
                var urlEndpoint = this.payMode === 'product' ? 'buckaroo/applepay/add' : 'buckaroo/applepay/getShippingMethods';
                var requestData = this.payMode === 'product' ? { product: this.productSelected, wallet: event } : { wallet: event };

                return $.ajax({
                    url: urlBuilder.build(urlEndpoint),
                    type: 'POST',
                    data: requestData,
                    global: false,
                    dataType: 'json'
                }).then(function (result) {
                    if (result.success === 'true') {
                        this.shippingGroups = {};
                        $.each(result.data.shipping_methods, function (index, rate) {
                            this.shippingGroups[rate.method_code] = rate;
                        }.bind(this));

                        var authorizationResult = {
                            errors: [],
                            newShippingMethods: this.availableShippingMethodInformation(),
                            newTotal: this.processTotalLineItems('final', result.data.totals),
                            newLineItems: this.processLineItems('final', result.data.totals)
                        };

                        this.devLog('applepaydebug/30');

                        return authorizationResult;
                    } else {
                        this.timeoutRedirect();
                        return $.Deferred().reject();
                    }
                }.bind(this)).fail(function () {
                    this.timeoutRedirect();
                    return $.Deferred().reject();
                }.bind(this));
            } else {
                var newShippingAddress = shippingHandler.setShippingAddress(event);
                return this.updateShippingMethods(newShippingAddress).then(function () {
                    var authorizationResult = {
                        errors: [],
                        newShippingMethods: this.availableShippingMethodInformation(),
                        newTotal: this.processTotalLineItems(),
                        newLineItems: this.processLineItems()
                    };
                    return authorizationResult;
                }.bind(this));
            }
        },

        updateShippingMethods: function (address) {
            this.devLog('applepaydebug/16');
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

            return $.ajax({
                url: urlBuilder.build(serviceUrl),
                type: 'POST',
                data: payload,
                global: false,
                contentType: 'application/json',
                dataType: 'json'
            }).done(function (result) {
                this.shippingGroups = {};
                var firstLoop = true;

                $.each(result, function (index, rate) {
                    this.shippingGroups[rate.method_code] = rate;

                    if (firstLoop) {
                        this.updateQuoteRate(rate);
                        firstLoop = false;
                    }
                }.bind(this));
            }.bind(this)).fail(function () {
                this.devLog('Failed to update shipping methods');
            }.bind(this));
        },

        updateQuoteRate: function (newRate) {
            shippingHandler.selectShippingMethod(newRate);

            var subtotal = parseFloat(this.quote.totals().subtotal_incl_tax || 0);
            var shipping = parseFloat(newRate.price_incl_tax || 0);
            this.quote.totals().shipping_incl_tax = shipping;
            this.quote.totals().grand_total = subtotal + shipping;
            this.quote.totals().custom_grand_total = true;
        },

        captureFunds: function (payment) {
            this.devLog('applepaydebug/12', payment);

            var authorizationResult = {
                status: ApplePaySession.STATUS_SUCCESS,
                errors: []
            };

            if (this.payMode === 'product' || this.payMode === 'cart') {
                return $.ajax({
                    url: urlBuilder.build('buckaroo/applepay/saveOrder'),
                    type: 'POST',
                    data: {
                        payment: payment,
                        extra: this.getData(payment)
                    },
                    global: false,
                    dataType: 'json'
                }).then(function (result) {
                    if (result.success === 'true') {
                        if (result.data && result.data.RequiredAction && result.data.RequiredAction.RedirectURL) {
                            this.timeoutRedirect(result.data.RequiredAction.RedirectURL);
                        } else {
                            this.timeoutRedirect();
                        }
                        this.payMode = '';
                        return authorizationResult;
                    } else {
                        this.timeoutRedirect();
                        return {
                            status: ApplePaySession.STATUS_FAILURE,
                            errors: []
                        };
                    }
                }.bind(this)).fail(function () {
                    this.timeoutRedirect();
                    return {
                        status: ApplePaySession.STATUS_FAILURE,
                        errors: []
                    };
                }.bind(this));
            } else {
                this.transactionResult(payment);
                return Promise.resolve(authorizationResult);
            }
        },

        getData: function (payment) {
            var transactionData = this.formatTransactionResponse(payment);

            return {
                method: 'buckaroo_magento2_applepay',
                po_number: null,
                additional_data: {
                    applepayTransaction: transactionData,
                    billingContact: payment && payment.billingContact
                        ? JSON.stringify(payment.billingContact)
                        : ''
                }
            };
        },

        formatTransactionResponse: function (response) {
            if (!response || !response.token || !response.token.paymentData) {
                return null;
            }

            var paymentData = response.token.paymentData;

            var formattedData = {
                paymentData: {
                    version: paymentData.version,
                    data: paymentData.data,
                    signature: paymentData.signature,
                    header: paymentData.header
                }
            };

            return JSON.stringify(formattedData);
        },

        initProductViewWatchers: function () {
            this.devLog('applepaydebug/initProductViewWatchers');

            var productId = $('.price-box').attr('data-product-id');
            var productQty = $('#qty').val() || '1';

            if (!productId) {
                console.error('Product ID not found on the page.');
                return;
            }

            this.productSelected.id = productId;
            this.productSelected.qty = productQty;

            $('#qty').on('change', function () {
                this.productSelected.qty = $(this).val();
            }.bind(this));

            $('.product-options-wrapper').on('click', 'div', function () {
                var selectedOptions = {};
                $('div.swatch-attribute').each(function () {
                    var attributeId = $(this).attr('attribute-id') || $(this).attr('data-attribute-id');
                    var optionSelected = $(this).attr('option-selected') || $(this).attr('data-option-selected');

                    if (attributeId && optionSelected) {
                        selectedOptions[attributeId] = optionSelected;
                    }
                });

                this.productSelected.selected_options = selectedOptions;
            }.bind(this));
        },

        isOsc: function () {
            return !!this.getOscButton();
        },

        getOscButton: function () {
            return document.querySelector('.action.primary.checkout.iosc-place-order-button');
        },

        devLog: function (msg, params) {
            if (window.buckarooDebug) {
                console.log(msg, params);
            }
        }
    };
});
