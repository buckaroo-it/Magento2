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
            // Validate product for express checkout if on product page
            if (this.page === 'product' && !this.validateProductForExpressCheckout()) {
                return false;
            }

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
                var apiData = {
                    page: productData.page,
                    orderData: productData.order_data
                };

                $.post(urlBuilder.build("rest/V1/buckaroo/ideal/quote/create"), apiData)
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
        },

        /**
         * Validate product before allowing express checkout
         * @returns {boolean}
         */
        validateProductForExpressCheckout: function () {
            var form = $('#product_addtocart_form');
            if (!form.length) {
                this.displayErrorMessage('Unable to find product form.');
                return false;
            }

            var productId = $('[name="product"]', form).val();
            var qty = $('[name="qty"]', form).val() || 1;

            if (!productId) {
                this.displayErrorMessage('Unable to identify product.');
                return false;
            }

            // Check if configurable product has all required options selected
            var missingOptions = [];

            // Check swatch attributes (color/size swatches)
            if ($('div.swatch-attribute').length > 0) {
                $('div.swatch-attribute').each(function() {
                    var attributeId = $(this).attr('attribute-id') || $(this).attr('data-attribute-id');
                    var optionSelected = $(this).attr('option-selected') || $(this).attr('data-option-selected');
                    var label = $(this).find('.swatch-attribute-label').text().replace('*', '').trim();

                    if (!optionSelected && attributeId) {
                        missingOptions.push(label || 'Option');
                    }
                });
            }

            // Check dropdown configurable options (select dropdowns)
            $('select[name*="super_attribute"]').each(function() {
                var selectValue = $(this).val();
                var fieldElement = $(this).closest('.field');
                var label = fieldElement.find('label span').text().trim();

                if (!selectValue || selectValue === '') {
                    missingOptions.push(label || 'Dropdown Option');
                }
            });

            if (missingOptions.length > 0) {
                this.displayErrorMessage('Please select: ' + missingOptions.join(', '));
                return false;
            }

            // Check for required custom options
            var hasRequiredOptions = false;
            $('.product-options-wrapper .field.required').each(function() {
                var input = $(this).find('input, select, textarea');
                var value = input.val();

                if (!value || value === '') {
                    hasRequiredOptions = true;
                    return false;
                }
            });

            if (hasRequiredOptions) {
                this.displayErrorMessage('This product has required options. Please make your selections before proceeding.');
                return false;
            }

            // Validate quantity
            if (qty < 1) {
                this.displayErrorMessage('Please enter a valid quantity.');
                return false;
            }

            return true;
        }
    });
});
