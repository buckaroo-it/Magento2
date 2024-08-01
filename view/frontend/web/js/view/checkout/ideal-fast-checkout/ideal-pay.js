define([
    'jquery',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Customer/js/model/customer',
    'Magento_Ui/js/modal/alert',
    'buckaroo/checkout/common'
], function ($, urlBuilder, customerData, fullScreenLoader, $t, storage, errorProcessor, customer, alert, checkoutCommon) {
    'use strict';
    let page;
    return {
        createQuoteAndPlaceOrder: function (productData) {
            var self = this;
            fullScreenLoader.startLoader();
            console.log(productData);

            // Add placeholders for required fields if not provided
            productData.shipping_address = productData.shipping_address || {
                city: 'Placeholder',
                country_code: 'NL',
                postal_code: '00000',
                state: 'Placeholder',
                telephone: '0000000000',
            };
            this.page = productData.page
            productData.order_data = this.getOrderData();

            // Create the quote
            $.post(urlBuilder.build("rest/V1/buckaroo/ideal/quote/create"), productData)
                .done(function (quoteResponse) {
                    var quoteId = quoteResponse.cart_id;
                    console.log(productData)
                    // Proceed to place the order using the created quote ID
                    self.placeOrder(quoteId, productData.paymentData);
                })
                .fail(function (error) {
                    self.displayErrorMessage($t('Unable to create quote.'));
                    fullScreenLoader.stopLoader();
                });
        },

        getOrderData() {
            let form = $("#product_addtocart_form");
            if (this.page === 'product') {
                return form.serialize();
            }
        },

        placeOrder: function (quoteId, paymentData) {
            var self = this;
            var serviceUrl, payload;

            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.build(`rest/V1/guest-buckaroo/${quoteId}/payment-information`);

                payload = {
                    cartId: quoteId,
                    email: 'placeholder@example.com',
                    paymentMethod: paymentData,
                    billingAddress: {
                        city: 'Placeholder',
                        country_id: 'NL',
                        postcode: '00000',
                        region: 'Placeholder',
                        street: ['Placeholder Street'],
                        telephone: '0000000000',
                        firstname: 'Placeholder',
                        lastname: 'Placeholder',
                        email: 'placeholder@example.com'
                    }
                };
            } else {
                serviceUrl = urlBuilder.build('rest/V1/buckaroo/payment-information');
                payload = {
                    cartId: quoteId,
                    paymentMethod: paymentData,
                    billingAddress: {
                        city: 'Placeholder',
                        country_id: 'NL',
                        postcode: '00000',
                        region: 'Placeholder',
                        street: ['Placeholder Street'],
                        telephone: '0000000000',
                        firstname: 'Placeholder',
                        lastname: 'Placeholder',
                        email: 'placeholder@example.com'
                    }
                };
            }

            fullScreenLoader.startLoader();

            storage.post(
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
                    } else {
                        if (jsonResponse.RequiredAction && jsonResponse.RequiredAction.RedirectURL) {
                            window.location.replace(jsonResponse.RequiredAction.RedirectURL);
                        } else {
                            window.location.replace(urlBuilder.build('checkout/onepage/success'));
                        }
                    }
                    fullScreenLoader.stopLoader();

                }
            ).fail(
                function (response) {
                    this.displayErrorMessage(response);
                    errorProcessor.process(response);
                    fullScreenLoader.stopLoader();
                }
            );
        },

        displayErrorMessage: function (message) {
            customerData.set('messages', {
                messages: [{
                    type: 'error',
                    text: message
                }]
            });
        }
    };
});
