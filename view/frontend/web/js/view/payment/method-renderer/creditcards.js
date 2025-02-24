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

            initialize: function (options) {
                this._super(options);
                this.getOAuthToken();
                return this;
            },

            async getOAuthToken() {
                try {
                    const response = await $.ajax({
                        url: urlBuilder.build('/buckaroo/credentialschecker/gettoken'),
                        type: "GET",
                        headers: {
                            'X-Requested-From': 'MagentoFrontend'
                        }
                    });

                    if (response.error) {
                        this.oauthTokenError("An error occurred, please try another payment method or try again later.");
                    } else {
                        const accessToken = response.data.access_token;
                        const issuers = response.data.issuers;
                        await this.initHostedFields(accessToken, issuers);
                    }
                } catch (error) {
                    this.oauthTokenError("An error occurred, please try another payment method or try again later.");
                }
            },

            resetForm: function() {
                // Remove hosted field iframes from DOM using jQuery
                this.removeHostedFieldIframes();
                // Re-fetch the OAuth token and reinitialize the hosted fields
                this.getOAuthToken();
                this.paymentError('');
                this.isPayButtonDisabled(false);
            },

            removeHostedFieldIframes: function() {
                // Remove the iframes for the hosted fields by targeting their container
                $('#cc-name-wrapper iframe').remove();
                $('#cc-number-wrapper iframe').remove();
                $('#cc-expiry-wrapper iframe').remove();
                $('#cc-cvc-wrapper iframe').remove();
            },

            async initHostedFields(accessToken, issuers) {
                try {
                    this.sdkClient = new BuckarooHostedFieldsSdk.HFClient(accessToken);
                    const locale = document.documentElement.lang;
                    const languageCode = locale.split('_')[0];
                    this.sdkClient.setLanguage(languageCode);
                    this.sdkClient.setSupportedServices(issuers);

                    // Start session and update pay button state based on form validity
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

                    // Mount hosted fields concurrently using Promise.all
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

            onPayClick: async function(data, event) {
                event.preventDefault();
                this.isPayButtonDisabled(true);
                try {
                    const paymentToken = await this.sdkClient.submitSession();
                    if (!paymentToken) {
                        throw new Error("Failed to get encrypted card data.");
                    }
                    this.encryptedCardData = paymentToken;
                    this.service = this.sdkClient.getService();
                    this.finalizePlaceOrder(event);
                } catch (error) {
                    this.paymentError("Payment processing failed. Please try again.");
                    this.isPayButtonDisabled(false);
                }
            },

            /**
             * Place order.
             *
             * The placeOrderAction has been modified (Buckaroo_Magento2/js/action/place-order)
             * to prevent redirection and handle the response.
             */
            finalizePlaceOrder: function (event) {
                if (event) {
                    event.preventDefault();
                }

                if (!this.encryptedCardData) {
                    this.paymentError("Payment token is missing. Please try again.");
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
                            this.paymentError("Payment token is missing. Please try again.");
                        })
                        .done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            afterPlaceOrder: function () {
                const response = window.checkoutConfig.payment.buckaroo.response;
                checkoutCommon.redirectHandle(response);
            },

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

            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            }
        });
    }
);
