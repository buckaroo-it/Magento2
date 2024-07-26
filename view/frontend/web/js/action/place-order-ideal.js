/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
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



            console.log("ALBINAAA111");

            redirectOnSuccess = redirectOnSuccess !== false;
            agreementsAssigner(paymentData);
            console.log("ALBINAAA222");


            /**
             * Checkout for guest and registered customer.
             */
            // if (!customer.isLoggedIn()) {
            //     serviceUrl = urlBuilder.createUrl(
            //         '/guest-buckaroo/:quoteId/payment-information');
            //     payload = {
            //         email: quote.guestEmail,
            //         paymentMethod: paymentData,
            //     };
            // } else {
                serviceUrl = urlBuilder.createUrl('/buckaroo/payment-information', {});
            console.log("ALBINAAA");
                console.log(serviceUrl);
            console.log("ALBINAAA");

            payload = {
                    paymentMethod: paymentData,
                // };
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
        window.placeOrder = placeOrder;
        return placeOrder;
    }
);
