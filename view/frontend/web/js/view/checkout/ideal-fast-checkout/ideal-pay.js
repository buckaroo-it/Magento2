define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'mage/url',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_CheckoutAgreements/js/model/agreements-assigner',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'buckaroo/checkout/common'
], function (
    $,
    Component,
    ko,
    quote,
    urlBuilder,
    storage,
    url,
    errorProcessor,
    customer,
    fullScreenLoader,
    agreementsAssigner,
    alert,
    $t,
    checkoutCommon
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Buckaroo_Magento2/catalog/product/view/ideal-fast-checkout'
        },
        initialize: function () {
            this._super();
            console.log('Component initialized');
        },
        startProcess: function() {
            console.log('startProcess called');
            this.onShippingChangeHandler();
            this.createPaymentHandler();
            this.processPayment();
        },
        processPayment: function (paymentData, redirectOnSuccess, messageContainer) {
            console.log('processPayment called');
            var serviceUrl, payload;

            redirectOnSuccess = redirectOnSuccess !== false;

            paymentData = {
                method: "buckaroo_magento2_ideal",
                po_number: null,
                additional_data: { issuer: "ASNBNL21" }
            };

            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-buckaroo/:quoteId/payment-information', {
                    quoteId: "23WJG3msarGt0AzdP6mEvpWp2AFcM4Iw"
                });
                payload = {
                    cartId: "23WJG3msarGt0AzdP6mEvpWp2AFcM4Iw",
                    email: "albina@random.com",
                    paymentMethod: paymentData
                };
            } else {
                serviceUrl = url.createUrl('/buckaroo/payment-information', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    paymentMethod: paymentData
                };
            }

            serviceUrl = 'rest/default/V1/guest-buckaroo/23WJG3msarGt0AzdP6mEvpWp2AFcM4Iw/payment-information';

            return storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done(
                function (response) {
                    console.log('Payment processed successfully', response);
                    let jsonResponse = $.parseJSON(response);
                    if (typeof jsonResponse === 'object' && typeof jsonResponse.limitReachedMessage === 'string') {
                        alert({
                            title: $t('Error'),
                            content: $t(jsonResponse.limitReachedMessage),
                            buttons: [{
                                text: $t('Close'),
                                class: 'action primary accept',
                                click: function () {
                                    this.closeModal(true);
                                }
                            }]
                        });
                        $('.' + paymentData.method).remove();
                    } else if (redirectOnSuccess) {
                        checkoutCommon.redirectHandle(jsonResponse);
                        window.location.replace(url.build('checkout/onepage/success/'));
                    }
                    window.checkoutConfig.payment.buckaroo.response = response;
                    checkoutCommon.redirectHandle(jsonResponse);

                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function (response) {
                    console.log('Payment processing failed', response);
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        },
        onShippingChangeHandler: function(data, actions) {
            console.log('onShippingChangeHandler called');

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
                        console.log('Shipping set successfully', response);
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

            });
        },
        createPaymentHandler: function(data) {
            console.log('createPaymentHandler called');
            console.log(data);
            return this.createTransaction(data);
        },
        createTransaction: function(orderId) {
            console.log('createTransaction called');

            const cart_id = this.cart_id;
            return new Promise((resolve, reject) => {
                $.post(url.build("rest/V1/buckaroo/paypal-express/order/create"),
                    {
                        paypal_order_id: 5,
                        cart_id
                    }).then(
                    (response) => {
                        console.log('Transaction created successfully', response);
                        this.result = response;
                        resolve(response);
                    },
                    (reason) => reject(reason)
                );
            });
        },

        getOrderData() {
            let form = $("#product_addtocart_form");
            if (this.page === 'product') {
                return form.serialize();
            }
        },
        setShipping: function(data) {
            console.log('setShipping called');

            return $.post(url.build("rest/V1/buckaroo/paypal-express/quote/create"), {
                shipping_address: "ddddd",
                order_data: this.getOrderData(),
                page: this.page,
            });
        }
    });
});
