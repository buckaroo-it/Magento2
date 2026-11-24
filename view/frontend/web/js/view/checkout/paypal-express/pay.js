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
define([
  "jquery",
  "ko",
  "require",
  "mage/url",
  "Magento_Customer/js/customer-data",
  'mage/translate',
  'Buckaroo_Magento2/js/view/express-payment/product-price-mixin',
  'buckaroo/rest-url-builder',
  'Buckaroo_Magento2/js/view/checkout/product-selection',
  'BuckarooSdk'
], function ($, ko, require, urlBuilder, customerData, $t, productPriceMixin, restUrlBuilder, productSelection) {
  "use strict";

  function getBuckarooSdkInstance() {
    if (typeof window !== "undefined" && window.BuckarooSdk) {
      return window.BuckarooSdk;
    }

    if (typeof globalThis !== "undefined" && globalThis.BuckarooSdk) {
      return globalThis.BuckarooSdk;
    }

    return null;
  }

  return $.extend({}, productPriceMixin, {
    setConfig(config, page) {
      this.page = page;

      // Set test mode for Buckaroo SDK
      const sdk = getBuckarooSdkInstance();
      if (config.isTestMode !== undefined && sdk && sdk.Base && sdk.Base.setTestMode) {
        sdk.Base.setTestMode(config.isTestMode);
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
        require(['Magento_Checkout/js/model/quote'], function (quote) {
          const syncFromTotals = function (totalData) {
            if (!totalData) return;
            const grandTotal = parseFloat(totalData.grand_total);
            if (Number.isNaN(grandTotal)) return;
            self.options.amount = grandTotal.toFixed(2);
            if (totalData.quote_currency_code) {
              self.options.currency = totalData.quote_currency_code;
            }
          };
          try {
            syncFromTotals(quote.totals());
          } catch (e) {
            // Totals not ready yet; the subscription below will sync later.
          }
          quote.totals.subscribe(syncFromTotals);
        }, function () {
          // quote model unavailable on this page (no window.checkoutConfig);
          // keep the config-provided amount/currency so the button still renders.
        });
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
        !productSelection.validate()
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
          const sdk = getBuckarooSdkInstance();
          if (!sdk || !sdk.PayPal || !sdk.PayPal.initiate) {
            throw new Error("BuckarooSdk.PayPal is not available");
          }
          sdk.PayPal.initiate(this.options);
      },

    /**
     * Create order and do payment
     * @param {string} orderId
     * @returns Promise
     */
    createTransaction(orderId) {
        const cart_id = this.cart_id;

        return new Promise((resolve, reject) => {
            $.post(restUrlBuilder.createUrl('/buckaroo/paypal-express/order/create'), {
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
      return $.post(restUrlBuilder.createUrl('/buckaroo/paypal-express/quote/create'), {
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
      if (this.page === 'product') {
        return productSelection.serialize();
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
     * Validate the product form before PayPal opens (used as SDK validation callback): the same check as
     * Magento's Add to Cart button, with its messages at the fields
     * @returns {Object|boolean} Validation result object or boolean
     */
    validateProductOptions() {
      if (this.page !== 'product' || productSelection.validate()) {
        return true;
      }

      return {
        isValid: false,
        message: $t("Please select all required product options.")
      };
    }
  });
});
