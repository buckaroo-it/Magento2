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
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'buckaroo/checkout/common',
        'Magento_Checkout/js/model/totals',
        'BuckarooSdk',
        'mage/translate'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon,
        totals
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_clicktopay'
                },
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,
                buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_clicktopay,

                buttonWrapperId: '#buckaroo-clicktopay-button',
                screenWrapperId: '#buckaroo-clicktopay-screen',
                accessTokenCache: null,
                captureContextAmount: null,
                initSequence: 0,
                totalsSubscription: null,
                totalsDebounceTimer: null,

                initObservable: function () {
                    this._super().observe(['transientToken', 'identifier', 'initErrorMessage']);
                    this.transientToken('');
                    this.identifier('');
                    this.initErrorMessage('');
                    return this;
                },

                /**
                 * Initialize the BuckarooSdk ClickToPay CaptureContext and render the Drop-in UI.
                 *
                 * Runs eagerly when the payment step renders (afterRender binding) so the
                 * Drop-in UI is already there when the shopper selects the method, instead
                 * of starting a token -> capture-context -> script-load waterfall on click.
                 * Idempotent: re-runs only when the grand total changed, superseding any
                 * still-pending initialization for a stale amount.
                 */
                initializeCaptureContext: function () {
                    var self = this;
                    var config = this.buckaroo;

                    if (!config || !config.merchantIdentifier) {
                        return;
                    }

                    if (typeof BuckarooSdk === 'undefined' || !BuckarooSdk.ClickToPay) {
                        return;
                    }

                    if (!$(this.buttonWrapperId).length || !$(this.screenWrapperId).length) {
                        return;
                    }

                    this.subscribeToTotalsChanges();

                    var grandTotal = totals.totals() ? totals.totals().grand_total : 0;

                    if (!grandTotal || grandTotal === this.captureContextAmount) {
                        return;
                    }

                    this.captureContextAmount = grandTotal;
                    this.initErrorMessage('');
                    var sequence = ++this.initSequence;

                    var captureContext;
                    var captureContextOptions;
                    try {
                        captureContextOptions = new BuckarooSdk.ClickToPay.CaptureContextOptions(
                            config.merchantIdentifier,
                            config.targetOrigins,
                            config.country,
                            config.locale,
                            {
                                currency: config.currency,
                                totalAmount: grandTotal
                            },
                            function (paymentData) {
                                self.transientToken(paymentData.transientToken || '');
                                self.identifier(paymentData.identifier || '');
                                self.placeOrder(null, null);
                            }
                        );

                        captureContext = new BuckarooSdk.ClickToPay.CaptureContext(
                            this.buttonWrapperId,
                            this.screenWrapperId,
                            captureContextOptions
                        );
                    } catch (e) {
                        this.captureContextAmount = null;
                        this.initErrorMessage(this.getInitErrorText());
                        console.error('[ClicktoPay] SDK initialization failed:', e);
                        return;
                    }

                    this.fetchAccessToken()
                        .then(function (accessToken) {
                            return captureContext.generateCaptureContext(accessToken);
                        })
                        .then(function (response) {
                            // The GenerateCaptureContext API returns PascalCase keys
                            // (Successful, ScriptUrl, Jwt, Identifier, ErrorReason) but the
                            // SDK reads camelCase. Normalize the response so the success
                            // check and Drop-in UI initialization work.
                            return self.normalizeCaptureContext(response);
                        })
                        .then(function (context) {
                            if (sequence !== self.initSequence) {
                                // A newer initialization (changed grand total) superseded this one.
                                return;
                            }

                            if (!context || !context.successful || !context.scriptUrl || !context.jwt) {
                                throw new Error(
                                    (context && context.errorReason) || 'Capture context response was not successful.'
                                );
                            }

                            $(self.buttonWrapperId).empty();
                            $(self.screenWrapperId).empty();

                            BuckarooSdk.ClickToPay.initiateClickToPayDropInUI(
                                context.identifier,
                                context.scriptUrl,
                                context.jwt,
                                self.buttonWrapperId,
                                self.screenWrapperId,
                                captureContextOptions.processPaymentCallback
                            );

                            self.initErrorMessage('');
                        })
                        .catch(function (e) {
                            if (sequence === self.initSequence) {
                                self.captureContextAmount = null;
                                self.initErrorMessage(self.getInitErrorText());
                            }
                            console.error('[ClicktoPay] SDK initialization failed:', e);
                        });
                },

                /**
                 * Shopper-facing message shown when the Drop-in UI could not be
                 * initialized; mirrors the Hosted Fields (credit cards) wording.
                 */
                getInitErrorText: function () {
                    return $.mage.__('An error occurred, please try another payment method or try again later.');
                },

                /**
                 * Fetch the Click to Pay access token via the Magento proxy (avoids CORS),
                 * caching it client-side until shortly before it expires so re-initialization
                 * (e.g. after a coupon changes the grand total) skips the Magento round trip.
                 */
                fetchAccessToken: function () {
                    var self = this;

                    if (this.accessTokenCache && this.accessTokenCache.expiresAt > Date.now()) {
                        return $.Deferred().resolve(this.accessTokenCache.token).promise();
                    }

                    return $.ajax({
                        url: window.BASE_URL + 'buckaroo/clicktopay/token',
                        method: 'POST',
                        data: { form_key: window.FORM_KEY }
                    }).then(function (response) {
                        var expiresIn = parseInt(response.expires_in, 10) || 0;

                        if (response.access_token && expiresIn > 0) {
                            self.accessTokenCache = {
                                token: response.access_token,
                                expiresAt: Date.now() + (expiresIn * 1000)
                            };
                        }

                        return response.access_token;
                    });
                },

                /**
                 * Re-initialize the capture context when the grand total changes
                 * (coupon applied, shipping change, ...) so the amount shown in the
                 * Click to Pay UI stays in sync with the quote. Debounced because
                 * totals can emit several times for a single update.
                 */
                subscribeToTotalsChanges: function () {
                    var self = this;

                    if (this.totalsSubscription) {
                        return;
                    }

                    this.totalsSubscription = totals.totals.subscribe(function () {
                        clearTimeout(self.totalsDebounceTimer);
                        self.totalsDebounceTimer = setTimeout(function () {
                            self.initializeCaptureContext();
                        }, 300);
                    });
                },

                /**
                 * Map a capture-context response to camelCase top-level keys.
                 * The API returns PascalCase (Successful, ScriptUrl, Jwt, ...) while the
                 * SDK reads camelCase; lowercasing the first letter is idempotent for
                 * already-camelCase responses. Returns a new object (no mutation).
                 */
                normalizeCaptureContext: function (response) {
                    if (!response || typeof response !== 'object') {
                        return response;
                    }

                    return Object.keys(response).reduce(function (accumulator, key) {
                        var camelKey = key.charAt(0).toLowerCase() + key.slice(1);
                        accumulator[camelKey] = response[key];
                        return accumulator;
                    }, {});
                },

                /**
                 * Place order after CTP payment callback has populated transientToken.
                 */
                placeOrder: function (data, event) {
                    var self = this;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);

                        var placeOrder = placeOrderAction(
                            this.getData(),
                            this.redirectAfterPlaceOrder,
                            this.messageContainer
                        );

                        $.when(placeOrder).fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                                self.transientToken('');
                            }
                        ).done(this.afterPlaceOrder.bind(this));

                        return true;
                    }

                    return false;
                },

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.responseData;
                    checkoutCommon.redirectHandle(response);
                },

                selectPaymentMethod: function () {
                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    var self = this;
                    setTimeout(function () {
                        self.initializeCaptureContext();
                    }, 0);
                    return true;
                },

                validate: function () {
                    return this.transientToken().length > 0;
                },

                getData: function () {
                    return {
                        'method': this.item.method,
                        'po_number': null,
                        'additional_data': {
                            'transient_token': this.transientToken(),
                            'identifier': this.identifier()
                        }
                    };
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = (this.buckaroo && this.buckaroo.allowedCurrencies)
                        ? this.buckaroo.allowedCurrencies
                        : [];

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');
                    return text.replace('%s', this.baseCurrencyCode);
                }
            }
        );
    }
);
