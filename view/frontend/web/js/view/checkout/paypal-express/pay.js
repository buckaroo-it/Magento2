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
    'mage/translate'
], function ($, ko, urlBuilder, customerData, sdk, $t) {
    var PayPal;
    (function (PayPal) {
        var PayPalOptions = /** @class */ (function () {
            function PayPalOptions() {}
            return PayPalOptions;
        }());
        PayPal.PayPalOptions = PayPalOptions;
        PayPal.payPalClientId = "ATv1oKfBmc76Zzl8rAMai_OwpXIp9CsDTMzEceayY7X2Sy8t6bQT2rm7DIC7LYbfkch9m9S3R3amkeyU";
        PayPal.payPalPartnerAttributionId = 'NL_BUCKAROO_PPCP';

        PayPal.initiate = function (options) {
            var script = document.createElement("script");
            script.src = "https://www.paypal.com/sdk/js?client-id=" + PayPal.payPalClientId + "&merchant-id=" + options.paypalMerchantId + "&currency=" + options.currency + "&disable-funding=credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo&enable-funding=paylater";
            script.setAttribute('data-partner-attribution-id', PayPal.payPalPartnerAttributionId);
            script.type = "text/javascript";
            script.addEventListener('load', function () {
                paypal.Buttons({
                    createOrder: function () {
                        var createOrderUrl = Base.checkoutUrl + "/api/paypal/createOrder?buckarooWebsiteKey=" + options.buckarooWebsiteKey + "&currency=" + options.currency + "&initialAmount=" + options.amount;
                        if (options.invoiceId !== undefined && options.invoiceId !== null) {
                            createOrderUrl += "&invoiceId=" + options.invoiceId;
                        }
                        return fetch(createOrderUrl, {
                            method: 'post'
                        }).then(function (res) {
                            return res.json();
                        }).then(function (orderData) {
                            console.log(orderData);
                            return orderData.PaypalOrderId;
                        });
                    },
                    onApprove: function (data) {
                        return options.createPaymentHandler(data).then(function () {
                            options.onSuccessCallback();
                        }, function (reason) {
                            if (options.onErrorCallback !== undefined)
                                options.onErrorCallback(reason);
                        });
                    },
                    onShippingChange: function (data, actions) {
                        if (options.onShippingChangeHandler !== undefined)
                            return options.onShippingChangeHandler(data, actions);
                    },
                    onCancel: function () {
                        if (options.onCancelCallback !== undefined)
                            options.onCancelCallback();
                    },
                    onError: function (reason) {
                        if (options.onErrorCallback !== undefined)
                            options.onErrorCallback(reason);
                    },
                    onInit: function () {
                        if (options.onInitCallback !== undefined)
                            options.onInitCallback();
                    },
                    onClick: function () {
                        if (options.onClickCallback !== undefined)
                            options.onClickCallback();
                    }
                }).render(options.containerSelector);
            });
            document.getElementsByTagName("head")[0].appendChild(script);
        };
    })(PayPal = BuckarooSdk.PayPal || (BuckarooSdk.PayPal = {}));

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
                        reject($t("Cannot create payment"));
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
                    this.displayErrorMessage($t("Cannot create payment"));
                }
            }
        },

        onErrorCallback(reason) {
            this.displayErrorMessage(reason);
        },
        onInitCallback() {
        },
        onCancelCallback() {
            this.displayErrorMessage($t("You have canceled the payment request."));
        },
        onClickCallback() {
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
    };
});
