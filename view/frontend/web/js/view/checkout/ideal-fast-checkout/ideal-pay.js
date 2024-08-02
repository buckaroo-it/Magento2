define([
    'jquery',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Customer/js/model/customer',
    'Magento_Ui/js/modal/alert'
], function ($, urlBuilder, customerData, fullScreenLoader, $t, storage, errorProcessor, customer, alert) {
    'use strict';

    return {
        createQuoteAndPlaceOrder: function (productData) {
            fullScreenLoader.startLoader();

            // Add placeholders for required fields if not provided
            productData.shipping_address = productData.shipping_address || this.getDefaultAddress();
            this.page = productData.page;
            productData.order_data = this.getOrderData();

            // Create the quote
            $.post(urlBuilder.build("rest/V1/buckaroo/ideal/quote/create"), productData)
                .done(this.onQuoteCreateSuccess.bind(this, productData))
                .fail(this.onQuoteCreateFail.bind(this));
        },

        getDefaultAddress: function () {
            return {
                city: 'Placeholder',
                country_code: 'NL',
                postal_code: '00000',
                state: 'Placeholder',
                telephone: '0000000000',
            };
        },

        getOrderData: function () {
            let form = $("#product_addtocart_form");
            return this.page === 'product' ? form.serialize() : null;
        },

        onQuoteCreateSuccess: function (productData, quoteResponse) {
            var quoteId = quoteResponse.cart_id;

            // Proceed to place the order using the created quote ID
            this.placeOrder(quoteId, productData.paymentData);
        },

        onQuoteCreateFail: function () {
            this.displayErrorMessage($t('Unable to create quote.'));
            fullScreenLoader.stopLoader();
        },

        placeOrder: function (quoteId, paymentData) {
            var serviceUrl, payload;

            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.build(`rest/V1/guest-buckaroo/${quoteId}/payment-information`);
                payload = this.getPayload(quoteId, paymentData, 'guest');
            } else {
                serviceUrl = urlBuilder.build('rest/V1/buckaroo/payment-information');
                payload = this.getPayload(quoteId, paymentData, 'customer');
            }

            fullScreenLoader.startLoader();

            storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done(this.onOrderPlaceSuccess.bind(this))
                .fail(this.onOrderPlaceFail.bind(this));
        },

        getPayload: function (quoteId, paymentData, type) {
            var billingAddress = {
                city: 'Placeholder',
                country_id: 'NL',
                postcode: '00000',
                region: 'Placeholder',
                street: ['Placeholder Street'],
                telephone: '0000000000',
                firstname: 'Placeholder',
                lastname: 'Placeholder',
                email: 'placeholder@example.com'
            };

            return type === 'guest' ? {
                cartId: quoteId,
                email: 'placeholder@example.com',
                paymentMethod: paymentData,
                billingAddress: billingAddress
            } : {
                cartId: quoteId,
                paymentMethod: paymentData,
                billingAddress: billingAddress
            };
        },

        onOrderPlaceSuccess: function (response) {
            let jsonResponse;
            try {
                jsonResponse = $.parseJSON(response);
            } catch (e) {
                this.displayErrorMessage($t('An error occurred while processing your order.'));
                fullScreenLoader.stopLoader();
                return;
            }

            if (this.isLimitReached(jsonResponse)) {
                this.displayLimitReachedMessage(jsonResponse.buckaroo_response.limitReachedMessage);
                return;
            }

            this.updateOrder(jsonResponse);

            fullScreenLoader.stopLoader();
        },

        onOrderPlaceFail: function (response) {
            this.displayErrorMessage($t('An error occurred during payment.'));
            errorProcessor.process(response);
            fullScreenLoader.stopLoader();
        },

        isLimitReached: function (jsonResponse) {
            return typeof jsonResponse === 'object' && jsonResponse.buckaroo_response && jsonResponse.buckaroo_response.limitReachedMessage;
        },

        displayLimitReachedMessage: function (message) {
            alert({
                title: $t('Error'),
                content: $t(message),
                buttons: [{
                    text: $t('Close'),
                    class: 'action primary accept',
                    click: function () {
                        this.closeModal(true);
                    }
                }]
            });
            $('.' + paymentData.method).remove();
        },

        updateOrder: function (jsonResponse) {
            if (jsonResponse.buckaroo_response.RequiredAction && jsonResponse.buckaroo_response.RequiredAction.RedirectURL) {
                window.location.replace(jsonResponse.buckaroo_response.RequiredAction.RedirectURL);
            } else {
                window.location.replace(urlBuilder.build('checkout/onepage/success'));
            }
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