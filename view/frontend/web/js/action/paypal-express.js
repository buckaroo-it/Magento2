define([], function () {
    "use strict";
    let checkoutUrl = "https://checkout.buckaroo.nl";
    let payPalClientId =
    "ATv1oKfBmc76Zzl8rAMai_OwpXIp9CsDTMzEceayY7X2Sy8t6bQT2rm7DIC7LYbfkch9m9S3R3amkeyU";
    let buttonStyle = {
        color: "gold",
        shape: "rect",
    };
    let amount = 0.01;
    let currency = "EUR";
  /**
   * Load the paypal sdk
   */
    const requirePayPal = function (url, callback) {
        const e = document.createElement("script");
        e.src = url;
        e.setAttribute("data-partner-attribution-id", "NL_BUCKAROO_PPCP");
        e.type = "text/javascript";
        e.addEventListener("load", callback);
        document.getElementsByTagName("head")[0].appendChild(e);
    };

  /**
   * Set mode test
   */
    const setTestMode = function () {
        checkoutUrl = "https://testcheckout.buckaroo.nl";
        payPalClientId =
        "AfHztAEfaf3f76tNy8j_Z86w5y-fGbqbBt04PXppVFtJatje79gVSB27DwBENnyFgfhFvKzgJbegNpHv";
    };

    const setAmount = function (newAmount) {
        amount = newAmount;
    };

    const setCurrency = function (newCurrency) {
        currency = newCurrency;
    };

  /**
   * Initiate de button display
   */
    const initiate = function (options) {
        const style = options.style || {};
        if (options.isTestMode) {
            setTestMode();
        }

        if (options.amount) {
            amount = options.amount;
        }

        if (options.currency) {
            currency = options.currency;
        }

        requirePayPal(
            "https://www.paypal.com/sdk/js?client-id=" +
            payPalClientId +
            "&merchant-id=" +
            options.paypalMerchantId +
            "&currency=" +
            currency +
            "&disable-funding=credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo&enable-funding=paylater",
            function () {
                paypal
                .Buttons({
                    style: Object.assign(buttonStyle, style),
                    createOrder: function () {
                        var createOrderUrl =
                        checkoutUrl +
                        "/api/paypal/createOrder?buckarooWebsiteKey=" +
                        options.buckarooWebsiteKey +
                        "&currency=" +
                        currency +
                        "&initialAmount=" +
                        amount;
                      // Optional - Add InvoiceId when available.
                        if (
                        options.invoiceId !== undefined &&
                        options.invoiceId !== null
                        ) {
                            createOrderUrl += "&invoiceId=" + options.invoiceId;
                        }
                        return fetch(createOrderUrl, {
                            method: "post",
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (orderData) {
                            console.log(orderData);
                            return orderData.PaypalOrderId;
                        });
                    },
                    onApprove: function (data) {
                        return options.createPaymentHandler(data).then(
                            function () {
                                options.onSuccessCallback();
                            },
                            function (reason) {
                                if (options.onErrorCallback !== undefined) {
                                    options.onErrorCallback(reason);
                                }
                            }
                        );
                    },
                    onShippingChange: function (data, actions) {
                        if (options.onShippingChangeHandler !== undefined) {
                            return options.onShippingChangeHandler(data, actions);
                        }
                    },
                    onCancel: function () {
                        if (options.onCancelCallback !== undefined) {
                            options.onCancelCallback();
                        }
                    },
                    onError: function (reason) {
                        if (options.onErrorCallback !== undefined) {
                            options.onErrorCallback(reason);
                        }
                    },
                    onInit: function () {
                        if (options.onInitCallback !== undefined) {
                            options.onInitCallback();
                        }
                    },
                    onClick: function () {
                        if (options.onClickCallback !== undefined) {
                            options.onClickCallback();
                        }
                    },
                })
                .render(options.containerSelector);
            }
        );
    };
    return {
        initiate,
        setAmount,
        setCurrency,
    };
});
