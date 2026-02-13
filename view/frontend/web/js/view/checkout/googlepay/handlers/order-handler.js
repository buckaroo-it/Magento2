/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
define(
    [
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
    ],
    function (
        $,
        urlBuilder,
        quote,
        customer
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
                    console.error('[GooglePay] No payment data available');
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
                    console.error('[GooglePay] No email provided by Google Pay - emailRequired may not be configured correctly');
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
                    showLoader: true,
                    data: addPayload,
                    success: function (addResponse) {
                        if (addResponse && !addResponse.error) {
                            self.saveOrder(transformedPaymentData, productData);
                        } else {
                            console.error('[GooglePay] Failed to add product:', addResponse);
                            alert('Failed to add product to cart: ' + (addResponse.error || 'Unknown error'));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('[GooglePay] Add product error:', error);
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
                    showLoader: true,
                    data: payload,
                    success: function (response) {
                        self.afterPlaceOrder(response);
                    },
                    error: function (xhr, status, error) {
                        console.error('[GooglePay] Order placement failed:', xhr.responseText);
                        alert('Order placement failed. Please try again.');
                    }
                });
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
                    }
                } else if (response && response.error) {
                    console.error('[GooglePay] Order error:', response.error);
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
                console.log('[GooglePay order-handler] Getting product data from page');

                // Try to get product data from the add to cart form
                var form = $('#product_addtocart_form');
                if (form.length) {
                    var formData = form.serializeArray();
                    var productData = {};
                    var selectedOptions = {};

                    $.each(formData, function(i, field) {
                        productData[field.name] = field.value;

                        // Extract super_attribute data for configurable products
                        // Format: super_attribute[142]=167 -> selected_options: {142: "167"}
                        var match = field.name.match(/^super_attribute\[(\d+)\]$/);
                        if (match) {
                            selectedOptions[match[1]] = field.value;
                        }
                    });

                    // Add selected_options to productData if any were found
                    if (Object.keys(selectedOptions).length > 0) {
                        productData.selected_options = selectedOptions;
                    }

                    return productData;
                }

                return {};
            }
        };
    }
);
