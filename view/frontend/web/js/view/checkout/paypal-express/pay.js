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
  'mage/translate',
  'BuckarooSdk'
], function ($, ko, urlBuilder, customerData, $t) {
  "use strict";

  return {
    setConfig(config, page) {
      this.page = page;
      if (this.page === 'cart') {
        const self = this;
        require(["Magento_Checkout/js/model/quote"], function (quote) {
          quote.totals.subscribe((totalData) => {
            self.options.amount = (totalData.grand_total + totalData.tax_amount).toFixed(2);
            self.options.currency = totalData.quote_currency_code;
          })
        })
      }

      this.options = Object.assign(
        {
          containerSelector: ".buckaroo-paypal-express",
          buckarooWebsiteKey: "",
          paypalMerchantId: "",
          currency: "EUR",
          amount: 0.1,
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
    },
    result: null,
    paypalInitialized: false,
    cart_id: null,

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
              this.options.amount = response.value;
              this.cart_id = response.cart_id;
              actions.order.patch([
                {
                  op: "replace",
                  path: "/purchase_units/@reference_id=='default'/amount",
                  value: response,
                },
              ]).then((resp) => resolve(resp), (err) => reject(err));
            } else {
              reject(response.message);
            }

            this.options = Object.assign(
                {
                    containerSelector: ".buckaroo-paypal-express",
                    buckarooWebsiteKey: "",
                    paypalMerchantId: "",
                    currency: "EUR",
                    amount: 0.1,
                    createPaymentHandler: this.createPaymentHandler.bind(this),
                    onShippingChangeHandler: this.onShippingChangeHandler.bind(this),
                    onSuccessCallback: this.onSuccessCallback.bind(this),
                    onErrorCallback: this.onErrorCallback.bind(this),
                    onCancelCallback: this.onCancelCallback.bind(this),
                    onInitCallback: this.onInitCallback.bind(this),
                    onClickCallback: this.onClickCallback.bind(this),
                },
                config
            );
        },
        result: null,

        cart_id: null,
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

    onErrorCallback(reason) {
        // custom error behavior
        this.displayErrorMessage(reason);
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
            $.post(urlBuilder.build("rest/V1/buckaroo/paypal-express/order/create"),
                {
                    paypal_order_id: orderId,
                    cart_id
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
     * Set shipping on cart and return new total
     * @param {Object} data
     * @returns
     */
    setShipping(data) {
      return $.post(urlBuilder.build("rest/V1/buckaroo/paypal-express/quote/create"), {
        shipping_address: data.shipping_address,
        order_data: this.getOrderData(),
        page: this.page,
      });
    },
    /**
     * Get form data for product page to create cart
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
        this.displayErrorMessage(errorMessage);

        // Also display inline error near PayPal button
        this.displayInlineError(errorMessage);

        return {
          isValid: false,
          message: errorMessage
        };
      }

      this.clearInlineError();
      return true;
    },

    /**
     * Display inline error message near PayPal button
     * @param {string} message
     */
    displayInlineError(message) {
      // Remove any existing error
      var existingError = document.querySelector("#paypal-validation-error");
      if (existingError) {
        existingError.remove();
      }

      // Create error message
      var errorDiv = document.createElement("div");
      errorDiv.id = "paypal-validation-error";
      errorDiv.style.cssText = "background: #fff5f5; border: 1px solid #e02b27; color: #e02b27; padding: 12px; margin: 10px 0; border-radius: 4px; font-size: 14px; font-weight: bold; position: relative; z-index: 9999;";
      errorDiv.textContent = message;

      // Insert error near PayPal button
      var paypalContainer = document.querySelector(".buckaroo-paypal-express") ||
                          document.querySelector("[id*=\"paypal\"]") ||
                          document.querySelector("#product_addtocart_form");
      if (paypalContainer) {
        paypalContainer.insertBefore(errorDiv, paypalContainer.firstChild);
      }
    },

    /**
     * Clear inline error message
     */
    clearInlineError() {
      var existingError = document.querySelector("#paypal-validation-error");
      if (existingError) {
        existingError.remove();
      }
    }
  };
});
