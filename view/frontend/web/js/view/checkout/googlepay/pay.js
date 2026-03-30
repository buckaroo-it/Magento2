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
        'ko',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Customer/js/customer-data',
        'mage/translate',
        'BuckarooSdk'
    ],
    function (
        $,
        ko,
        urlBuilder,
        quote,
        totals,
        additionalValidators,
        customerData,
        $t
    ) {
        'use strict';


        var transactionResult = ko.observable(null);
        var canShowMethod = ko.observable(false);

        return {
            transactionResult: transactionResult,
            canShowMethod: canShowMethod,
            googlePayPayment: null,
            isOnCheckout: false,
            quote: null,
            productSelected: {
                id: null,
                qty: 1,
                unitPrice: null,
                selected_options: {}
            },
            // Store shipping prices separately (not in shippingOptions per Google Pay spec)
            shippingPriceById: {},

            /**
             * Show Google Pay button
             * @param {string} payMode - 'product', 'cart', or undefined for checkout
             */
            showPayButton: function (payMode) {
                var self = this;

                // Set pay mode
                this.payMode = payMode;

                if (typeof window.checkoutConfig === 'undefined') {
                    console.error('[GooglePay] checkoutConfig is undefined');
                    return;
                }

                var config = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_googlepay;
                if (!config) {
                    console.error('[GooglePay] Config not found');
                    return;
                }

                // Set checkout mode based on payMode
                this.setIsOnCheckout((this.payMode !== 'product' && this.payMode !== 'cart'));

                // Initialize product view watchers for product pages (Apple Pay approach)
                if (this.payMode === 'product') {
                    this.initProductViewWatchers();
                }

                // Generate Google Pay options
                var googlePayOptions = this.generateGooglePayOptions();

                // Check if options generation failed (e.g., couldn't get product price)
                if (!googlePayOptions) {
                    console.error('[GooglePay pay.js] Failed to generate Google Pay options - cannot show button');
                    canShowMethod(false);
                    return;
                }

                // Prevent duplicate button renders (afterRender can fire multiple times in Knockout)
                if (self.googlePayPayment !== null) {
                    console.log('[GooglePay] Button already initialized, skipping');
                    return;
                }

                try {
                    // Check if BuckarooSdk is available
                    if (typeof window.BuckarooSdk === 'undefined' || !window.BuckarooSdk.GooglePay) {
                        console.error('[GooglePay] BuckarooSdk.GooglePay is not available');
                        throw new Error('BuckarooSdk.GooglePay is not available');
                    }

                    // Initialize Google Pay using Buckaroo SDK (only once)
                    self.googlePayPayment = new window.BuckarooSdk.GooglePay.GooglePayPayment(googlePayOptions);
                    self.googlePayPayment.initiate();

                    console.log('[GooglePay] Button initialized successfully');
                    canShowMethod(true);
                } catch (error) {
                    console.error('[GooglePay] Error initializing:', error);
                    canShowMethod(false);
                }
            },

            /**
             * Get product price from the page
             */
            /**
             * Initialize product view watchers (same as Apple Pay approach)
             * Sets up real-time tracking of product selection changes
             */
            initProductViewWatchers: function() {
                var self = this;

                // Get product ID
                this.productSelected.id = $('.price-box').attr('data-product-id');

                // Get initial quantity - try multiple selectors
                var qtyInput = $('#qty, #product_addtocart_form input[name="qty"], input[name="qty"]').first();
                if (qtyInput.length) {
                    this.productSelected.qty = parseFloat(qtyInput.val()) || 1;

                    // Watch for quantity changes (use 'input' event for real-time updates)
                    qtyInput.on('change input', function() {
                        var newQty = parseFloat($(this).val()) || 1;
                        self.productSelected.qty = newQty;
                        self.updateProductPrice();
                    });
                } else {
                    this.productSelected.qty = 1;
                }

                // Get initial unit price
                this.updateProductPrice();

                // Watch for option changes (color, size, etc.)
                $('.product-options-wrapper div').on('click', function() {
                    setTimeout(function() {
                        var selected_options = {};
                        $('div.swatch-attribute').each(function(k, v) {
                            var attribute_id = $(v).attr('attribute-id') || $(v).attr('data-attribute-id');
                            var option_selected = $(v).attr('option-selected') || $(v).attr('data-option-selected');
                            if (attribute_id && option_selected) {
                                selected_options[attribute_id] = option_selected;
                            }
                        });
                        self.productSelected.selected_options = selected_options;

                        // Update price when options change (configurable products might have different prices)
                        self.updateProductPrice();
                    }, 100); // Small delay to let Magento update the DOM
                });
            },

            /**
             * Update the unit price from the page
             */
            updateProductPrice: function() {
                try {
                    var unitPrice = null;

                    // Method 1: Get price from priceBox widget cache (most reliable for configurable products)
                    var priceBox = $('.product-info-main .price-box[data-product-id]').first();
                    if (priceBox.length) {
                        var priceBoxData = priceBox.data('priceBox');
                        if (priceBoxData && priceBoxData.cache && priceBoxData.cache.displayPrices) {
                            var finalPrice = priceBoxData.cache.displayPrices.finalPrice;
                            if (finalPrice && finalPrice.amount) {
                                unitPrice = parseFloat(finalPrice.amount);
                            }
                        }
                    }

                    // Method 2: Parse visible price text as fallback
                    if (!unitPrice || unitPrice <= 0) {
                        var priceElement = $('.product-info-main .price-box .price').first();
                        if (priceElement.length) {
                            var priceText = priceElement.text().trim();
                            // Remove currency symbols and parse (e.g., "€34.00" or "$34.00")
                            var priceMatch = priceText.match(/[\d.,]+/);
                            if (priceMatch) {
                                unitPrice = parseFloat(priceMatch[0].replace(',', '.'));
                            }
                        }
                    }

                    if (unitPrice && unitPrice > 0) {
                        this.productSelected.unitPrice = unitPrice;
                    }
                } catch (e) {
                    console.error('[GooglePay] Error updating product price:', e);
                }
            },

            /**
             * Validate product options before payment
             * Uses cached productSelected data (Apple Pay approach)
             */
            validateProductOptions: function() {
                // Only validate on product pages
                if (this.payMode !== 'product') {
                    return true;
                }

                var productForm = document.querySelector("#product_addtocart_form");
                if (!productForm) {
                    return true;
                }

                var isValid = true;
                var errorMessage = "Please select all required product options.";
                var missingOptions = [];

                // Check if we have selected options from our watcher
                var selectedOptions = this.productSelected.selected_options || {};

                // Check all configurable swatch options
                var swatchElements = productForm.querySelectorAll("div.swatch-attribute");
                swatchElements.forEach(function(element) {
                    var attributeId = element.getAttribute("data-attribute-id");
                    var attributeCode = element.getAttribute("data-attribute-code");

                    var label = element.querySelector(".swatch-attribute-label");
                    var labelText = label ? label.textContent.trim() : (attributeCode || "Option");

                    // Check if this attribute is selected in our cached data
                    if (!selectedOptions[attributeId] || selectedOptions[attributeId] === "") {
                        isValid = false;
                        missingOptions.push(labelText);
                    }
                });

                // Check dropdown configurable options
                var dropdownElements = productForm.querySelectorAll("select[name*=\"super_attribute\"]");
                dropdownElements.forEach(function(select, index) {
                    var selectValue = select.value;
                    var fieldElement = select.closest(".field");
                    var label = fieldElement ? fieldElement.querySelector("label .required, label span") : null;
                    var labelText = label ? label.textContent.trim() : ("Option " + (index + 1));

                    if (!selectValue || selectValue === "") {
                        isValid = false;
                        missingOptions.push(labelText);
                    }
                });

                if (missingOptions.length > 0) {
                    errorMessage = "Please select: " + missingOptions.join(", ");
                }

                if (!isValid) {
                    // Show error message using Magento's messaging system
                    customerData.set('messages', {
                        messages: [{
                            type: 'error',
                            text: errorMessage
                        }]
                    });
                    return false;
                }

                return true;
            },

            /**
             * Get product price from cached data (Apple Pay approach)
             * Returns total price (unit price * quantity)
             */
            getProductPriceFromPage: function () {
                try {
                    var unitPrice = this.productSelected.unitPrice;
                    var quantity = this.productSelected.qty || 1;

                    // If we don't have cached price, try to get it now
                    if (!unitPrice || unitPrice <= 0) {
                        this.updateProductPrice();
                        unitPrice = this.productSelected.unitPrice;
                    }

                    if (unitPrice && unitPrice > 0) {
                        return unitPrice * quantity;
                    }
                } catch (e) {
                    console.error('[GooglePay] Error getting product price:', e);
                }

                return null;
            },

            /**
             * Generate Google Pay options for the SDK
             */
            generateGooglePayOptions: function () {
                var self = this;
                var config = window.checkoutConfig.payment.buckaroo.buckaroo_magento2_googlepay;

                var grandTotal, currencyCode;

                // Handle product page
                if (this.payMode === 'product') {
                    grandTotal = this.getProductPriceFromPage();
                    if (grandTotal === null || grandTotal <= 0) {
                        console.error('[GooglePay] Cannot initialize - product price not available');
                        alert('Unable to initialize Google Pay: Product price not available. Please refresh the page and try again.');
                        return null;
                    }
                    currencyCode = config.currency || 'EUR';
                } else if (this.payMode === 'cart') {
                    // For cart pages, get cart total
                    var cartTotals = window.checkoutConfig && window.checkoutConfig.totalsData;
                    if (cartTotals && cartTotals.grand_total) {
                        grandTotal = parseFloat(cartTotals.grand_total);
                    } else {
                        grandTotal = 1.00;
                    }
                    currencyCode = config.currency || 'EUR';
                } else {
                    // Checkout mode - use remaining amount when giftcard/voucher partially paid
                    var remainingSegment = totals.getSegment('remaining_amount');
                    if (remainingSegment && parseFloat(remainingSegment.value) > 0) {
                        grandTotal = parseFloat(remainingSegment.value);
                    } else {
                        var quoteData = window.checkoutConfig.quoteData || {};
                        grandTotal = parseFloat(quoteData.grand_total) || 1.00;
                    }
                    var checkoutQuoteData = window.checkoutConfig.quoteData || {};
                    currencyCode = checkoutQuoteData.quote_currency_code || config.currency || 'EUR';
                }

                // Determine button container based on mode
                var buttonContainerId = 'google-pay-button-container';
                if (this.payMode === 'product') {
                    buttonContainerId = 'google-pay-wrapper';
                } else if (this.payMode === 'cart') {
                    buttonContainerId = 'google-pay-wrapper';
                }

                // Fix: isTestMode might be an array due to array_merge_recursive
                var isTestMode = Array.isArray(config.isTestMode) ? config.isTestMode[0] : config.isTestMode;

                var options = {
                    // Environment (TEST or PRODUCTION)
                    environment: isTestMode ? 'TEST' : 'PRODUCTION',

                    // Merchant info
                    merchantId: config.merchantId,
                    merchantName: config.merchantName || window.checkoutConfig.storeName || 'Store',
                    gatewayMerchantId: config.gatewayMerchantId,

                    // Transaction info
                    currencyCode: currencyCode,
                    countryCode: config.countryCode || 'NL',
                    totalPrice: grandTotal.toFixed(2),
                    totalPriceStatus: this.payMode === 'product' || this.payMode === 'cart' ? 'ESTIMATED' : 'FINAL',

                    // Shipping info - Required for product/cart pages
                    emailRequired: this.payMode === 'product' || this.payMode === 'cart',
                    shippingAddressRequired: this.payMode === 'product' || this.payMode === 'cart',
                    shippingOptionRequired: this.payMode === 'product' || this.payMode === 'cart',
                    shippingAddressParameters: {
                        phoneNumberRequired: true
                        // Note: allowedCountryCodes not specified = all countries allowed
                    },

                    // Button styling
                    buttonColor: config.buttonStyle || 'black', // 'black', 'white', 'default'
                    buttonType: 'buy', // 'book', 'buy', 'checkout', 'donate', 'order', 'pay', 'plain', 'subscribe'
                    buttonContainerId: buttonContainerId,
                    buttonLocale: config.locale || 'en',

                    // Allowed card networks and auth methods
                    allowedCardNetworks: config.allowedCardNetworks || ['MASTERCARD', 'VISA'],
                    allowedCardAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],

                    // Callbacks
                    onValidationCallback: function() {
                        return self.validateProductOptions();
                    },

                    processPayment: function (paymentData) {
                        return self.processPayment(paymentData);
                    }
                };

                // Only add onPaymentDataChanged for product/cart pages (not checkout)
                if (this.payMode === 'product' || this.payMode === 'cart') {
                    options.onPaymentDataChanged = function (intermediatePaymentData) {
                        var callbackTrigger = intermediatePaymentData.callbackTrigger;
                        console.log('[GooglePay] onPaymentDataChanged - trigger:', callbackTrigger);

                        // Recalculate grandTotal with current qty for product pages
                        var currentGrandTotal = grandTotal;
                        if (self.payMode === 'product') {
                            // Update price from page right before showing payment sheet
                            self.updateProductPrice();
                            
                            if (self.productSelected.unitPrice && self.productSelected.qty) {
                                currentGrandTotal = self.productSelected.unitPrice * self.productSelected.qty;
                            }
                        }

                        // Handle SHIPPING_OPTION change (user selected different shipping method)
                        if (callbackTrigger === 'SHIPPING_OPTION' && intermediatePaymentData.shippingOptionData) {
                            var selectedId = intermediatePaymentData.shippingOptionData.id;
                            var shippingCost = self.shippingPriceById[selectedId] || 0;
                            var newTotal = (parseFloat(currentGrandTotal) + shippingCost).toFixed(2);

                            return Promise.resolve({
                                newTransactionInfo: {
                                    countryCode: config.countryCode || 'NL',
                                    currencyCode: currencyCode,
                                    totalPriceStatus: 'FINAL',
                                    totalPrice: newTotal,
                                    displayItems: [
                                        {
                                            label: 'Subtotal',
                                            type: 'SUBTOTAL',
                                            price: currentGrandTotal.toFixed(2),
                                            status: 'FINAL'
                                        },
                                        {
                                            label: 'Shipping',
                                            type: 'SHIPPING_OPTION',
                                            price: shippingCost.toFixed(2),
                                            status: 'FINAL'
                                        }
                                    ]
                                }
                            });
                        }

                        // Handle INITIALIZE or SHIPPING_ADDRESS (fetch shipping methods)
                        if (intermediatePaymentData.shippingAddress) {
                            return self.getShippingMethods(intermediatePaymentData.shippingAddress)
                                .then(function(shippingMethods) {
                                    // Get selected shipping (or default to first method)
                                    var selectedShipping = null;
                                    if (intermediatePaymentData.shippingOptionData && intermediatePaymentData.shippingOptionData.id) {
                                        var optionId = intermediatePaymentData.shippingOptionData.id;
                                        // Only use it if it's a valid shipping method ID (not "shipping_option_unselected")
                                        if (shippingMethods.some(function(m) { return m.id === optionId; })) {
                                            selectedShipping = optionId;
                                        }
                                    }
                                    // Default to first method if no valid selection
                                    if (!selectedShipping && shippingMethods.length > 0) {
                                        selectedShipping = shippingMethods[0].id;
                                    }

                                    // Get shipping cost from stored price map (not from shippingOptions)
                                    var shippingCost = 0;
                                    if (selectedShipping && self.shippingPriceById[selectedShipping] != null) {
                                        shippingCost = self.shippingPriceById[selectedShipping];
                                    }

                                    var newTotal = (parseFloat(currentGrandTotal) + shippingCost).toFixed(2);

                                    var updateObject = {
                                        newTransactionInfo: {
                                            countryCode: config.countryCode || 'NL',  // Required by Google Pay
                                            currencyCode: currencyCode,
                                            totalPriceStatus: 'FINAL',
                                            totalPrice: newTotal,
                                            displayItems: [
                                                {
                                                    label: 'Subtotal',
                                                    type: 'SUBTOTAL',
                                                    price: currentGrandTotal.toFixed(2),
                                                    status: 'FINAL'
                                                },
                                                {
                                                    label: 'Shipping',
                                                    type: 'SHIPPING_OPTION',  // Correct type per Google Pay spec
                                                    price: shippingCost.toFixed(2),
                                                    status: 'FINAL'
                                                }
                                            ]
                                        },
                                        newShippingOptionParameters: {
                                            defaultSelectedOptionId: selectedShipping,  // Already validated above
                                            shippingOptions: shippingMethods  // Now valid SelectionOption[] without price
                                        }
                                    };

                                    console.log('[GooglePay] Returning updateObject:', JSON.stringify(updateObject));
                                    return updateObject;
                                })
                                .catch(function(error) {
                                    console.error('[GooglePay] Error getting shipping methods:', error);

                                    // Return error to Google Pay - this will show the error to the user
                                    // and prevent them from completing the payment
                                    return {
                                        error: {
                                            reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                            message: 'Sorry, we do not ship to this address. Please select a different address.',
                                            intent: 'SHIPPING_ADDRESS'
                                        }
                                    };
                                });
                        }

                        // If no shipping address, return current total with updated qty
                        return Promise.resolve({
                            newTransactionInfo: {
                                countryCode: config.countryCode || 'NL',  // Required by Google Pay
                                currencyCode: currencyCode,
                                totalPriceStatus: 'ESTIMATED',
                                totalPrice: currentGrandTotal.toFixed(2)
                            }
                        });
                    };
                }

                // Add error handler
                options.onGooglePayLoadError = function (error) {
                    console.error('[Buckaroo Google Pay] Load error:', error);
                    canShowMethod(false);
                };

                return options;
            },

            /**
             * Process Google Pay payment
             */
            getShippingMethods: function (shippingAddress) {
                var self = this;
                return new Promise(function(resolve, reject) {
                    // For product page, we need to include product info to create a temporary quote
                    var requestData = {
                        address: JSON.stringify(shippingAddress)
                    };

                    // Add product info for product page
                    if (self.payMode === 'product' && self.productSelected) {
                        requestData.product = JSON.stringify({
                            id: self.productSelected.id,
                            qty: self.productSelected.qty,
                            selected_options: self.productSelected.selected_options || {}
                        });
                    }

                    $.ajax({
                        url: urlBuilder.build('buckaroo/googlepay/getShippingMethods'),
                        type: 'POST',
                        dataType: 'json',
                        data: requestData,
                        success: function(response) {
                            if (response && response.success && response.data && response.data.shipping_methods) {
                                // Transform to Google Pay format (without price - per Google Pay spec)
                                var googlePayShippingOptions = response.data.shipping_methods.map(function(method) {
                                    var price = method.amount || method.price_incl_tax || method.price || 0;
                                    var id = method.carrier_code + '_' + method.method_code;

                                    // Store price separately (NOT in the SelectionOption object)
                                    self.shippingPriceById[id] = parseFloat(price);

                                    // Return valid Google Pay SelectionOption (id, label, description only)
                                    return {
                                        id: id,
                                        label: method.carrier_title + ' - ' + method.method_title,
                                        description: method.method_title
                                    };
                                });
                                resolve(googlePayShippingOptions);
                            } else if (response && response.error) {
                                reject(new Error(response.error));
                            } else {
                                resolve([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('[GooglePay] Error fetching shipping methods:', error);
                            reject(error);
                        }
                    });
                });
            },

            processPayment: function (paymentData) {
                var self = this;

                return new Promise(function (resolve, reject) {
                    try {
                        // Store the payment data in observable
                        // The order-handler subscribes to this and will handle order placement
                        self.transactionResult(paymentData);

                        resolve({
                            success: true
                        });
                    } catch (error) {
                        console.error('[GooglePay] Process payment error:', error);
                        resolve({
                            success: false,
                            errorMessage: $t('Payment processing failed. Please try again.'),
                            errorReason: 'PAYMENT_DATA_INVALID'
                        });
                    }
                });
            },

            /**
             * Update Google Pay options (e.g., when totals change)
             */
            updateOptions: function () {
                if (this.googlePayPayment === null) {
                    return;
                }

                // Regenerate options with updated totals
                var newOptions = this.generateGooglePayOptions();

                // Update the payment instance options
                if (this.googlePayPayment.options) {
                    this.googlePayPayment.options.totalPrice = newOptions.totalPrice;
                    this.googlePayPayment.options.currencyCode = newOptions.currencyCode;
                }
            },

            /**
             * Set quote
             */
            setQuote: function (newQuote) {
                this.quote = newQuote;
            },

            /**
             * Set if on checkout page
             */
            setIsOnCheckout: function (isOnCheckout) {
                this.isOnCheckout = isOnCheckout;
            },

            /**
             * Check if we're in One Step Checkout
             */
            isOsc: function () {
                return window.checkoutConfig.isOscEnabled || false;
            }
        };
    }
);
