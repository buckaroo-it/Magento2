/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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
                var response = window.checkoutConfig.payment.buckaroo.responseData;

                if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                    window.location.replace(response.RequiredAction.RedirectURL);
                }
            },

            getData: function () {
                var transactionData = this.formatTransactionResponse();

                return {
                    "method": 'buckaroo_magento2_applepay',
                    "po_number": null,
                    "additional_data": {
                        "applepayTransaction" : transactionData
                    }
                };
            },

            formatTransactionResponse: function () {
                if (null === this.applepayTransaction || 'undefined' === this.applepayTransaction) {
                    return null;
                }

                var paymentData = this.applepayTransaction.token.paymentData;

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
            }
        };
    }
);
