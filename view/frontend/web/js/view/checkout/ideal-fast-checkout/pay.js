define([
    'jquery',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'mage/translate',
    'mage/storage',
    'Magento_Checkout/js/model/full-screen-loader'
], function ($, urlBuilder, customerData, $t, storage, fullScreenLoader) {
    'use strict';

    return {
        createQuoteAndPlaceOrder: function (productData) {
            this.page = productData.page;
            productData.order_data = this.getOrderData();

            var customerDataObject = customerData.get('customer');
            customerDataObject.subscribe(function (updatedCustomer) {
            }.bind(this));

            fullScreenLoader.startLoader(); // Show the loader

            this.processOrderFlow(productData);
        },

        processOrderFlow: function (productData) {
            // Create the quote
            $.post(urlBuilder.build("rest/V1/buckaroo/ideal/quote/create"), productData)
                .done(this.onQuoteCreateSuccess.bind(this, productData))
                .fail(this.onQuoteCreateFail.bind(this))
                .always(fullScreenLoader.stopLoader); // Hide the loader when the request is done
        },

        getOrderData: function () {
            let form = $("#product_addtocart_form");
            return this.page === 'product' ? form.serialize() : null;
        },

        onQuoteCreateSuccess: function (productData, quoteResponse) {
            var quoteId = quoteResponse.cart_id;
            this.placeOrder(quoteId, productData.paymentData);
        },

        onQuoteCreateFail: function () {
            this.displayErrorMessage($t('Unable to create quote.'));
            fullScreenLoader.stopLoader(); // Hide the loader on failure
        },

        placeOrder: function (quoteId, paymentData) {
            var serviceUrl, payload;
            var customerDataObject = customerData.get('customer');

            // Determine the appropriate service URL and payload based on login status
            if (!customerDataObject().firstname) {
                serviceUrl = urlBuilder.build(`rest/V1/guest-buckaroo/${quoteId}/payment-information`);
                payload = this.getPayload(quoteId, paymentData, 'guest');
            } else {
                serviceUrl = urlBuilder.build('rest/V1/buckaroo/payment-information');
                payload = this.getPayload(quoteId, paymentData, 'customer');
            }

            storage.post(serviceUrl, JSON.stringify(payload))
                .done(this.onOrderPlaceSuccess.bind(this))
                .fail(this.onOrderPlaceFail.bind(this))
                .always(fullScreenLoader.stopLoader); // Hide the loader when the request is done
        },

        getPayload: function (quoteId, paymentData, type) {
            return type === 'guest' ? {
                cartId: quoteId,
                email: 'guest@example.com',
                paymentMethod: paymentData,
            } : {
                cartId: quoteId,
                paymentMethod: paymentData,
            };
        },

        onOrderPlaceSuccess: function (response) {
            let jsonResponse;
            try {
                jsonResponse = $.parseJSON(response);
            } catch (e) {
                this.displayErrorMessage($t('An error occurred while processing your order.'));
                return;
            }

            this.updateOrder(jsonResponse);
        },

        onOrderPlaceFail: function (response) {
            this.displayErrorMessage($t(response));
        },

        updateOrder: function (jsonResponse) {
            if (jsonResponse.buckaroo_response.RequiredAction && jsonResponse.buckaroo_response.RequiredAction.RedirectURL) {
                window.location.replace(jsonResponse.buckaroo_response.RequiredAction.RedirectURL);
            } else {
                window.location.replace(urlBuilder.build('checkout/onepage/success'));
            }
        },

        displayErrorMessage: function (message) {
            if (typeof message === "object") {
                if (message.responseJSON && message.responseJSON.message) {
                    message = $t(message.responseJSON.message);
                } else {
                    message = $t("Cannot create payment");
                }

            }
            customerData.set('messages', {
                messages: [{
                    type: 'error',
                    text: message
                }]
            });
        }
    };
});
