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
        'mage/url',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'buckaroo/checkout/common',
        'BuckarooHostedFieldsSdk'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        urlBuilder,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon,
        BuckarooHostedFieldsSdk
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Buckaroo_Magento2/payment/buckaroo_magento2_creditcards',
                encryptedCardData: null,
                service: null
            },
            paymentFeeLabel: window.checkoutConfig.payment.buckaroo.creditcards.paymentFeeLabel,
            subtext: window.checkoutConfig.payment.buckaroo.creditcards.subtext,
            subTextStyle: checkoutCommon.getSubtextStyle('creditcards'),

            oauthTokenError: ko.observable(''),
            paymentError: ko.observable(''),
            isPayButtonDisabled: ko.observable(false),
            sdkClient: null,
            tokenExpiresAt: null,

            /**
             * Initialize component and retrieve OAuth token.
             */
            initialize: function (options) {
                this._super(options);
                return this;
            },

            /**
             * Called from afterRender in the template, ensuring DOM is ready.
             */
            initCreditCardFields: function () {
                this.getOAuthToken();
            },

            /**
             * Retrieve OAuth token via AJAX.
             */
            async getOAuthToken() {
                try {
                    const response = await $.ajax({
                        url: urlBuilder.build('/buckaroo/credentialschecker/gettoken'),
                        type: 'GET',
                        headers: {
                            'X-Requested-From': 'MagentoFrontend'
                        }
                    });
                    if (response.error) {
                        this.oauthTokenError($.mage.__("An error occurred, please try another payment method or try again later."));
                    } else {
                        const accessToken = response.data.access_token;
                        const issuers = response.data.issuers;
                        const expiresIn = response.data.expires_in; // lifetime in seconds

                        this.tokenExpiresAt = Date.now() + expiresIn * 1000;

                        this.scheduleTokenRefresh(expiresIn);

                        await this.initHostedFields(accessToken, issuers);
                    }
                } catch (error) {
                    this.oauthTokenError($.mage.__("An error occurred, please try another payment method or try again later."));
                }
            },

            /**
             * Schedule token refresh before expiry.
             */
            scheduleTokenRefresh: function(expiresIn) {
                const refreshTime = Math.max(expiresIn * 1000 - 1000, 0);
                setTimeout(() => {
                    this.resetHostedFields($.mage.__("We are refreshing the payment form, because the session has expired."));
                }, refreshTime);
            },

            /**
             * Remove hosted field iframes from the DOM.
             */
            removeHostedFieldIframes: function() {
                $('#cc-name-wrapper iframe').remove();
                $('#cc-number-wrapper iframe').remove();
                $('#cc-expiry-wrapper iframe').remove();
                $('#cc-cvc-wrapper iframe').remove();
            },

            /**
             * Unified function to reset hosted fields.
             * @param {String} [errorMsg] Optional error message to display.
             */
            async resetHostedFields(errorMsg = '') {
                this.removeHostedFieldIframes();
                this.paymentError(errorMsg);
                await this.getOAuthToken();
                this.isPayButtonDisabled(false);
            },

            /**
             * Initialize hosted fields using the OAuth token and issuers.
             *
             * @param {String} accessToken
             * @param {Array} issuers
             */
            async initHostedFields(accessToken, issuers) {
                try {
                    this.sdkClient = new BuckarooHostedFieldsSdk.HFClient(accessToken);
                    const locale = document.documentElement.lang;
                    const languageCode = locale.split('_')[0];
                    this.sdkClient.setLanguage(languageCode);
                    this.sdkClient.setSupportedServices(issuers);

                    // Start the session and update the pay button state based on validation.
                    await this.sdkClient.startSession((event) => {
                        this.sdkClient.handleValidation(
                            event,
                            'cc-name-error',
                            'cc-number-error',
                            'cc-expiry-error',
                            'cc-cvc-error'
                        );
                        this.isPayButtonDisabled(!this.sdkClient.formIsValid());
                        this.service = this.sdkClient.getService();
                    });

                    // Styling for hosted fields.
                    const cardLogoStyling = {
                        height: "80%",
                        position: 'absolute',
                        border: '1px solid #d6d6d6',
                        borderRadius: "4px",
                        opacity: '1',
                        transition: 'all 0.3s ease',
                        right: '5px',
                        backgroundColor: 'inherit'
                    };

                    const styling = {
                        fontSize: "14px",
                        fontStyle: "normal",
                        fontWeight: 400,
                        fontFamily: 'Open Sans, Helvetica Neue, Helvetica, Arial, sans-serif',
                        textAlign: 'left',
                        background: '#fefefe',
                        color: '#333333',
                        placeholderColor: '#888888',
                        borderRadius: '5px',
                        padding: '8px 10px',
                        boxShadow: 'none',
                        transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
                        border: '1px solid #d6d6d6',
                        cardLogoStyling: cardLogoStyling
                    };

                    // Mount hosted fields concurrently.
                    const mountCardHolderNamePromise = this.sdkClient.mountCardHolderName("#cc-name-wrapper", {
                        id: "ccname",
                        placeHolder: "John Doe",
                        labelSelector: "#cc-name-label",
                        baseStyling: styling
                    }).then(field => {
                        field.focus();
                        return field;
                    });

                    const mountCardNumberPromise = this.sdkClient.mountCardNumber("#cc-number-wrapper", {
                        id: "cc",
                        placeHolder: "555x xxxx xxxx xxxx",
                        labelSelector: "#cc-number-label",
                        baseStyling: styling
                    });

                    const mountCvcPromise = this.sdkClient.mountCvc("#cc-cvc-wrapper", {
                        id: "cvc",
                        placeHolder: "1234",
                        labelSelector: "#cc-cvc-label",
                        baseStyling: styling
                    });

                    const mountExpiryPromise = this.sdkClient.mountExpiryDate("#cc-expiry-wrapper", {
                        id: "expiry",
                        placeHolder: "MM / YY",
                        labelSelector: "#cc-expiry-label",
                        baseStyling: styling
                    });

                    await Promise.all([
                        mountCardHolderNamePromise,
                        mountCardNumberPromise,
                        mountCvcPromise,
                        mountExpiryPromise
                    ]);
                } catch (error) {
                    console.error("Error initializing hosted fields:", error);
                }
            },

            /**
             * Knockout click handler for the pay button.
             *
             * @param {Object} data - The view model data.
             * @param {Event} event - The event object.
             */
            onPayClick: async function(data, event) {
                event.preventDefault();
                this.isPayButtonDisabled(true);

                // Check if the token has expired before processing payment.
                if (Date.now() > this.tokenExpiresAt) {
                    await this.resetHostedFields($.mage.__("We are refreshing the payment form, because the session has expired."));
                    this.paymentError($.mage.__("Session expired, please try again."));
                    this.isPayButtonDisabled(false);
                    return;
                }

                try {
                    const paymentToken = await this.sdkClient.submitSession();
                    if (!paymentToken) {
                        throw new Error("Failed to get encrypted card data.");
                    }
                    this.encryptedCardData = paymentToken;
                    this.service = this.sdkClient.getService();
                    this.finalizePlaceOrder(event);
                } catch (error) {
                    this.paymentError($.mage.__("Payment processing failed. Please try again."));
                    this.isPayButtonDisabled(false);
                }
            },

            /**
             * Finalize placing the order.
             *
             * @param {Event} event
             */
            finalizePlaceOrder: function (event) {
                if (event) {
                    event.preventDefault();
                }
                if (!this.encryptedCardData) {
                    this.paymentError($.mage.__("Payment token is missing. Please try again."));
                    return;
                }
                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    const placeOrder = placeOrderAction(
                        this.getData(),
                        this.redirectAfterPlaceOrder,
                        this.messageContainer
                    );
                    $.when(placeOrder)
                        .fail(() => {
                            this.isPlaceOrderActionAllowed(true);
                            this.paymentError($.mage.__("Payment token is missing. Please try again."));
                        })
                        .done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            /**
             * After order placement, handle the redirect.
             */
            afterPlaceOrder: function () {
                const response = window.checkoutConfig.payment.buckaroo.response;
                checkoutCommon.redirectHandle(response);
            },

            /**
             * Retrieve the payment data.
             *
             * @returns {Object}
             */
            getData: function() {
                return {
                    "method": this.item.method,
                    "po_number": null,
                    "additional_data": {
                        "customer_encrypteddata": this.encryptedCardData,
                        "customer_creditcardcompany": this.service
                    }
                };
            },

            /**
             * Select this payment method.
             *
             * @returns {Boolean}
             */
            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            }
        });
    }
);
