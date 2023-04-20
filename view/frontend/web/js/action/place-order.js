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
        'Magento_CheckoutAgreements/js/model/agreements-assigner',
        'jquery',
        'Magento_Ui/js/modal/alert',
        'mage/translate'
    ],
    function (
        quote,
        urlBuilder,
        storage,
        url,
        errorProcessor,
        customer,
        fullScreenLoader,
        agreementsAssigner,
        $,
        alert,
        $t
    ) {
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
            if (billingAddress.customAttributes !== undefined && billingAddress.customAttributes.tig_housenumber !== undefined) {
                billingAddress['extension_attributes']['tig_housenumber']          = billingAddress.customAttributes.tig_housenumber;
                billingAddress['extension_attributes']['tig_housenumber_addition'] = billingAddress.customAttributes.tig_housenumber_addition;
            }
            // >= M2.3.0
            if (billingAddress.customAttributes !== undefined && billingAddress.customAttributes[0] !== undefined && billingAddress.customAttributes[0].attribute_code === 'tig_housenumber') {
                billingAddress['extension_attributes']['tig_housenumber']          = billingAddress.customAttributes[0].value;
                billingAddress['extension_attributes']['tig_housenumber_addition'] = billingAddress.customAttributes[1].value;
            }

            var shippingAddress = quote.shippingAddress();

            if (shippingAddress['extension_attributes'] === undefined) {
                shippingAddress['extension_attributes'] = {};
            }
            // < M2.3.0
            if (shippingAddress.customAttributes !== undefined && shippingAddress.customAttributes.tig_housenumber !== undefined) {
                shippingAddress['extension_attributes']['tig_housenumber']          = shippingAddress.customAttributes.tig_housenumber;
                shippingAddress['extension_attributes']['tig_housenumber_addition'] = shippingAddress.customAttributes.tig_housenumber_addition;
            }
            // >= M2.3.0
            if (shippingAddress.customAttributes !== undefined && shippingAddress.customAttributes[0] !== undefined && shippingAddress.customAttributes[0].attribute_code === 'tig_housenumber') {
                shippingAddress['extension_attributes']['tig_housenumber']          = shippingAddress.customAttributes[0].value;
                shippingAddress['extension_attributes']['tig_housenumber_addition'] = shippingAddress.customAttributes[1].value;
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
                    let jsonResponse = $.parseJSON(response);
                    if (typeof jsonResponse === 'object' && typeof jsonResponse.limitReachedMessage === 'string') {
                        alert({
                            title: $t('Error'),
                            content: $t(jsonResponse.limitReachedMessage),
                            buttons: [{
                            text: $t('Close'),
                            class: 'action primary accept',
                                click: function () {
                                    this.closeModal(true);
                                }
                            }]
                        });
                        $('.' + paymentData.method).remove();
                    } else if (redirectOnSuccess) {
                        window.location.replace(url.build('checkout/onepage/success/'));
                    }
                    window.checkoutConfig.payment.buckaroo.response = response;
                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                    if (paymentData && paymentData.method && (paymentData.method == 'buckaroo_magento2_afterpay20')) {
                        setInterval(function () {
                            if (document.querySelector('.buckaroo_magento2_afterpay20.payment-method')) {
                                var y = window.scrollY;
                                window.scroll(0, 0);  // reset the scroll position to the top left of the document.
                                window.scroll(0, y);
                            }
                        }, 2000);

                    }
                }
            );
        };
    }
);
