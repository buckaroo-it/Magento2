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
        'Magento_Checkout/js/model/quote',
        'buckaroo/applepay/shipping-handler',
        'buckaroo/applepay/billing-handler',
        'Buckaroo_Magento2/js/action/place-order',
    ],
    function (
        $,
        quote,
        shippingHandler,
        billingHandler,
        placeOrderAction,
    ) {
        'use strict';

        return {
            applepayTransaction : null,

            setApplepayTransaction: function (newTransaction) {
                this.applepayTransaction = newTransaction;
            },

            placeOrder: function () {
                quote.guestEmail = this.applepayTransaction.shippingContact.emailAddress;

                var shipingAddress = this.applepayTransaction.shippingContact;
                var billingAddress = this.applepayTransaction.billingContact;

                billingAddress.emailAddress = shipingAddress.emailAddress;
                billingAddress.phoneNumber = shipingAddress.phoneNumber;

                shippingHandler.setShippingAddress(shipingAddress);
                shippingHandler.saveShipmentInfo();
                billingHandler.setBillingAddress(billingAddress);
                billingHandler.selectPaymentMethod(this.getData());
                billingHandler.savePaymentInfo();

                var placeOrder = placeOrderAction(this.getData(), true, null);

                $.when(placeOrder).done(this.afterPlaceOrder.bind(this));
            },

            afterPlaceOrder: function () {
                var response = window.checkoutConfig.payment.buckaroo.response;

                if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                    window.location.replace(response.RequiredAction.RedirectURL);
                }
            },

            getData: function () {
                var transactionData = this.formatTransactionResponse();

                if (!transactionData || transactionData === 'null') {
                    throw new Error('Apple Pay transaction data is invalid. Please try again.');
                }

                return {
                    "method": 'buckaroo_magento2_applepay',
                    "po_number": null,
                    "additional_data": {
                        "applepayTransaction" : transactionData
                    }
                };
            },

            formatTransactionResponse: function () {
                if (null === this.applepayTransaction || 'undefined' === typeof this.applepayTransaction) {
                    return null;
                }

                try {
                    if (!this.applepayTransaction.token || !this.applepayTransaction.token.paymentData) {
                        return null;
                    }

                    var paymentData = this.applepayTransaction.token.paymentData;

                    if (!paymentData.data || !paymentData.signature || !paymentData.header) {
                        return null;
                    }

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
                } catch (error) {
                    return null;
                }
            }
        };
    }
);
