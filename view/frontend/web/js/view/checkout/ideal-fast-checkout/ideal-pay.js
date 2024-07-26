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

        // console.log("HEREEqqqq");
        return function (paymentData, redirectOnSuccess, messageContainer) {
            var serviceUrl,
                payload;

            // console.log("AAAAA");
            // console.log(paymentData);
            redirectOnSuccess = redirectOnSuccess !== false;
            // console.log("BBBB");
            // agreementsAssigner(paymentData);

            // console.log("Processing Buckaroo iDEAL payment");

            // paymentData["method"] = "buckaroo_magento2_ideal";



            paymentData = {
                method: "buckaroo_magento2_ideal",
                // cartId: "cJ9ZIr1cJZdIygX8R1V3guLH5m0fSFWc",
                po_number: null,
                additional_data:
                    {issuer: "ASNBNL21"}

            };
            /**
             * Checkout for guest and registered customer.
             */
            if (!customer.isLoggedIn()) {
                // console.log(quote);
                serviceUrl = urlBuilder.createUrl(
                    '/guest-buckaroo/:quoteId/payment-information',
                    {
                        // quoteId: quote.getQuoteId()
                        quoteId: "7N8n502viMwTWodLDEG3wymEJgJOGB80"
                    }
                );
                // console.log("55555");
                payload = {
                    cartId: "7N8n502viMwTWodLDEG3wymEJgJOGB80",
                    email: "albina@random.com",
                    paymentMethod: paymentData
                };
                // console.log("5656");
                // console.log(payload);

            } else {
                serviceUrl = urlBuilder.createUrl('/buckaroo/payment-information', {});
                // console.log("Service URL:", serviceUrl);
                payload = {
                    cartId: quote.getQuoteId(),
                    paymentMethod: paymentData
                };
            }

            // console.log(serviceUrl);



            serviceUrl = 'rest/default/V1/guest-buckaroo/7N8n502viMwTWodLDEG3wymEJgJOGB80/payment-information'

            // console.log("TESSSST")
            // console.log(serviceUrl)

            // fullScreenLoader.startLoader();

            // console.log("8888");
            // console.log(payload);
            return storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done(
                function (response) {
                    // console.log("999");
                    let jsonResponse = $.parseJSON(response);
                    if (typeof jsonResponse === 'object' && typeof jsonResponse.limitReachedMessage === 'string') {
                        // console.log("GGGGGGGGGGG");
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
                        console.log("SSSSSSSS")
                        window.location.replace(url.build('checkout/onepage/success/'));
                    }
                    window.checkoutConfig.payment.buckaroo.response = response;
                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function (response) {
                    // console.log("VVVVV");
                    // console.log(response);
                    // console.log("VVVVV");
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
