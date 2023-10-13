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
  "BuckarooSDK",
  'mage/translate',
], function ($, ko, urlBuilder, customerData, sdk, __) {
  // 'use strict';
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
          },
          () => {
            reject(__("Cannot create payment"));
          }
        );

      })

    },
    createPaymentHandler(data) {
      return this.createTransaction(data.orderID);
    },
    onSuccessCallback() {
      if (this.result.message) {
        this.displayErrorMessage(message);
      } else {
        if (this.result.cart_id && this.result.cart_id.length) {
          window.location.replace(urlBuilder.build('checkout/onepage/success/'));
        } else {
          this.displayErrorMessage(__("Cannot create payment"));
        }
      }
    },

    onErrorCallback(reason) {
      // custom error behavior
      this.displayErrorMessage(reason);
    },
    onInitCallback() {
    },
    onCancelCallback() {
      this.displayErrorMessage(__("You have canceled the payment request"));
    },
    onClickCallback() {
      //reset any previous payment response;
      this.result = null;
    },
    /**
     * Init class
     */
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
        return form.serializeArray();
      }
    },
    /**
     * Display any validation errors we receive
     * @param {string} message
     */
    displayErrorMessage(message) {
      if (typeof message === "object") {
        if (message.responseJSON && message.responseJSON.message) {
          message = __(message.responseJSON.message);
        } else {
          message = __("Cannot create payment");
        }

      }
      customerData.set('messages', {
        messages: [{
          type: 'error',
          text: message
        }]
      });

    },
  };
});
