define([
    "jquery",
    "ko",
    "Magento_Customer/js/customer-data",
    'mage/translate',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage'
], function ($, ko, customerData, $t, errorProcessor, fullScreenLoader, quote, urlBuilder, storage) {
    'use strict';

    return {
        setConfig(config, page) {
            this.page = page;
            this.options = $.extend(true, {
                containerSelector: ".buckaroo-ideal-fast-checkout",
                buckarooWebsiteKey: "",
                currency: "EUR",
                amount: 0.1,
                createPaymentHandler: this.createPaymentHandler.bind(this),
                onShippingChangeHandler: this.onShippingChangeHandler.bind(this),
                onSuccessCallback: this.onSuccessCallback.bind(this),
                onErrorCallback: this.onErrorCallback.bind(this),
                onCancelCallback: this.onCancelCallback.bind(this),
                onInitCallback: this.onInitCallback.bind(this),
                onClickCallback: this.onClickCallback.bind(this),
            }, config);

            if (this.page === 'cart') {
                require(["Magento_Checkout/js/model/quote"], function (quote) {
                    quote.totals.subscribe((totalData) => {
                        this.options.amount = (totalData.grand_total + totalData.tax_amount).toFixed(2);
                        this.options.currency = totalData.quote_currency_code;
                    });
                });
            }
        },

        init() {
            this.showPayButton();
        },

        showPayButton() {
            $(this.options.containerSelector).html('<button id="buckaroo-pay-button">Pay with iDEAL</button>');
            $('#buckaroo-pay-button').click(() => {
                this.createTransaction();
            });
        },

        createTransaction() {
            const cart_id = this.cart_id;
            const serviceUrl = customer.isLoggedIn()
                ? urlBuilder.createUrl('/buckaroo/payment-information', {})
                : urlBuilder.createUrl('/guest-buckaroo/:quoteId/payment-information', { quoteId: quote.getQuoteId() });

            const payload = {
                cartId: quote.getQuoteId(),
                email: customer.isLoggedIn() ? undefined : quote.guestEmail,
                paymentMethod: {
                    method: 'buckaroo_magento2_ideal'
                },
                billingAddress: quote.billingAddress()
            };

            fullScreenLoader.startLoader();

            return storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done((response) => {
                this.result = response;
                this.onSuccessCallback();
            }).fail((response) => {
                errorProcessor.process(response, customerData);
                fullScreenLoader.stopLoader();
                this.onErrorCallback($t("Cannot create payment"));
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
        },

        onInitCallback() {},

        onCancelCallback() {
            this.displayErrorMessage($t("You have canceled the payment request."));
        },

        onClickCallback() {
            this.result = null;
        },

        displayErrorMessage(message) {
            if (typeof message === "object" && message.responseJSON && message.responseJSON.message) {
                message = $t(message.responseJSON.message);
            } else {
                message = $t("Cannot create payment");
            }

            customerData.set('messages', {
                messages: [{
                    type: 'error',
                    text: message
                }]
            });
        }
    };
});
