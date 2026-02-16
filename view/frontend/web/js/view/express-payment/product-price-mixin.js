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

/**
 * Shared Product Price Detection Mixin for Express Payment Methods
 * 
 * This mixin provides consistent product price detection across all express payment methods
 * (Google Pay, Apple Pay, PayPal Express, etc.)
 * 
 * Usage:
 * 1. Include this mixin in your component dependencies
 * 2. Call this.getProductPriceFromPage() to get the current product price
 * 3. Call this.initProductPriceWatchers() to watch for price changes
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return {
        /**
         * Product selection data
         */
        productSelected: {
            id: null,
            qty: 1,
            unitPrice: null,
            selected_options: {}
        },

        /**
         * Initialize product view watchers
         * Sets up real-time tracking of product selection changes (quantity, options)
         * 
         * @returns {void}
         */
        initProductPriceWatchers: function() {
            var self = this;

            // Get product ID from price box
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

            // Watch for option changes (color, size, etc.) - configurable products
            $('.product-options-wrapper div').on('click', function() {
                setTimeout(function() {
                    var selected_options = {};
                    
                    // Collect selected swatch options
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
         * Uses two-level fallback for reliability:
         * 1. Magento's priceBox widget cache (most reliable)
         * 2. Parse visible price text (fallback)
         * 
         * @returns {void}
         */
        updateProductPrice: function() {
            try {
                var unitPrice = this.getPriceFromWidget() || this.getPriceFromText();

                if (unitPrice && unitPrice > 0) {
                    this.productSelected.unitPrice = unitPrice;
                }
            } catch (e) {
                console.error('[ExpressPayment] Error updating product price:', e);
            }
        },

        /**
         * Method 1: Get price from Magento's priceBox widget cache
         * This is the most reliable method as it gets the actual numeric value
         * without any string parsing
         * 
         * @returns {number|null} Price as float, or null if not found
         */
        getPriceFromWidget: function() {
            try {
                var priceBox = $('.product-info-main .price-box[data-product-id]').first();
                
                if (!priceBox.length) {
                    return null;
                }

                var priceBoxData = priceBox.data('priceBox');
                
                if (!priceBoxData || !priceBoxData.cache || !priceBoxData.cache.displayPrices) {
                    return null;
                }

                var finalPrice = priceBoxData.cache.displayPrices.finalPrice;
                
                if (finalPrice && finalPrice.amount) {
                    return parseFloat(finalPrice.amount);
                }

                return null;
            } catch (e) {
                console.warn('[ExpressPayment] Error getting price from widget:', e);
                return null;
            }
        },

        /**
         * Method 2: Parse price from visible text (fallback)
         * Used when widget cache is not available
         * Supports various currency formats (€12.34, $12.34, 12,34 €, etc.)
         * 
         * @returns {number|null} Price as float, or null if not found
         */
        getPriceFromText: function() {
            try {
                var priceElement = $('.product-info-main .price-box .price').first();
                
                if (!priceElement.length) {
                    return null;
                }

                var priceText = priceElement.text().trim();
                
                // Extract numbers, dots, and commas
                var priceMatch = priceText.match(/[\d.,]+/);
                
                if (!priceMatch) {
                    return null;
                }

                // Handle different decimal separators
                // Examples: "12,34" (European) or "12.34" (US)
                var price = priceMatch[0];
                
                // If there's a comma followed by 2 digits at the end, it's likely a decimal separator
                if (/,\d{2}$/.test(price)) {
                    // European format: 1.234,56 → 1234.56
                    price = price.replace(/\./g, '').replace(',', '.');
                } else {
                    // US format: 1,234.56 → 1234.56 (remove comma thousand separator)
                    price = price.replace(/,/g, '');
                }

                return parseFloat(price);
            } catch (e) {
                console.warn('[ExpressPayment] Error parsing price from text:', e);
                return null;
            }
        },

        /**
         * Get current product price from page (combines both methods)
         * Public method that should be called by payment implementations
         * 
         * @returns {number|null} Current product price, or null if cannot be determined
         */
        getProductPriceFromPage: function() {
            try {
                var unitPrice = this.getPriceFromWidget() || this.getPriceFromText();

                // If we don't have cached price, try to get it now
                if (!this.productSelected.unitPrice || this.productSelected.unitPrice <= 0) {
                    this.updateProductPrice();
                    unitPrice = this.productSelected.unitPrice;
                }

                return unitPrice && unitPrice > 0 ? unitPrice : null;
            } catch (e) {
                console.error('[ExpressPayment] Error getting product price:', e);
                return null;
            }
        },

        /**
         * Get total price (unit price * quantity)
         * 
         * @returns {number|null} Total price, or null if cannot be determined
         */
        getProductTotalPrice: function() {
            var unitPrice = this.getProductPriceFromPage();
            var quantity = this.productSelected.qty || 1;

            if (unitPrice && unitPrice > 0) {
                return unitPrice * quantity;
            }

            return null;
        },

        /**
         * Validate that we have a valid product price
         * Shows user-friendly error if price cannot be determined
         * 
         * @param {string} paymentMethodName - Name of payment method for error message
         * @param {Function} errorCallback - Optional callback to show error (receives error message)
         * @returns {boolean} True if valid price exists, false otherwise
         */
        validateProductPrice: function(paymentMethodName, errorCallback) {
            var price = this.getProductPriceFromPage();

            if (!price || price <= 0) {
                var errorMessage = 'Unable to initialize ' + paymentMethodName + ': Product price not available. Please refresh the page and try again.';
                
                console.error('[ExpressPayment] ' + errorMessage);
                
                // Use custom error callback if provided, otherwise use alert
                if (errorCallback && typeof errorCallback === 'function') {
                    errorCallback(errorMessage);
                } else if (typeof alert !== 'undefined') {
                    alert(errorMessage);
                }
                
                return false;
            }

            return true;
        }
    };
});
