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
], function ($, ko, urlBuilder, customerData, $t) {
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
                    });
                });
            }

            this.options = Object.assign(
                {
                    containerSelector: ".buckaroo-ideal-fast-checkout",
                    buckarooWebsiteKey: "",
                    currency: "EUR",
                    amount: 0.1,
                    createPaymentHandler: this.createPaymentHandler.bind(this),
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
        createPaymentHandler(data) {
            return this.createTransaction();
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
        /**
         * Init class
         */
        init() {
            console.log("Initializing iDEAL Fast Checkout");
            this.initializePaymentButton();
        },

        /**
         * Initialize the iDEAL Fast Checkout button
         */
        initializePaymentButton() {
            const self = this;
            $(this.options.containerSelector).on('click', function () {
                self.onClickCallback();
                self.createPaymentHandler().then(
                    self.onSuccessCallback,
                    self.onErrorCallback
                );
            });
        },

        /**
         * Create order and do payment
         * @returns Promise
         */
        createTransaction() {
            const cart_id = this.cart_id;
            return new Promise((resolve, reject) => {
                $.post(urlBuilder.build("rest/V1/buckaroo/ideal-fast-checkout/order/create"), {
                    cart_id,
                    amount: this.options.amount,
                    currency: this.options.currency
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
    };
});
