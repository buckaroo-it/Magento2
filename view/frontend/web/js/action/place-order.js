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
             * Support for PostNL postcode check and Buckaroo Postcode Service
             */
            var billingAddress = quote.billingAddress();

            if (billingAddress['extension_attributes'] === undefined) {
                billingAddress['extension_attributes'] = {};
            }
            // < M2.3.0
            if (billingAddress.customAttributes !== undefined && billingAddress.customAttributes.buckaroo_housenumber !== undefined) {
                billingAddress['extension_attributes']['buckaroo_housenumber']          = billingAddress.customAttributes.buckaroo_housenumber;
                billingAddress['extension_attributes']['buckaroo_housenumber_addition'] = billingAddress.customAttributes.buckaroo_housenumber_addition;
            }
            // >= M2.3.0
            if (billingAddress.customAttributes !== undefined && billingAddress.customAttributes[0] !== undefined && billingAddress.customAttributes[0].attribute_code === 'buckaroo_housenumber') {
                billingAddress['extension_attributes']['buckaroo_housenumber']          = billingAddress.customAttributes[0].value;
                billingAddress['extension_attributes']['buckaroo_housenumber_addition'] = billingAddress.customAttributes[1].value;
            }

            var shippingAddress = quote.shippingAddress();

            if (shippingAddress['extension_attributes'] === undefined) {
                shippingAddress['extension_attributes'] = {};
            }
            // < M2.3.0
            if (shippingAddress.customAttributes !== undefined && shippingAddress.customAttributes.buckaroo_housenumber !== undefined) {
                shippingAddress['extension_attributes']['buckaroo_housenumber']          = shippingAddress.customAttributes.buckaroo_housenumber;
                shippingAddress['extension_attributes']['buckaroo_housenumber_addition'] = shippingAddress.customAttributes.buckaroo_housenumber_addition;
            }
            // >= M2.3.0
            if (shippingAddress.customAttributes !== undefined && shippingAddress.customAttributes[0] !== undefined && shippingAddress.customAttributes[0].attribute_code === 'buckaroo_housenumber') {
                shippingAddress['extension_attributes']['buckaroo_housenumber']          = shippingAddress.customAttributes[0].value;
                shippingAddress['extension_attributes']['buckaroo_housenumber_addition'] = shippingAddress.customAttributes[1].value;
            }

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
