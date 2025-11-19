/*!
    * Buckaroo Client SDK v1.8.2
    *
    * Copyright Buckaroo
    * Released under the MIT license
    * https://buckaroo.nl
    *
    * Warning: Do not cache this SDK to stay in sync with updates.
    *
    * Release Notes
    * - v1.8.2 (22-12-2022): Updated WebsocketUrl
 */
var BuckarooSdk;
(function (BuckarooSdk) {
    var $ = jQuery;
    var Base;
    (function (Base) {
        Base.checkoutUrl = "https://checkout.buckaroo.nl";
        Base.applePaySessionUrl = "https://applepay.buckaroo.io";
        Base.clickToPayEndpointUrl = "https://clicktopay-externalapi.prod-pci.buckaroo.io/";
        Base.tokenApiEndpointUrl = "https://auth.buckaroo.io/";
        Base.websocketUrl = "wss://websocketservice-externalapi.prod.buckaroo.io/";
        var initiate = function () {
            // insert css file into html head
            document.head.insertAdjacentHTML("beforeend", "<link href=\"" + Base.checkoutUrl + "/api/buckaroosdk/css\" rel=\"stylesheet\">");
            // disable cache on GET requests
            $.ajaxSetup({ cache: true });
        };
        Base.setupWebSocket = function (url, onMessageEvent) {
            var socket = new WebSocket(url);
            socket.onclose = function (e) {
                setTimeout(function () {
                    Base.setupWebSocket(url, onMessageEvent);
                }, 200);
            };
            socket.onerror = function (err) {
                socket.close();
            };
            socket.onmessage = onMessageEvent;
        };
        initiate();
    })(Base || (Base = {}));
    var Resources;
    (function (Resources) {
        Resources.bancontactApp = "Bancontact app";
        Resources.bancontactDescription = 'Scan onderstaande code binnen 15 minuten met de <a href="{0}" target="_blank">{1}</a>.'; // using ' in stead of ", because this description contains a html tag
        Resources.bancontactLink = "https://www.bancontact.com/nl";
        Resources.cancel = "Annuleren";
        Resources.failed = "Betaling mislukt.";
        Resources.failedAuth = "Autorisatie mislukt";
        Resources.finish = "Rond de betaling af op uw mobiele apparaat...";
        Resources.finishAuth = "Rond de autorisatie af op uw mobiele apparaat";
        Resources.idealQrDescription = "Scan onderstaande code via uw bank app of de iDEAL QR app.";
        Resources.payconiqApp = "Payconiq app";
        Resources.payconiqDescription = 'Scan onderstaande code met de <a href="{0}" target="_blank">{1}</a>. <br>Deze QR-code is 20 minuten geldig.'; // using ' in stead of ", because this description contains a html tag
        Resources.payconiqLink = "https://www.payconiq.nl/";
        Resources.redirect = "U wordt binnen 5 seconden doorgestuurd...";
        Resources.scanCodeText = "Scan de QR-code";
        Resources.succeeded = "Betaling gelukt!";
        Resources.succeededAuth = "Autorisatie gelukt!";
        Resources.testSelectInputTypeDescription = "Selecteer het invoertype wat u wilt simuleren";
        Resources.testSelectStatusDescription = "Selecteer de status die u wilt simuleren";
        Resources.testStatusSuccessful = "Succesvol";
        Resources.testStatusFailed = "Mislukt";
        Resources.testStatusPending = "In afwachting";
        Resources.testStatusCancelled = "Betaling geannuleerd.";
        Resources.cancelledAuth = "Autorisatie geannuleerd.";
    })(Resources || (Resources = {}));
    var Payconiq;
    (function (Payconiq) {
        var progressClasses = "pending waiting scanned success failed";
        var containerSelector;
        var callbackHandler;
        var inlineQrCode;
        var getCodeUrl = function (transactionKey) {
            var url = Base.checkoutUrl + "/api/payconiq/GetCodeUrl?id=" + transactionKey;
            var getRequest = $.ajax({
                url: url,
                cache: false
            });
            return getRequest;
        };
        var renderQrOrRedirectToApp = function (data) {
            // ios mobile flow
            if (/iPhone|iPod/i.test(navigator.userAgent)) {
                window.location.href = data.PayconiqIosUrl;
                return;
            }
            // android mobile flow
            if (/(android(?=.*mobi))/i.test(navigator.userAgent)) {
                window.location.href = data.PayconiqAndroidUrl;
                return;
            }
            // other mobile mobile flow
            if (/webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                window.location.href = data.PayconiqUrl;
                return;
            }
            // no QrUrl available? do nothing
            if (!data.QrUrl) {
                return;
            }
            // desktop flow
            if (inlineQrCode) {
                $(containerSelector).find("#qrImage img").attr({ src: data.QrUrl, alt: "Payconiq QR code" });
                $(containerSelector).find("#qrProgress").removeClass(progressClasses);
                $(containerSelector).find("#qrProgress").addClass("waiting");
            }
            else {
                $("#qrImage img").attr({ src: data.QrUrl, alt: "Payconiq QR code" });
                $("#qrProgress").removeClass(progressClasses);
                $("#qrProgress").addClass("waiting");
            }
        };
        var setupWebSocketChannel = function (transactionKey) {
            var url = Base.websocketUrl + "Payconiq/" + transactionKey;
            Base.setupWebSocket(url, function (event) {
                // get response object from event
                var responseObj = JSON.parse(event.data);
                // remove any progress classes
                if (inlineQrCode) {
                    $(containerSelector).find("#qrProgress").removeClass(progressClasses);
                }
                else {
                    $("#qrProgress").removeClass(progressClasses);
                }
                switch (responseObj.status) {
                    case "PROCESSING":
                        if (inlineQrCode) {
                            $(containerSelector).find("#qrProgress").addClass("scanned");
                        }
                        else {
                            $("#qrProgress").addClass("scanned");
                        }
                        callbackHandler(responseObj.status, []);
                        break;
                    case "SUCCESS":
                        if (inlineQrCode) {
                            $(containerSelector).find("#qrProgress").addClass("success");
                        }
                        else {
                            $("#qrProgress").addClass("success");
                        }
                        if (callbackHandler(responseObj.status, [responseObj.redirectUrl])) {
                            // redirect to URL (after 3 seconds)
                            setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 3000);
                        }
                        break;
                    case "FAILED":
                        if (inlineQrCode) {
                            $(containerSelector).find("#qrProgress").addClass("failed");
                        }
                        else {
                            $("#qrProgress").addClass("failed");
                        }
                        if (callbackHandler(responseObj.status, [responseObj.redirectUrl])) {
                            // redirect to URL (after 3 seconds)
                            setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 3000);
                        }
                        break;
                }
            });
        };
        var renderHtml = function () {
            var html;
            if (inlineQrCode) {
                html = "<div id=\"qrProgress\" class=\"pending\">\n\t<div class=\"p-4\">\n\t\t<div id=\"qrImage\" class=\"if if-waiting\">\n\t\t\t<img src=\"\" class=\"img-fluid\" />\n\t\t</div>\n\t\t<div id=\"check\" class=\"if if-pending if-scanned if-success if-failed\">\n\t\t\t<div class=\"circle-loader\">\n\t\t\t\t<div class=\"bck-checkmark draw if if-success\"></div>\n\t\t\t\t<div class=\"bck-cross if if-failed\"></div>\n\t\t\t</div>\n\t\t\t<p class=\"if if-scanned pt-4 m-0\">" + Resources.finish + "</p>\n            <p class=\"if if-success pt-4 m-0 text-success\">" + Resources.succeeded + " " + Resources.redirect + "</p>\n            <p class=\"if if-failed pt-4 m-0 text-failed\">" + Resources.failed + " " + Resources.redirect + "</p>\n\t\t</div>\n\t</div>\n</div>";
            }
            else {
                html = "<div class=\"text-align-center\">\n\t<div id=\"qrProgress\" class=\"pending\">\n\t\t<p>" + Resources.payconiqDescription.replace("{0}", Resources.payconiqLink).replace("{1}", Resources.payconiqApp) + "</p>\n\t\t<div class=\"p-4\">\n\t\t\t<div id=\"qrImage\" class=\"if if-waiting\">\n\t\t\t\t<img src=\"\" style=\"opacity: 1;\" height=\"200\" width=\"200\" class=\"img-fluid\" />\n\t\t\t</div>\n\t\t\t<div id=\"check\" class=\"if if-pending if-scanned if-success if-failed\">\n\t\t\t\t<div class=\"circle-loader\">\n\t\t\t\t\t<div class=\"bck-checkmark draw if if-success\"></div>\n\t\t\t\t\t<div class=\"bck-cross if if-failed\"></div>\n\t\t\t\t</div>\n\t\t\t\t<p class=\"if if-scanned pt-4 m-0\">" + Resources.finish + "</p>\n\t\t\t\t<p class=\"if if-success pt-4 m-0 text-success\">" + Resources.succeeded + " " + Resources.redirect + "</p>\n\t\t\t\t<p class=\"if if-failed pt-4 m-0 text-failed\">" + Resources.failed + " " + Resources.redirect + "</p>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n</div>";
            }
            $(containerSelector).append(html);
        };
        Payconiq.initiate = function (selector, transactionKey, callback, inlineQr) {
            if (callback === void 0) { callback = null; }
            if (inlineQr === void 0) { inlineQr = false; }
            containerSelector = selector;
            inlineQrCode = inlineQr;
            callbackHandler = callback || (function () { return true; });
            // render html
            renderHtml();
            // get qr url
            getCodeUrl(transactionKey).done(renderQrOrRedirectToApp);
            // setup websocket
            setupWebSocketChannel(transactionKey);
        };
    })(Payconiq = BuckarooSdk.Payconiq || (BuckarooSdk.Payconiq = {}));
    var PayPal;
    (function (PayPal) {
        var PayPalOptions = /** @class */ (function () {
            function PayPalOptions() {
            }
            return PayPalOptions;
        }());
        PayPal.PayPalOptions = PayPalOptions;
        PayPal.payPalClientId = "ATv1oKfBmc76Zzl8rAMai_OwpXIp9CsDTMzEceayY7X2Sy8t6bQT2rm7DIC7LYbfkch9m9S3R3amkeyU";
        PayPal.payPalCollectingClientId = "ARo6emSQPfMjeY3Jc1ceOfn-2kgNJFVR2CNuKJxOWzq5HtuuSyzzvRvv2bYJFB2hRozmUaVbWLqCTSFO";
        PayPal.payPalPartnerAttributionId = 'NL_BUCKAROO_PPCP';
        var requirePayPal = function (options, callback) {
            var e = document.createElement("script");
            if (options.paypalMerchantId.substring(0, 2) === "c_") {
                options.paypalMerchantId = options.paypalMerchantId.substring(2);
                PayPal.payPalPartnerAttributionId = "Buckaroo_PSP_MP";
                e.src = "https://www.paypal.com/sdk/js?client-id=" + PayPal.payPalCollectingClientId + "&merchant-id=" + options.paypalMerchantId + "&currency=" + options.currency + "&disable-funding=credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo&enable-funding=paylater";
            }
            else {
                e.src = "https://www.paypal.com/sdk/js?client-id=" + PayPal.payPalClientId + "&merchant-id=" + options.paypalMerchantId + "&currency=" + options.currency + "&disable-funding=credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo&enable-funding=paylater";
            }
            e.setAttribute('data-partner-attribution-id', PayPal.payPalPartnerAttributionId);
            e.type = "text/javascript";
            e.addEventListener('load', callback);
            document.getElementsByTagName("head")[0].appendChild(e);
        };
        PayPal.initiate = function (options) {
            requirePayPal(options, function () {
                paypal.Buttons({
                    createOrder: function () {
                        // Call validation callback if provided
                        if (options.onValidationCallback !== undefined) {
                            try {
                                var validationResult = options.onValidationCallback();
                                if (validationResult === false || (validationResult && validationResult.isValid === false)) {
                                    var errorMessage = (validationResult && validationResult.message) ? validationResult.message : "Validation failed";
                                    return Promise.reject(new Error(errorMessage));
                                }
                            } catch (error) {
                                return Promise.reject(error);
                            }
                        }

                        // Original order creation logic
                        var createOrderUrl = Base.checkoutUrl + "/api/paypal/createOrder?buckarooWebsiteKey=" + options.buckarooWebsiteKey + "&currency=" + options.currency + "&initialAmount=" + options.amount;
                        // Optional - Add InvoiceId when available.
                        if (options.invoiceId !== undefined && options.invoiceId !== null) {
                            createOrderUrl += "&invoiceId=" + options.invoiceId;
                        }
                        return fetch(createOrderUrl, {
                            method: 'post'
                        }).then(function (res) {
                            return res.json();
                        }).then(function (orderData) {
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
        };
    })(PayPal = BuckarooSdk.PayPal || (BuckarooSdk.PayPal = {}));
    var IdealQr;
    (function (IdealQr) {
        var qrLoaded = false;
        var progressClasses = "pending waiting scanned success failed";
        var setupWebSocketChannel;
        var transactionKey;
        var isProcessing;
        var containerId;
        var inlineQrCode;
        var getIdealQrCodeUrl = function () {
            // Setup websocket
            setupWebSocketChannel(transactionKey);
            var def = $.Deferred();
            var url = Base.checkoutUrl + "/api/idealQr/GetCodeUrl?transactionKey=" + transactionKey + "&isProcessing=" + isProcessing;
            var getRequest = $.ajax({
                url: url,
                cache: false
            });
            getRequest.done(function (data) {
                def.resolve(data);
            });
            return def.promise();
        };
        setupWebSocketChannel = function (transactionKey) {
            var url = Base.websocketUrl + "IdealQr/" + transactionKey;
            Base.setupWebSocket(url, function (event) {
                // get response object from event
                var responseObj = JSON.parse(event.data);
                // remove any progress classes
                if (inlineQrCode) {
                    $(containerId).find("#qrProgress").removeClass(progressClasses);
                }
                else {
                    $("#qrProgress").removeClass(progressClasses);
                }
                switch (responseObj.status) {
                    case "PROCESSING":
                        if (inlineQrCode) {
                            $(containerId).find("#qrProgress").addClass("scanned");
                        }
                        else {
                            $("#qrProgress").addClass("scanned");
                        }
                        break;
                    case "SUCCESS":
                        if (inlineQrCode) {
                            $(containerId).find("#qrProgress").addClass("success");
                        }
                        else {
                            $("#qrProgress").addClass("success");
                        }
                        setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 5000);
                        break;
                    case "FAILED":
                        if (inlineQrCode) {
                            $(containerId).find("#qrProgress").addClass("failed");
                        }
                        else {
                            $("#qrProgress").addClass("failed");
                        }
                        setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 5000);
                        break;
                }
            });
        };
        IdealQr.initiate = function (containerSelector, trxKey, processing, inlineQr) {
            if (inlineQr === void 0) { inlineQr = false; }
            transactionKey = trxKey;
            isProcessing = processing;
            containerId = containerSelector;
            inlineQrCode = inlineQr;
            if (inlineQrCode) {
                var inlineQrCodeBodyHtml = "<div id=\"qrProgress\" class=\"pending\" >\n\t<div class=\"p-4\">\n\t\t<div id=\"qrImage\" class=\"if if-waiting\">\n\t\t</div>\n\t\t<div id=\"check\" class=\"if if-scanned if-success if-failed if-pending\">\n\t\t\t<div class=\"circle-loader\">\n\t\t\t\t<div class=\"bck-checkmark draw if if-success\"></div>\n\t\t\t\t<div class=\"bck-cross if if-failed\"></div>\n\t\t\t</div>\n\t\t\t<p class=\"if if-scanned pt-4 m-0\">" + Resources.finish + "</p>\n\t\t\t<p class=\"if if-success pt-4 m-0 text-success\">" + Resources.succeeded + " " + Resources.redirect + "</p>\n\t\t\t<p class=\"if if-failed pt-4 m-0 text-failed\">" + Resources.failed + " " + Resources.redirect + "</p>\n\t\t</div>\n\t</div>\n</div>";
                $(containerSelector).append(inlineQrCodeBodyHtml);
                // Get logo.
                var qrCodeUrl = getIdealQrCodeUrl();
                qrCodeUrl.done(function (data) {
                    // Fill logo in popup
                    var image = $("<img src=\"" + data.IdealQrUrl + "\" alt=\"Ideal QR code\" class=\"img-fluid\" />");
                    $(containerSelector).find("#qrImage").append(image);
                    $(containerSelector).find("#qrProgress").removeClass(progressClasses);
                    $(containerSelector).find("#qrProgress").addClass("waiting");
                    qrLoaded = true;
                });
            }
            else {
                // Inject qr button at #insertQrButton
                var buttonHtml = "<div class=\"qr-input-group\">\n\t<div class=\"qr-pink-prepend\">\n\t\t<span class=\"qr-pink-prepend-image\">\n\t\t\t<img src=\"data:image/png;base64,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\" width=\"24\"></img>\n\t\t</span>\n\t</div>\n\t<button type=\"button\" id=\"qrButton\" class=\"qr-pink-button\"> " + Resources.scanCodeText + " </button>\n</div>";
                $(containerSelector).append(buttonHtml);
                // Inject popup html in body
                var bodyHtml = "<div class=\"qr-popup hidden\" id=\"idealQrPopup\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">\n\t<div class=\"bck-popup-dialog\" role=\"document\">\n\t\t<div class=\"bck-popup-content\">\n\t\t\t<div class=\"bck-popup-header\">\n\t\t\t\t<h5 class=\"bck-popup-title\" id=\"examplepopupLabel\">" + Resources.scanCodeText + "</h5>\n\t\t\t</div>\n\t\t\t<div class=\"bck-popup-body text-align-center\">\n\t\t\t\t<div id=\"qrProgress\" class=\"waiting\">\n\t\t\t\t\t<p>" + Resources.idealQrDescription + "</p>\n\t\t\t\t\t<div class=\"p-4\">\n\t\t\t\t\t\t<div id=\"qrImage\" class=\"if if-waiting\">\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div id=\"check\" class=\"if if-scanned if-success if-failed\">\n\t\t\t\t\t\t\t<div class=\"circle-loader\">\n\t\t\t\t\t\t\t\t<div class=\"bck-checkmark draw if if-success\"></div>\n\t\t\t\t\t\t\t\t<div class=\"bck-cross if if-failed\"></div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<p class=\"if if-scanned pt-4 m-0\">" + Resources.finish + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-success pt-4 m-0 text-success\">" + Resources.succeeded + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-failed pt-4 m-0 text-failed\">" + Resources.failed + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class=\"bck-popup-footer\">\n\t\t\t\t<button id=\"close-qr-popup\" type=\"button\">" + Resources.cancel + "</button>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n</div>";
                document.body.insertAdjacentHTML("beforeend", bodyHtml);
                $(document).on("click", "#close-qr-popup", function () {
                    $(".qr-popup").hide();
                });
                $(document).on("click", "#qrButton", function () {
                    if (qrLoaded === false) {
                        // Get logo.
                        var url = getIdealQrCodeUrl();
                        url.done(function (data) {
                            // Fill logo in popup
                            var image = $("<img src=\"" + data.IdealQrUrl + "\" alt=\"Ideal QR code\" style=\"opacity: 0;\" height=\"400\" width=\"400\" class=\"img-fluid\" />");
                            $("#qrImage").append(image);
                            image.delay(500).fadeTo(200, 1);
                            qrLoaded = true;
                        });
                    }
                    $(".qr-popup").show();
                });
            }
        };
    })(IdealQr = BuckarooSdk.IdealQr || (BuckarooSdk.IdealQr = {}));
    var BancontactMobile;
    (function (BancontactMobile) {
        var qrLoaded = false;
        var progressClasses = "pending waiting scanned success failed";
        var transactionKey;
        var callbackHandler;
        var qrCodeContainer;
        var urlIntentButton;
        var useSeparatedView = false;
        var getCodeUrl = function (transactionKey) {
            var url = Base.checkoutUrl + "/api/BancontactMobile/GetCodeUrl?transactionKey=" + transactionKey;
            var getRequest = $.ajax({
                url: url,
                cache: false
            });
            return getRequest;
        };
        var sendTestStatusData = function (status, key, reason) {
            if (reason === void 0) { reason = null; }
            var request = $.ajax({
                cache: false,
                type: "GET",
                url: Base.checkoutUrl + "/api/webhook/transaction/" + key + "?status=" + status + "&reason=" + reason,
            });
            request.done(function () {
                $("#statusButtons").remove();
            });
        };
        var sendTestInputTypeData = function (inputType, key) {
            var request = $.ajax({
                cache: false,
                type: "GET",
                url: Base.checkoutUrl + "/api/webhook/transaction/" + key + "?inputType=" + inputType,
            });
            request.done(function () {
                $("#inputTypeButtons").remove();
                var testButtons = "\n<div id=\"statusButtons\">\n\t<p>" + Resources.testSelectStatusDescription + ":</p>\n\t<div class=\"qr-input-group\">\n\t\t<button type=\"button\" id=\"successfulButton\" class=\"qr-blue-button test-button\">" + Resources.testStatusSuccessful + "</button>\n\t\t<button type=\"button\" id=\"failedButton\" class=\"qr-blue-button test-button\">" + Resources.testStatusFailed + "</button>\n\t\t<button type=\"button\" id=\"cancelledbutton\" class=\"qr-blue-button test-button\">" + Resources.cancel + "</button>\n\t</div>\n</div>\n";
                if (useSeparatedView) {
                    $(qrCodeContainer).find("#qrTestFlow").append(testButtons);
                    $(qrCodeContainer).find("#successfulButton").click(function () {
                        sendTestStatusData("190", key);
                    });
                    $(qrCodeContainer).find("#failedButton").click(function () {
                        sendTestStatusData("490", key, "Failed status chosen in test flow.");
                    });
                    $(qrCodeContainer).find("#cancelledbutton").click(function () {
                        sendTestStatusData("890", key);
                    });
                }
                else {
                    $("#qrTestFlow").append(testButtons);
                    $(document).on("click", "#successfulButton", function () {
                        sendTestStatusData("190", key);
                    });
                    $(document).on("click", "#failedButton", function () {
                        sendTestStatusData("490", key, "Failed status chosen in test flow.");
                    });
                    $(document).on("click", "#cancelledbutton", function () {
                        sendTestStatusData("890", key);
                    });
                }
            });
        };
        var setupWebSocketChannel = function (transactionKey) {
            var url = Base.websocketUrl + "BancontactMobile/" + transactionKey;
            Base.setupWebSocket(url, function (event) {
                // get response object from event
                var responseObj = JSON.parse(event.data);
                // remove any progress classes
                if (useSeparatedView) {
                    $(qrCodeContainer).find("#qrProgress").removeClass(progressClasses);
                }
                else {
                    $("#qrProgress").removeClass(progressClasses);
                }
                switch (responseObj.status) {
                    case "WAITING":
                        if (useSeparatedView) {
                            $(qrCodeContainer).find("#qrProgress").addClass("waiting");
                        }
                        else {
                            $("#qrProgress").addClass("waiting");
                            $("#close-qr-popup").show();
                        }
                        callbackHandler(responseObj.status, []);
                        break;
                    case "PROCESSING":
                        if (useSeparatedView) {
                            $(qrCodeContainer).find("#qrProgress").addClass("scanned");
                        }
                        else {
                            $("#qrProgress").addClass("scanned");
                            $("#close-qr-popup").show();
                        }
                        callbackHandler(responseObj.status, []);
                        break;
                    case "SUCCESS":
                        if (useSeparatedView) {
                            $(qrCodeContainer).find("#qrProgress").addClass("success");
                        }
                        else {
                            $("#qrProgress").addClass("success");
                            $("#close-qr-popup").hide();
                        }
                        if (callbackHandler(responseObj.status, [responseObj.redirectUrl])) {
                            // redirect to URL (after 3 seconds)
                            setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 3000);
                        }
                        break;
                    case "FAILED":
                        if (useSeparatedView) {
                            $(qrCodeContainer).find("#qrProgress").addClass("failed");
                        }
                        else {
                            $("#qrProgress").addClass("failed");
                            $("#close-qr-popup").hide();
                        }
                        if (callbackHandler(responseObj.status, [responseObj.redirectUrl])) {
                            // redirect to URL (after 3 seconds)
                            setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 3000);
                        }
                        break;
                    case "CANCELLED":
                        if (useSeparatedView) {
                            $(qrCodeContainer).find("#qrProgress").addClass("cancelled");
                        }
                        else {
                            $("#qrProgress").addClass("cancelled");
                            $("#close-qr-popup").hide();
                        }
                        if (callbackHandler(responseObj.status, [responseObj.redirectUrl])) {
                            // redirect to URL (after 3 seconds)
                            setTimeout(function () { window.location.href = responseObj.redirectUrl; }, 3000);
                        }
                        break;
                }
            });
        };
        var renderQrOrRedirectToApp = function (data) {
            // validation failed
            if (!data.QrCodeData) {
                $("#qrProgress").removeClass(progressClasses);
                $("#qrProgress").addClass("failed");
                $("#close-qr-popup").hide();
                if (callbackHandler("FAILED", [data.ConfirmUrl])) {
                    // redirect to URL (after 3 seconds)
                    setTimeout(function () { window.location.href = data.ConfirmUrl; }, 3000);
                    return;
                }
            }
            // setup websockets
            setupWebSocketChannel(data.TransactionKey);
            if (data.IsTest) {
                $("#qrTestFlow").show();
                var testButtons = "\n<div id=\"inputTypeButtons\">\n\t<p>" + Resources.testSelectInputTypeDescription + ":</p>\n\t<div class=\"qr-input-group\">\n\t\t<button type=\"button\" id=\"qrInputTypeButton\" class=\"qr-blue-button test-button\">QR-Code</button>\n\t\t<button type=\"button\" id=\"urlInputTypeButton\" class=\"qr-blue-button test-button\">URL-Intent</button>\n\t</div>\n</div>\n";
                if (useSeparatedView) {
                    $(qrCodeContainer).find("#qrTestFlow").append(testButtons);
                    $(qrCodeContainer).find("#qrInputTypeButton").click(function () {
                        sendTestInputTypeData("QrCode", data.TransactionKey);
                    });
                    $(qrCodeContainer).find("#urlInputTypeButton").click(function () {
                        sendTestInputTypeData("UrlIntent", data.TransactionKey);
                    });
                }
                else {
                    $("#qrTestFlow").append(testButtons);
                    $(document).on("click", "#qrInputTypeButton", function () {
                        sendTestInputTypeData("QrCode", data.TransactionKey);
                    });
                    $(document).on("click", "#urlInputTypeButton", function () {
                        sendTestInputTypeData("UrlIntent", data.TransactionKey);
                    });
                }
            }
            else {
                $("#qrTestFlow").hide();
                if (useSeparatedView) {
                    $(urlIntentButton).click(function () { window.location.href = data.UrlIntentData; });
                }
                else {
                    // mobile flow
                    if (/iPhone|iPod|(android(?=.*mobi))|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                        window.location.href = data.UrlIntentData;
                        return;
                    }
                }
                // no QrUrl available? do nothing
                if (!data.QrUrl) {
                    return;
                }
            }
            qrLoaded = true;
            if (useSeparatedView) {
                $(qrCodeContainer).find("#qrImage img").attr({ src: data.QrUrl, alt: "Bancontact mobile QR code" });
                $(qrCodeContainer).find("#qrProgress").removeClass(progressClasses);
                $(qrCodeContainer).find("#qrProgress").addClass("waiting");
            }
            else {
                // desktop flow
                var image = $("<img src=\"" + data.QrUrl + "\" alt=\"Bancontact mobile QR code\" style=\"opacity: 0;\" height=\"400\" width=\"400\" class=\"img-fluid\" />");
                $("#qrImage").append(image);
                image.delay(500).fadeTo(200, 1);
                $("#qrProgress").removeClass(progressClasses);
                $("#qrProgress").addClass("waiting");
            }
        };
        BancontactMobile.initiateSeparate = function (qrCodeContainerId, urlIntentButtonId, trxKey, intent, callback) {
            if (callback === void 0) { callback = null; }
            transactionKey = trxKey;
            callbackHandler = callback || (function () { return true; });
            qrCodeContainer = qrCodeContainerId;
            urlIntentButton = urlIntentButtonId;
            useSeparatedView = true;
            var resourceStringFinish = intent == "Pay" ? Resources.finish : Resources.finishAuth;
            var resourceStringSucceeded = intent == "Pay" ? Resources.succeeded : Resources.succeededAuth;
            var resourceStringFailed = intent == "Pay" ? Resources.failed : Resources.failedAuth;
            var resourceStringCancelled = intent == "Pay" ? Resources.testStatusCancelled : Resources.cancelledAuth;
            var qrCodeHtml = "<div id=\"qrProgress\" class=\"pending\">\n\t\t\t\t\t<div class=\"p-4\">\n\t\t\t\t\t\t<div id=\"qrImage\" class=\"if if-waiting\">\n\t\t\t\t\t\t\t<img src=\"\" class=\"img-fluid\" />\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div id=\"check\" class=\"if if-scanned if-success if-failed if-pending if-cancelled\">\n\t\t\t\t\t\t\t<div class=\"circle-loader\">\n\t\t\t\t\t\t\t\t<div class=\"bck-checkmark draw if if-success\"></div>\n\t\t\t\t\t\t\t\t<div class=\"bck-cross if if-failed\"></div>\n\t\t\t\t\t\t\t\t<div class=\"bck-triangle if if-cancelled\"></div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<p class=\"if if-scanned pt-4 m-0\">" + resourceStringFinish + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-success pt-4 m-0 text-success\">" + resourceStringSucceeded + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-failed pt-4 m-0 text-failed\">" + resourceStringFailed + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-cancelled pt-4 m-0 text-cancelled\">" + resourceStringCancelled + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div id=\"qrTestFlow\" hidden>\n\t\t\t\t</div>";
            $(qrCodeContainer).append(qrCodeHtml);
            // get qr url
            getCodeUrl(transactionKey).done(renderQrOrRedirectToApp);
        };
        BancontactMobile.initiate = function (containerSelector, trxKey, callback) {
            if (callback === void 0) { callback = null; }
            transactionKey = trxKey;
            callbackHandler = callback || (function () { return true; });
            // Inject qr button at #insertBancontactQrButton
            var buttonHtml = "<div class=\"qr-input-group\">\n\t<div class=\"qr-pink-prepend\">\n\t\t<span class=\"qr-pink-prepend-image\">\n\t\t\t<img src=\"data:image/png;base64,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\" width=\"24\"></img>\n\t\t</span>\n\t</div>\n\t<button type=\"button\" id=\"qrButton\" class=\"qr-blue-button\">" + Resources.bancontactApp + "</button>\n</div>";
            $(containerSelector).append(buttonHtml);
            // Inject popup html in body
            var bodyHtml = "<div class=\"qr-popup hidden\" id=\"bancontactMobilePopup\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">\n\t<div class=\"bck-popup-dialog\" role=\"document\">\n\t\t<div class=\"bck-popup-content\">\n\t\t\t<div class=\"bck-popup-header\"> \n\t\t\t\t<h5 class=\"bck-popup-title\" id=\"examplepopupLabel\">" + Resources.scanCodeText + "</h5>\n\t\t\t</div>\n\t\t\t<div class=\"bck-popup-body text-align-center\">\n\t\t\t\t<div id=\"qrProgress\" class=\"pending\">\n\t\t\t\t\t<p>" + Resources.bancontactDescription.replace("{0}", Resources.bancontactLink).replace("{1}", Resources.bancontactApp) + "</p>\n\t\t\t\t\t<div class=\"p-4\">\n\t\t\t\t\t\t<div id=\"qrImage\" class=\"if if-waiting\">\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div id=\"check\" class=\"if if-scanned if-success if-failed if-pending\">\n\t\t\t\t\t\t\t<div class=\"circle-loader\">\n\t\t\t\t\t\t\t\t<div class=\"bck-checkmark draw if if-success\"></div>\n\t\t\t\t\t\t\t\t<div class=\"bck-cross if if-failed\"></div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<p class=\"if if-scanned pt-4 m-0\">" + Resources.finish + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-success pt-4 m-0 text-success\">" + Resources.succeeded + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t\t<p class=\"if if-failed pt-4 m-0 text-failed\">" + Resources.failed + " " + Resources.redirect + "</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t\t<div id=\"qrTestFlow\" hidden>\t\t\t\t\t\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class=\"bck-popup-footer\">\n\t\t\t\t<button id=\"close-qr-popup\" type=\"button\">" + Resources.cancel + "</button>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n</div>";
            document.body.insertAdjacentHTML("beforeend", bodyHtml);
            $(document).on("click", "#close-qr-popup", function () {
                $(".qr-popup").hide();
            });
            $(document).on("click", "#qrButton", function () {
                if (qrLoaded === false) {
                    // get qr url
                    getCodeUrl(transactionKey).done(renderQrOrRedirectToApp);
                }
                $(".qr-popup").show();
            });
        };
    })(BancontactMobile = BuckarooSdk.BancontactMobile || (BuckarooSdk.BancontactMobile = {}));
    var ApplePay;
    (function (ApplePay) {
        ApplePay.checkApplePaySupport = function (merchantIdentifier) {
            if (!("ApplePaySession" in window))
                return Promise.resolve(false);
            if (ApplePaySession === undefined)
                return Promise.resolve(false);
            return ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
        };
        var ApplePayPayment = /** @class */ (function () {
            function ApplePayPayment(buttonSelector, options) {
                var _this = this;
                this.applePayVersion = 4;
                this.validationUrl = Base.applePaySessionUrl + "/v1/request-session";
                /**
                 * Aborts the current ApplePaySession if exists.
                 */
                this.abortSession = function () {
                    if (_this.session) {
                        _this.session.abort();
                    }
                };
                /**
                 * Initializes the ApplePay button
                 */
                this.showPayButton = function (buttonStyle, buttonType) {
                    if (buttonStyle === void 0) { buttonStyle = "black"; }
                    if (buttonType === void 0) { buttonType = 'plain'; }
                    _this.button.attr("lang", _this.options.cultureCode);
                    _this.button.on("click", _this.beginPayment);
                    _this.button.addClass("apple-pay apple-pay-button");
                    switch (buttonType) {
                        case "plain":
                            _this.button.addClass("apple-pay-button-type-plain");
                            break;
                        case "book":
                            _this.button.addClass("apple-pay-button-type-book");
                            break;
                        case "buy":
                            _this.button.addClass("apple-pay-button-type-buy");
                            break;
                        case "check-out":
                            _this.button.addClass("apple-pay-button-type-check-out");
                            break;
                        case "donate":
                            _this.button.addClass("apple-pay-button-type-donate");
                            break;
                        case "set-up":
                            _this.button.addClass("apple-pay-button-type-set-up");
                            break;
                        case "subscribe":
                            _this.button.addClass("apple-pay-button-type-subscribe");
                            break;
                    }
                    switch (buttonStyle) {
                        case "black":
                            _this.button.addClass("apple-pay-button-black");
                            break;
                        case "white":
                            _this.button.addClass("apple-pay-button-white");
                            break;
                        case "white-outline":
                            _this.button.addClass("apple-pay-button-white-with-line");
                            break;
                    }
                };
                this.validate = function () {
                    if (!_this.button.length)
                        throw "ApplePay: button element does not exist";
                    if (!_this.options.processCallback)
                        throw "ApplePay: processCallback must be set";
                    if (!_this.options.storeName)
                        throw "ApplePay: storeName is not set";
                    if (!_this.options.countryCode)
                        throw "ApplePay: countryCode is not set";
                    if (!_this.options.currencyCode)
                        throw "ApplePay: currencyCode is not set";
                    if (!_this.options.merchantIdentifier)
                        throw "ApplePay: merchantIdentifier is not set";
                };
                this.beginPayment = function (e) {
                    e.preventDefault();
                    var paymentRequest = {
                        countryCode: _this.options.countryCode,
                        currencyCode: _this.options.currencyCode,
                        merchantCapabilities: _this.options.merchantCapabilities,
                        supportedNetworks: _this.options.supportedNetworks,
                        lineItems: _this.options.lineItems,
                        total: _this.options.totalLineItem,
                        requiredBillingContactFields: _this.options.requiredBillingContactFields,
                        requiredShippingContactFields: _this.options.requiredShippingContactFields,
                        shippingType: _this.options.shippingType,
                        shippingMethods: _this.options.shippingMethods
                    };
                    // Create the Apple Pay session.
                    _this.session = new ApplePaySession(_this.applePayVersion, paymentRequest);
                    // Setup handler for validation the merchant session.
                    _this.session.onvalidatemerchant = _this.onValidateMerchant;
                    // Setup handler for shipping method selection.
                    if (_this.options.shippingMethodSelectedCallback)
                        _this.session.onshippingmethodselected = _this.onShippingMethodSelected;
                    // Setup handler for shipping contact selection.
                    if (_this.options.shippingContactSelectedCallback)
                        _this.session.onshippingcontactselected = _this.onShippingContactSelected;
                    // Setup handler for shipping method selection.
                    if (_this.options.cancelCallback)
                        _this.session.oncancel = _this.onCancel;
                    // Setup handler to receive the token when payment is authorized.
                    _this.session.onpaymentauthorized = _this.onPaymentAuthorized;
                    // Begin the session to display the Apple Pay sheet.
                    _this.session.begin();
                };
                /**
                 * Handles merchant validation for the Apple Pay session.
                 * @param event - The ApplePayValidateMerchantEvent object.
                 */
                this.onValidateMerchant = function (event) {
                    // Create the payload.
                    var data = {
                        validationUrl: event.validationURL,
                        displayName: _this.options.storeName,
                        domainName: window.location.hostname,
                        merchantIdentifier: _this.options.merchantIdentifier,
                    };
                    // Post the payload to the server to validate the
                    // merchant session using the merchant certificate.
                    $.post(_this.validationUrl, JSON.stringify(data), function (merchantSession) {
                        // Complete validation by passing the merchant session to the Apple Pay session.
                        _this.session.completeMerchantValidation(merchantSession);
                    });
                };
                /**
                 * Handles the Apple Pay payment being authorized by the user.
                 * @param event - The ApplePayPaymentAuthorizedEvent object.
                 */
                this.onPaymentAuthorized = function (event) {
                    // Get the payment data for use to capture funds from
                    // the encrypted Apple Pay token in your server.
                    var payment = event.payment;
                    // Process the payment
                    _this.options.processCallback(payment).then(function (authorizationResult) {
                        // Complete payment
                        _this.session.completePayment(authorizationResult);
                    });
                };
                /**
                 * Handles the shipping method being changed by the user
                 * @param event - The ApplePayShippingMethodSelectedEvent object.
                 */
                this.onShippingMethodSelected = function (event) {
                    if (!_this.options.shippingMethodSelectedCallback)
                        return;
                    _this.options.shippingMethodSelectedCallback(event.shippingMethod).then(function (result) {
                        if (!result)
                            return;
                        _this.session.completeShippingMethodSelection(result);
                    });
                };
                /**
                 * Handles the shipping contact being changed by the user
                 * @param event - The ApplePayShippingContactSelectedEvent object.
                 */
                this.onShippingContactSelected = function (event) {
                    if (!_this.options.shippingContactSelectedCallback)
                        return;
                    _this.options.shippingContactSelectedCallback(event.shippingContact).then(function (result) {
                        if (!result)
                            return;
                        _this.session.completeShippingContactSelection(result);
                    });
                };
                /**
                 * An event handler that is automatically called when the payment UI is dismissed.
                 * @param event - The Event object.
                 */
                this.onCancel = function (event) {
                    if (!_this.options.cancelCallback)
                        return;
                    _this.options.cancelCallback(event);
                };
                this.button = $(buttonSelector);
                this.options = options;
                this.validate();
            }
            return ApplePayPayment;
        }());
        ApplePay.ApplePayPayment = ApplePayPayment;
        var CardBrand;
        (function (CardBrand) {
            CardBrand["Mastercard"] = "Mastercard";
            CardBrand["Visa"] = "Visa";
            CardBrand["Maestro"] = "Maestro";
            CardBrand["Bancontact"] = "Bancontact";
            CardBrand["Amex"] = "Amex";
            CardBrand["Unknown"] = "Unknown";
        })(CardBrand = ApplePay.CardBrand || (ApplePay.CardBrand = {}));
        function PredictCardBrand(cardNumberBeginning) {
            if (RegExp('^3').test(cardNumberBeginning))
                return CardBrand.Amex;
            if (cardNumberBeginning.length < 4)
                return CardBrand.Unknown;
            if (RegExp('^(5018|5020|5038|6304|6759|6761|6763)').test(cardNumberBeginning))
                return CardBrand.Maestro;
            if (RegExp('^(4544|4579|4796|4871|4918|4934|4940|5127|5169|5182|5203|5214|5221|5229|5244|5247|5255|5394|5613|5614|6060|6703|4810|5480|5559)').test(cardNumberBeginning))
                return CardBrand.Bancontact;
            if (RegExp('^4'))
                return CardBrand.Visa;
            if (RegExp('^5'))
                return CardBrand.Mastercard;
            return CardBrand.Unknown;
        }
        ApplePay.PredictCardBrand = PredictCardBrand;
        var ApplePayOptions = /** @class */ (function () {
            function ApplePayOptions(storeName, countryCode, currencyCode, cultureCode, merchantIdentifier, lineItems, totalLineItem, shippingType, shippingMethods, processCallback, shippingMethodSelectedCallback, shippingContactSelectedCallback, requiredBillingContactFields, requiredShippingContactFields, cancelCallback, merchantCapabilities, supportedNetworks) {
                if (shippingMethodSelectedCallback === void 0) { shippingMethodSelectedCallback = null; }
                if (shippingContactSelectedCallback === void 0) { shippingContactSelectedCallback = null; }
                if (requiredBillingContactFields === void 0) { requiredBillingContactFields = ["email", "name", "postalAddress"]; }
                if (requiredShippingContactFields === void 0) { requiredShippingContactFields = ["email", "name", "postalAddress"]; }
                if (cancelCallback === void 0) { cancelCallback = null; }
                if (merchantCapabilities === void 0) { merchantCapabilities = ["supports3DS", "supportsCredit", "supportsDebit"]; }
                if (supportedNetworks === void 0) { supportedNetworks = ["masterCard", "visa", "maestro", "vPay", "cartesBancaires", "privateLabel"]; }
                this.storeName = storeName;
                this.countryCode = countryCode;
                this.currencyCode = currencyCode;
                this.cultureCode = cultureCode;
                this.merchantIdentifier = merchantIdentifier;
                this.lineItems = lineItems;
                this.totalLineItem = totalLineItem;
                this.shippingType = shippingType;
                this.shippingMethods = shippingMethods;
                this.processCallback = processCallback;
                this.shippingMethodSelectedCallback = shippingMethodSelectedCallback;
                this.shippingContactSelectedCallback = shippingContactSelectedCallback;
                this.requiredBillingContactFields = requiredBillingContactFields;
                this.requiredShippingContactFields = requiredShippingContactFields;
                this.cancelCallback = cancelCallback;
                this.merchantCapabilities = merchantCapabilities;
                this.supportedNetworks = supportedNetworks;
            }
            return ApplePayOptions;
        }());
        ApplePay.ApplePayOptions = ApplePayOptions;
    })(ApplePay = BuckarooSdk.ApplePay || (BuckarooSdk.ApplePay = {}));
    var ClickToPay;
    (function (ClickToPay) {
        function initiateClickToPayDropInUI(identifier, scriptUrl, captureContextJwt, buttonWrapper, paymentScreenWrapper, processPaymentCallback) {
            var script = document.createElement("script");
            script.src = scriptUrl;
            script.type = "text/javascript";
            script.addEventListener("load", function () {
                if (typeof Accept !== "function")
                    throw Error("ClickToPay: Accept is not available after loading the script.");
                Accept(captureContextJwt)
                    .then(function (accept) { return accept.unifiedPayments(false); })
                    .then(function (up) { return up.show({ containers: { paymentSelection: buttonWrapper, paymentScreen: paymentScreenWrapper } }); })
                    .then(function (transientToken) {
                    var clickToPayPayment = { transientToken: transientToken, identifier: identifier };
                    processPaymentCallback(clickToPayPayment);
                })
                    .catch(function (error) {
                    throw Error("ClickToPay Unified Checkout threw an error. " + error.reason + ": " + error.message);
                });
            });
            script.onerror = function () {
                console.error("ClickToPay: Failed to load script: " + scriptUrl);
            };
            document.getElementsByTagName("head")[0].appendChild(script);
        }
        ClickToPay.initiateClickToPayDropInUI = initiateClickToPayDropInUI;
        var CaptureContext = /** @class */ (function () {
            function CaptureContext(buttonWrapper, paymentScreenWrapper, options) {
                var _this = this;
                this.clickToPayTokenApiScope = "clicktopay:save";
                this.validate = function (buttonWrapper, paymentScreenWrapper, options) {
                    if (!buttonWrapper.length)
                        throw "ClickToPay: button wrapper element does not exist";
                    if (!paymentScreenWrapper.length)
                        throw "ClickToPay: payment screen wrapper element does not exist";
                    if (!options.targetOrigins)
                        throw "ClickToPay: targetOrigins is not set";
                    if (!options.merchantIdentifier)
                        throw "ClickToPay: merchantIdentifier is not set";
                    if (!options.country)
                        throw "ClickToPay: country is not set";
                    if (!options.locale)
                        throw "ClickToPay: locale is not set";
                    if (!options.orderInformation.currency)
                        throw "ClickToPay: currency is not set";
                    if (!options.orderInformation.totalAmount)
                        throw "ClickToPay: totalAmount is not set";
                    if (!options.processPaymentCallback)
                        throw "ClickToPay: processPaymentCallback must be set";
                };
                this.generateCaptureContext = function (accessToken) {
                    return fetch(Base.clickToPayEndpointUrl + "api/GenerateCaptureContext", {
                        method: 'post',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': "Bearer " + accessToken
                        },
                        body: JSON.stringify(_this.options),
                    })
                        .then(function (response) {
                        if (!response.ok) {
                            throw new Error("ClickToPay: Generation of capture context failed! " + response.statusText);
                        }
                        return response.json();
                    })
                        .then(function (captureContextResponse) { return captureContextResponse; })
                        .catch(function (error) {
                        throw new Error("ClickToPay: Generation of capture context failed! " + error);
                    });
                };
                this.validate(buttonWrapper, paymentScreenWrapper, options);
                this.options = options;
                this.options.transactionSource = 'Sdk';
                this.buttonWrapper = buttonWrapper;
                this.paymentScreenWrapper = paymentScreenWrapper;
            }
            CaptureContext.prototype.generateAndLoadCaptureContext = function (clientId, clientSecret) {
                var _this = this;
                TokenApi.getAccessToken(clientId, clientSecret, this.clickToPayTokenApiScope).then(function (accessToken) {
                    _this.generateCaptureContext(accessToken).then(function (captureContext) {
                        if (captureContext && captureContext.successful && captureContext.scriptUrl && captureContext.jwt)
                            initiateClickToPayDropInUI(captureContext.identifier, captureContext.scriptUrl, captureContext.jwt, _this.buttonWrapper, _this.paymentScreenWrapper, _this.options.processPaymentCallback);
                        else
                            throw new Error("ClickToPay: Generation of capture context failed! " + captureContext.errorReason);
                    });
                });
            };
            return CaptureContext;
        }());
        ClickToPay.CaptureContext = CaptureContext;
        var CaptureContextOptions = /** @class */ (function () {
            function CaptureContextOptions(merchantIdentifier, targetOrigins, country, locale, orderInformation, processPaymentCallback) {
                this.merchantIdentifier = merchantIdentifier;
                this.transactionSource = 'Sdk';
                this.targetOrigins = targetOrigins;
                this.country = country;
                this.locale = locale;
                this.orderInformation = orderInformation;
                this.processPaymentCallback = processPaymentCallback;
            }
            return CaptureContextOptions;
        }());
        ClickToPay.CaptureContextOptions = CaptureContextOptions;
    })(ClickToPay = BuckarooSdk.ClickToPay || (BuckarooSdk.ClickToPay = {}));
    var TokenApi;
    (function (TokenApi) {
        function getAccessToken(clientId, clientSecret, scope) {
            var headers = new Headers();
            headers.append("Content-Type", "application/x-www-form-urlencoded");
            headers.append("Authorization", "Basic " + btoa(clientId + ":" + clientSecret));
            var params = new URLSearchParams();
            params.append("scope", scope);
            params.append("grant_type", "client_credentials");
            return fetch(Base.tokenApiEndpointUrl + "oauth/token", {
                method: "POST",
                headers: headers,
                body: params,
            })
                .then(function (response) {
                if (!response.ok)
                    throw new Error("Access Token retrieval has failed: " + response.statusText);
                return response.json();
            })
                .then(function (tokenRes) {
                var accessToken = tokenRes.access_token;
                if (!accessToken)
                    throw new Error("Access token is missing in the response!");
                return accessToken;
            })
                .catch(function (error) {
                throw new Error("Access Token retrieval has failed: " + error);
            });
        }
        TokenApi.getAccessToken = getAccessToken;
    })(TokenApi = BuckarooSdk.TokenApi || (BuckarooSdk.TokenApi = {}));
})(BuckarooSdk || (BuckarooSdk = {}));
