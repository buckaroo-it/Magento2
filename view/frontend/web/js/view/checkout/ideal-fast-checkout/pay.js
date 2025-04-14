define([
    'uiComponent',
    'mage/url',
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate',
    'mage/storage'
], function (Component, urlBuilder, $, customerData, $t, storage) {
    'use strict';

    return Component.extend({
        page: null,
        paymentData: null,

        initialize: function (config) {
            this._super();

            this.page = config.page;
            this.paymentData = config.paymentData;

            var customerDataObject = customerData.get('customer');
            customerDataObject.subscribe(function (updatedCustomer) {
            }.bind(this));


            $(document).on('click', '#fast-checkout-ideal-btn', function() {
                this.onCheckout();
            }.bind(this));
        },

        onCheckout: function () {
            var qty = $("#qty").val();

            var productData = {
                qty: qty,
                page: this.page,
                paymentData: this.paymentData,
                order_data: this.getOrderData()
            };

            this.createQuoteAndPlaceOrder(productData);
        },

        createQuoteAndPlaceOrder: function (productData) {
            this.showLoader();

            this.processOrderFlow(productData)
                .then(this.onQuoteCreateSuccess.bind(this, productData))
                .catch(this.onQuoteCreateFail.bind(this));
        },

        processOrderFlow: function (productData) {
            return new Promise((resolve, reject) => {
                $.post(urlBuilder.build("rest/V1/buckaroo/ideal/quote/create"), productData)
                    .done((response) => resolve(response))
                    .fail((error) => reject(error));
            });
        },

        getOrderData: function () {
            let form = $("#product_addtocart_form");
            return this.page === "product" ? form.serialize() : null;
        },

        onQuoteCreateSuccess: function (productData, quoteResponse) {
            var quoteId = quoteResponse.cart_id;
            this.placeOrder(quoteId, productData.paymentData)
                .then(this.onOrderPlaceSuccess.bind(this))
                .catch(this.onOrderPlaceFail.bind(this));
        },

        onQuoteCreateFail: function (error) {
            this.hideLoader();
            this.displayErrorMessage($t('Unable to create quote.'));
        },

        placeOrder: function (quoteId, paymentData) {
            var serviceUrl, payload;
            var customerDataObject = customerData.get('customer');

            if (!customerDataObject().firstname) {
                serviceUrl = urlBuilder.build(`rest/V1/guest-buckaroo/${quoteId}/payment-information`);
                payload = this.getPayload(quoteId, paymentData, 'guest');
            } else {
                serviceUrl = urlBuilder.build('rest/V1/buckaroo/payment-information');
                payload = this.getPayload(quoteId, paymentData, 'customer');
            }

            return new Promise((resolve, reject) => {
                storage.post(serviceUrl, JSON.stringify(payload))
                    .done((response) => resolve(response))
                    .fail((error) => reject(error));
            });
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
            this.hideLoader();
            let jsonResponse;
            try {
                jsonResponse = $.parseJSON(response);
            } catch (e) {
                this.displayErrorMessage($t('An error occurred while processing your order.'));
                return;
            }

            this.updateOrder(jsonResponse);
        },

        onOrderPlaceFail: function (error) {
            this.hideLoader();
            this.displayErrorMessage(error);
        },

        updateOrder: function (jsonResponse) {
            if (jsonResponse.RequiredAction && jsonResponse.RequiredAction.RedirectURL) {
                window.location.replace(jsonResponse.RequiredAction.RedirectURL);
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

            $('<div class="message message-error error"><div>' + message + '</div></div>').appendTo('.page.messages').show();
        },

        showLoader: function () {
            $('body').loader('show');
        },

        hideLoader: function () {
            $('body').loader('hide');
        }
    });
});
