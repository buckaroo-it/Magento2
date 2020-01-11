/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'mage/url',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_CheckoutAgreements/js/model/agreements-assigner'
    ],
    function (quote, urlBuilder, storage, url, errorProcessor, customer, fullScreenLoader, agreementsAssigner) {
        'use strict';

        return function (paymentData, redirectOnSuccess, messageContainer) {
            var serviceUrl,
                payload;

            redirectOnSuccess = redirectOnSuccess !== false;
            agreementsAssigner(paymentData);

            /**
             * Checkout for guest and registered customer.
             */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl(
                    '/guest-buckaroo/:quoteId/payment-information',
                    {
                        quoteId: quote.getQuoteId()
                    }
                );
                payload = {
                    cartId: quote.getQuoteId(),
                    email: quote.guestEmail,
                    paymentMethod: paymentData,
                    billingAddress: quote.billingAddress()
                };
            } else {
                serviceUrl = urlBuilder.createUrl('/buckaroo/payment-information', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    paymentMethod: paymentData,
                    billingAddress: quote.billingAddress()
                };
            }

            fullScreenLoader.startLoader();

            return storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done(
                function (response) {
                    if (redirectOnSuccess) {
                        window.location.replace(url.build('checkout/onepage/success/'));
                    }
                    window.checkoutConfig.payment.buckaroo.response = response;
                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
