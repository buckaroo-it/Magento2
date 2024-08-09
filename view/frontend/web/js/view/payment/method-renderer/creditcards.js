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

            initialize: function (options) {
                this._super(options);
                this.getOAuthToken();
                return this;
            },

            initObservable: function () {
                this._super();
                return this;
            },

            /** Get the card issuer based on the creditcard number **/
            determineIssuer: function (cardNumber) {
                var issuers = {
                    'amex': {
                        'regex': '^3[47][0-9]{13}$',
                        'name': 'American Express'
                    },
                    'maestro': {
                        'regex': '^(5018|5020|5038|6304|6759|6761|6763)[0-9]{8,15}$',
                        'name': 'Maestro'
                    },
                    'dankort': {
                        'regex': '^(5019|4571)[0-9]{12}$',
                        'name': 'Dankort'
                    },
                    'mastercard': {
                        'regex': '^(5[1-5]|2[2-7])[0-9]{14}$',
                        'name': 'Mastercard'
                    },
                    'visaelectron': {
                        'regex': '^(4026[0-9]{2}|417500|4508[0-9]{2}|4844[0-9]{2}|4913[0-9]{2}|4917[0-9]{2})[0-9]{10}$',
                        'name': 'Visa Electron'
                    },
                    'visa': {
                        'regex': '^4[0-9]{12}(?:[0-9]{3})?$',
                        'name': 'Visa'
                    }
                };

                for (var key in issuers) {
                    if (cardNumber !== undefined && cardNumber.match(issuers[key].regex)) {
                        return issuers[key].name;
                    }
                }

                return false;
            },

            getOAuthToken: function () {
                var self = this;

                $.ajax({
                    url: urlBuilder.build('/buckaroo/credentialschecker/gettoken'),
                    type: "GET",
                    headers: {
                        'X-Requested-From': 'MagentoFrontend'
                    },
                    success: function (response) {
                        if (response.access_token) {
                            setTimeout(function() {
                                self.initHostedFields(response.access_token);
                            }, 5000); // Wait 5 seconds before initializing to ensure elements are loaded
                        } else {
                            console.error("Error getting OAuth token:", response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error getting OAuth token:", error);
                    }
                });
            },

            initHostedFields: function (accessToken) {
                var self = this;
                const init = async function () {
                    try {
                        var sdkClient = new BuckarooHostedFieldsSdk.HFClient(accessToken);
                        let service = "";
                        await sdkClient.startSession(function (event) {
                            sdkClient.handleValidation(event, 'cc-name-error', 'cc-number-error', 'cc-expiry-error', 'cc-cvc-error');

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

                            service = sdkClient.getService();
                        });

                        let styling = {
                            fontSize: "14px",
                            fontFamily: 'Consolas, Liberation Mono, Menlo, Courier, monospace',
                            textAlign: 'left',
                            background: 'inherit',
                            color: 'black',
                            placeholderColor: 'grey'
                        };

                        let chnameOptions = {
                            id: "ccname",
                            placeHolder: "John Doe",
                            labelSelector: "#cc-name-label",
                            baseStyling: styling
                        };

                        await sdkClient.mountCardHolderName("#cc-name-wrapper", chnameOptions)
                            .then(function (cardHolderNameField) {
                                cardHolderNameField.focus();
                            });

                        let ccOptions = {
                            id: "cc",
                            placeHolder: "555x xxxx xxxx xxxx",
                            labelSelector: "#cc-number-label",
                            baseStyling: styling
                        };

                        await sdkClient.mountCardNumber("#cc-number-wrapper", ccOptions);

                        let cvcOptions = {
                            id: "cvc",
                            placeHolder: "1234",
                            labelSelector: "#cc-cvc-label",
                            baseStyling: styling
                        };

                        await sdkClient.mountCvc("#cc-cvc-wrapper", cvcOptions);

                        let expiryDateOptions = {
                            id: "expiry",
                            placeHolder: "MM / YY",
                            labelSelector: "#cc-expiry-label",
                            baseStyling: styling
                        };

                        await sdkClient.mountExpiryDate("#cc-expiry-wrapper", expiryDateOptions);

                        let payButton = document.getElementById("pay");

                        if (payButton) {
                            payButton.addEventListener("click", async function (event) {
                                event.preventDefault();
                                try {
                                    let paymentToken = await sdkClient.submitSession();
                                    self.encryptedCardData = paymentToken;
                                    self.service = service;
                                    self.finalizePlaceOrder(event);
                                } catch (error) {
                                    console.error("Error during payment submission:", error);
                                }
                            });
                        }
                    } catch (error) {
                        console.error("Error initializing hosted fields:", error);
                    }
                };

                init();
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
