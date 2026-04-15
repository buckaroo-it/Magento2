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
define([
  "jquery",
  "ko",
  "mage/url",
  "Magento_Customer/js/customer-data",
  "Magento_Checkout/js/model/quote",
  'mage/translate',
  'Buckaroo_Magento2/js/view/express-payment/product-price-mixin',
  'BuckarooSdk'
], function ($, ko, urlBuilder, customerData, quote, $t, productPriceMixin, BuckarooSdk) {
  "use strict";

  return $.extend({}, productPriceMixin, {
    setConfig(config, page) {
      this.page = page;

      // Set test mode for Buckaroo SDK
      if (config.isTestMode !== undefined && typeof BuckarooSdk !== 'undefined' && BuckarooSdk.Base && BuckarooSdk.Base.setTestMode) {
        BuckarooSdk.Base.setTestMode(config.isTestMode);
      }

      // Initialize product price watchers for product page
      if (this.page === 'product') {
        this.initProductPriceWatchers();
      }

      // Get product price for product page using mixin
      let productPrice = null;
      if (this.page === 'product') {
        productPrice = this.getProductTotalPrice();
        if (!productPrice || productPrice <= 0) {
          console.error('[PayPal Express] Cannot initialize - product price not available');
          this.displayErrorMessage('Unable to initialize PayPal Express: Product price not available. Please refresh the page and try again.');
          return;
        }
      }

      this.options = Object.assign(
        {
          containerSelector: ".buckaroo-paypal-express",
          buckarooWebsiteKey: "",
          paypalMerchantId: "",
          currency: "EUR",
          amount: productPrice,
          createPaymentHandler: this.createPaymentHandler.bind(this),
          onShippingChangeHandler: this.onShippingChangeHandler.bind(this),
          onSuccessCallback: this.onSuccessCallback.bind(this),
          onErrorCallback: this.onErrorCallback.bind(this),
          onCancelCallback: this.onCancelCallback.bind(this),
          onInitCallback: this.onInitCallback.bind(this),
          onClickCallback: this.onClickCallback.bind(this),
          onValidationCallback: this.validateProductOptions.bind(this),
        },
        config
      );

      // For cart page, sync amount from quote totals after options exist.
      if (this.page === 'cart') {
        const self = this;
        const syncFromTotals = function (totalData) {
          if (!totalData) return;
          const grandTotal = parseFloat(totalData.grand_total);
          if (Number.isNaN(grandTotal)) return;
          self.options.amount = grandTotal.toFixed(2);
          if (totalData.quote_currency_code) {
            self.options.currency = totalData.quote_currency_code;
          }
        };
        syncFromTotals(quote.totals());
        quote.totals.subscribe(syncFromTotals);
      }
    },
    result: null,
    paypalInitialized: false,
    cart_id: null,
    page: null,

    /**
     * Api events
     */
    onShippingChangeHandler(data, actions) {
      if (
        this.page === 'product' &&
        $("#product_addtocart_form").valid() === false
      ) {
        return actions.reject();
      }

      let shipping = this.setShipping(data);
      return new Promise((resolve, reject) => {
        shipping.then(
          (response) => {
            if (!response.message) {
              this.cart_id = response.cart_id;

              // Get amounts from response breakdown.
              const newTotal = parseFloat(response.value);
              if (Number.isNaN(newTotal)) {
                reject($t("Cannot update payment totals"));
                return;
              }
              const currency = this.options.currency;
              const bd = response.breakdown || {};
              const itemTotalRaw = bd.item_total ? parseFloat(bd.item_total.value) : newTotal;
              const shippingRaw = bd.shipping ? parseFloat(bd.shipping.value) : 0;
              const taxTotalRaw = bd.tax_total ? parseFloat(bd.tax_total.value) : 0;
              const itemTotal = Number.isNaN(itemTotalRaw) ? newTotal : itemTotalRaw;
              const shippingAmt = Number.isNaN(shippingRaw) ? 0 : shippingRaw;
              const taxTotal = Number.isNaN(taxTotalRaw) ? 0 : taxTotalRaw;

              // Update the PayPal order with correct breakdown
              return actions.order.patch([
                {
                  op: 'replace',
                  path: "/purchase_units/@reference_id=='default'/amount",
                  value: {
                    currency_code: currency,
                    value: newTotal.toFixed(2),
                    breakdown: {
                      item_total: {
                        currency_code: currency,
                        value: itemTotal.toFixed(2)
                      },
                      shipping: {
                        currency_code: currency,
                        value: shippingAmt.toFixed(2)
                      },
                      tax_total: {
                        currency_code: currency,
                        value: taxTotal.toFixed(2)
                      }
                    }
                  }
                }
              ]).then(() => {
                this.options.amount = newTotal.toFixed(2);
                resolve();
              }).catch((error) => {
                // Order patch failed (may not be supported), continue with the flow
                this.options.amount = newTotal.toFixed(2);
                resolve();
              });
            } else {
              reject(response.message);
            }
          },
          () => {
            reject($t("Cannot create payment"));
          }
        );
      });
    },
    createPaymentHandler(orderID) {
      return this.createTransaction(orderID).catch((error) => {
        console.error('[PayPal Express] Payment creation failed:', error);
        throw error;
      });
    },
    onSuccessCallback() {
      if (this.result.message) {
        this.displayErrorMessage(this.result.message);
      } else {
        if (this.result.cart_id && this.result.cart_id.length) {
          window.location.replace(urlBuilder.build('checkout/onepage/success/'));
        } else {
          this.displayErrorMessage($t("Cannot create payment"));
        }
      }
    },

    onErrorCallback(reason) {
        this.displayErrorMessage(reason);

        if (this.page !== 'product' && this.page !== 'cart') {
          setTimeout(() => {
            window.location.replace(urlBuilder.build('checkout/cart/'));
          }, 3000);
        }
    },
    onInitCallback() {
    },
    onCancelCallback() {
      this.displayErrorMessage($t("You have canceled the payment request."));
    },
    onClickCallback() {
        //reset any previous payment response;
        this.result = null;
    },

      init() {
          BuckarooSdk.PayPal.initiate(this.options);
      },

    /**
     * Create order and do payment
     * @param {string} orderId
     * @returns Promise
     */
    createTransaction(orderId) {
        const cart_id = this.cart_id;

        return new Promise((resolve, reject) => {
            $.post(urlBuilder.build("rest/V1/buckaroo/paypal-express/order/create"), {
                paypal_order_id: orderId,
                cart_id: cart_id
            }).then(
                (response) => {
                    this.result = response;
                    resolve(response);
                },
                (reason) => reject(reason)
            );
        });
    },

    /**
     * Create/update quote with shipping address and get cart_id
     * @param {Object} data
     * @returns Promise
     */
    setShipping(data) {
      return $.post(urlBuilder.build("rest/V1/buckaroo/paypal-express/quote/create"), {
        shipping_address: data.shipping_address,
        order_data: this.getOrderData(),
        page: this.page,
      });
    },

    /**
     * Get form data for the product page to create a cart
     * @returns
     */
    getOrderData() {
      let form = $("#product_addtocart_form");
      if (this.page === 'product') {
        return form.serialize();
      }
    },
    /**
     * Display any validation errors we receive
     * @param {string} message
     */
    displayErrorMessage(message) {
      let errorText = message;

      if (typeof message === "object" && message !== null) {
        if (message.responseJSON && message.responseJSON.message) {
          errorText = message.responseJSON.message;
        } else if (message.responseText) {
          try {
            const parsed = JSON.parse(message.responseText);
            errorText = parsed.message || "Cannot create payment";
          } catch (e) {
            errorText = "Cannot create payment";
          }
        } else {
          errorText = "Cannot create payment";
        }
      }
      if (typeof errorText !== "string") {
        errorText = "Cannot create payment";
      }
      errorText = $t(errorText);
      customerData.set('messages', {
        messages: [{
          type: 'error',
          text: errorText
        }]
      });

      customerData.invalidate(['messages']);
      customerData.reload(['messages'], true);
    },

    /**
     * Validate product options for Magento (used as SDK validation callback)
     * @returns {Object|boolean} Validation result object or boolean
     */
    validateProductOptions() {
      // Only validate on product pages
      if (this.page !== 'product') {
        return true;
      }

      var productForm = document.querySelector("#product_addtocart_form");
      if (!productForm) {
        return true;
      }

      var isValid = true;
      var errorMessage = "Please select all required product options.";
      var missingOptions = [];

      // Check configurable swatch options using Magento structure
      var swatchElements = productForm.querySelectorAll("div.swatch-attribute");

      swatchElements.forEach(function(element, index) {
        var attributeId = element.getAttribute("data-attribute-id");
        var attributeCode = element.getAttribute("data-attribute-code");
        var optionSelected = element.getAttribute("data-option-selected");

        // Check the corresponding hidden input
        var hiddenInput = productForm.querySelector("input[name=\"super_attribute[" + attributeId + "]\"]");
        var inputValue = hiddenInput ? hiddenInput.value : "";

        // Check for selected swatch (class="swatch-option ... selected")
        var selectedSwatch = element.querySelector(".swatch-option.selected");

        var label = element.querySelector(".swatch-attribute-label");
        var labelText = label ? label.textContent.trim() : (attributeCode || "Option");

        // An option is considered SELECTED if ANY of these are true:
        var hasSelection = (optionSelected && optionSelected !== "") ||
                         (inputValue && inputValue !== "") ||
                         !!selectedSwatch;

        if (!hasSelection) {
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
        var labelText = label ? label.textContent.trim() : ("Dropdown Option " + (index + 1));

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

        return {
          isValid: false,
          message: errorMessage
        };
      }

      return true;
    }
  });
});
