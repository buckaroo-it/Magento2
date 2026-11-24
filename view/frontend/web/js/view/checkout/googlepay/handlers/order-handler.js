/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
define(
    [
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Buckaroo_Magento2/js/view/checkout/payment-processing',
        'Buckaroo_Magento2/js/view/checkout/product-selection'
    ],
    function (
        $,
        urlBuilder,
        quote,
        customer,
        paymentProcessing,
        productSelection
    ) {
        'use strict';

        return {
            googlepayPaymentData: null,
            currentProduct: null,

            setGooglepayPaymentData: function (paymentData) {
                this.googlepayPaymentData = paymentData;
            },

            setProduct: function (product) {
                this.currentProduct = product;
            },

            placeOrder: function () {
                if (!this.googlepayPaymentData) {
                    return;
                }

                var self = this;

                // Collect product data from the page
                var productData = this.currentProduct || this.getProductDataFromPage();

                // Extract shipping and billing address from Google Pay data
                var shippingAddress = this.googlepayPaymentData.shippingAddress || {};
                var email = this.googlepayPaymentData.email || this.googlepayPaymentData.emailAddress || '';

                // Email should always be provided by Google Pay when emailRequired: true
                if (!email || email === '') {
                }

                // Transform Google Pay address format
                // Google Pay returns: {name, address1, address2, address3, locality, administrativeArea, postalCode, countryCode, phoneNumber}
                // Need to convert to: {givenName, familyName, addressLines, locality, administrativeArea, postalCode, countryCode, phoneNumber}
                var transformedPaymentData = JSON.parse(JSON.stringify(this.googlepayPaymentData));

                if (shippingAddress && Object.keys(shippingAddress).length > 0) {
                    var nameParts = (shippingAddress.name || '').split(' ');
                    transformedPaymentData.shippingContact = {
                        givenName: nameParts[0] || '',
                        familyName: nameParts.slice(1).join(' ') || nameParts[0] || '',
                        addressLines: [
                            shippingAddress.address1 || '',
                            shippingAddress.address2 || '',
                            shippingAddress.address3 || ''
                        ].filter(Boolean),
                        locality: shippingAddress.locality || '',
                        administrativeArea: shippingAddress.administrativeArea || '',
                        postalCode: shippingAddress.postalCode || '',
                        countryCode: shippingAddress.countryCode || '',
                        phoneNumber: shippingAddress.phoneNumber || email,
                        emailAddress: email
                    };
                    transformedPaymentData.billingContact = transformedPaymentData.shippingContact;
                }

                // Check if we have product data (product page flow) or if we're in checkout
                var hasProductId = productData && (productData.product || productData.id);
                var hasProductForm = $('#product_addtocart_form').length > 0;
                var isProductPage = hasProductId || hasProductForm;

                if (isProductPage) {
                    this.addProductAndPlaceOrder(productData, transformedPaymentData);
                } else {
                    this.saveOrder(transformedPaymentData, productData);
                }
            },

            addProductAndPlaceOrder: function(productData, transformedPaymentData) {
                var self = this;

                // Step 1: Call Add endpoint to create quote and add product
                var addPayload = {
                    product: {
                        id: productData.product,
                        qty: productData.qty || 1,
                        selected_options: productData.selected_options || {}
                    },
                    wallet: this.googlepayPaymentData.shippingAddress
                };

                $.ajax({
                    url: urlBuilder.build('buckaroo/googlepay/add'),
                    type: 'POST',
                    dataType: 'json',
                    // The payment-processing overlay is already shown; a second loader would stack on it
                    showLoader: false,
                    data: addPayload,
                    success: function (addResponse) {
                        if (addResponse && !addResponse.error) {
                            self.saveOrder(transformedPaymentData, productData);
                        } else {
                            self.settle(false);
                            alert('Failed to add product to cart: ' + (addResponse.error || 'Unknown error'));
                        }
                    },
                    error: function (xhr, status, error) {
                        self.settle(false);
                        alert('Failed to add product to cart');
                    }
                });
            },

            saveOrder: function(transformedPaymentData, productData) {
                var self = this;

                // Prepare payload for SaveOrder endpoint
                // Note: googlepayPaymentData must be a JSON string for the DataBuilder
                var payload = {
                    payment: JSON.stringify(transformedPaymentData),
                    extra: JSON.stringify({
                        product: productData,
                        isLoggedIn: customer.isLoggedIn(),
                        googlepayPaymentData: JSON.stringify(this.googlepayPaymentData)  // Must be string for DataBuilder
                    })
                };

                // Create order via AJAX
                $.ajax({
                    url: urlBuilder.build('buckaroo/googlepay/saveOrder'),
                    type: 'POST',
                    dataType: 'json',
                    // The payment-processing overlay is already shown; a second loader would stack on it
                    showLoader: false,
                    data: payload,
                    success: function (response) {
                        self.settle(!!(response && response.success));
                        self.afterPlaceOrder(response);
                    },
                    error: function (xhr, status, error) {
                        self.settle(false);
                        alert('Order placement failed. Please try again.');
                    }
                });
            },

            /**
             * Tell the wallet sheet how the order request ended (set by the button that started it)
             *
             * @param {Boolean} succeeded
             */
            settle: function (succeeded) {
                if (typeof this.onSettled === 'function') {
                    this.onSettled(succeeded);
                }
            },

            afterPlaceOrder: function (response) {
                if (response && response.success) {
                    // Check for redirect URL in the response data
                    var redirectUrl = null;

                    // Check for RequiredAction.RedirectURL (from Buckaroo response)
                    if (response.data && response.data.RequiredAction && response.data.RequiredAction.RedirectURL) {
                        redirectUrl = response.data.RequiredAction.RedirectURL;
                    }
                    // Fallback to flat redirectUrl property
                    else if (response.redirectUrl) {
                        redirectUrl = response.redirectUrl;
                    }
                    // Fallback to data.redirectUrl property
                    else if (response.data && response.data.redirectUrl) {
                        redirectUrl = response.data.redirectUrl;
                    }

                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        // Nowhere to send the shopper: reload so the page shows the placed order, as Apple Pay does
                        paymentProcessing.hide();
                        window.location.reload();
                    }
                } else if (response && response.error) {
                    alert(response.error);
                }
            },

            getData: function () {
                return {
                    "method": 'buckaroo_magento2_googlepay',
                    "po_number": null,
                    "additional_data": {
                        "googlepayPaymentData": JSON.stringify(this.googlepayPaymentData)
                    }
                };
            },

            getProductDataFromPage: function () {
                var selection = productSelection.read();

                // No product on this page (cart): the order is placed from the current cart
                if (!selection.id) {
                    return {};
                }

                return {
                    product: selection.id,
                    qty: selection.qty,
                    selected_options: selection.selected_options
                };
            }
        };
    }
);
