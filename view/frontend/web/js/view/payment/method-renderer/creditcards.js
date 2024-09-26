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

            // Error message observables
            cardholderNameError: ko.observable(''),
            cardNumberError: ko.observable(''),
            expiryError: ko.observable(''),
            cvcError: ko.observable(''),
            oauthTokenError: ko.observable(''),

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

                    if (response.access_token) {
                        await this.initHostedFields(response.access_token);
                    } else {
                        this.oauthTokenError("Error getting OAuth token: " + response.error);
                    }
                } catch (error) {
                    this.oauthTokenError("Error getting OAuth token: " + response.error);
                }
            },

            async initHostedFields(accessToken) {
                try {
                    const sdkClient = new BuckarooHostedFieldsSdk.HFClient(accessToken);

                    await sdkClient.startSession(event => {
                        sdkClient.handleValidation(event, 'cc-name-error', 'cc-number-error', 'cc-expiry-error', 'cc-cvc-error');

                        // Dynamically update the error messages using observables
                        this.cardholderNameError(event.errors['cc-name-error'] || '');
                        this.cardNumberError(event.errors['cc-number-error'] || '');
                        this.expiryError(event.errors['cc-expiry-error'] || '');
                        this.cvcError(event.errors['cc-cvc-error'] || '');

                        let payButton = document.getElementById("pay");
                        if (payButton) {
                            let disabled = !sdkClient.formIsValid();
                            payButton.disabled = disabled;
                            if (disabled) {
                                payButton.style.backgroundColor = "#ff5555";
                                payButton.style.cursor = "not-allowed";
                                payButton.style.opacity = "0.5";
                            } else {
                                payButton.style.backgroundColor = "";
                                payButton.style.cursor = "";
                                payButton.style.opacity = "";
                            }
                        }

                        this.service = sdkClient.getService();
                    });

                    // Define styling and mount hosted fields as needed...
                    let styling = {
                        fontSize: "14px",
                        fontFamily: 'Consolas, Liberation Mono, Menlo, Courier, monospace',
                        textAlign: 'left',
                        background: 'inherit',
                        color: 'black',
                        placeholderColor: 'grey'
                    };

                    await sdkClient.mountCardHolderName("#cc-name-wrapper", {
                        id: "ccname",
                        placeHolder: "John Doe",
                        labelSelector: "#cc-name-label",
                        baseStyling: styling
                    }).then(field => field.focus());

                    await sdkClient.mountCardNumber("#cc-number-wrapper", {
                        id: "cc",
                        placeHolder: "555x xxxx xxxx xxxx",
                        labelSelector: "#cc-number-label",
                        baseStyling: styling
                    });

                    await sdkClient.mountCvc("#cc-cvc-wrapper", {
                        id: "cvc",
                        placeHolder: "1234",
                        labelSelector: "#cc-cvc-label",
                        baseStyling: styling
                    });

                    await sdkClient.mountExpiryDate("#cc-expiry-wrapper", {
                        id: "expiry",
                        placeHolder: "MM / YY",
                        labelSelector: "#cc-expiry-label",
                        baseStyling: styling
                    });

                    let payButton = document.getElementById("pay");
                    if (payButton) {
                        payButton.addEventListener("click", async function (event) {
                            event.preventDefault();
                            payButton.disabled = true; // Disable button to prevent double submissions

                            try {
                                let paymentToken = await sdkClient.submitSession();
                                if (!paymentToken) {
                                    throw new Error("Failed to get encrypted card data.");
                                }
                                this.encryptedCardData = paymentToken;
                                this.service = sdkClient.getService();
                                this.finalizePlaceOrder(event);
                            } catch (error) {
                                console.error("Error during payment submission:", error);
                                payButton.disabled = false; // Re-enable button if there's an error
                            }
                        }.bind(this));
                    }
                } catch (error) {
                    console.error("Error initializing hosted fields:", error);
                }
            },

            /**
             * Place order.
             *
             * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
             * (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
             */
            finalizePlaceOrder: function (event) {
                var self = this,
                    placeOrder;

                if (event) {
                    event.preventDefault();
                }

                if (!this.encryptedCardData) {
                    console.error("Payment token is missing. Please try again.");
                    return;
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(self.getData(), self.redirectAfterPlaceOrder, self.messageContainer);

                    $.when(placeOrder).fail(
                        function () {
                            self.isPlaceOrderActionAllowed(true);
                        }
                    ).done(self.afterPlaceOrder.bind(self));
                    return true;
                }
                return false;
            },

            afterPlaceOrder: function () {
                var response = window.checkoutConfig.payment.buckaroo.response;
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
                }
            },

            selectPaymentMethod: function () {
                window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            }
        });
    }
);
