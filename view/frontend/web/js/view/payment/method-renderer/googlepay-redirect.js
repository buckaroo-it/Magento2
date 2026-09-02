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
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'mage/url'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        url
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_googlepay_redirect'
                },
                redirectAfterPlaceOrder: false,
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,

                /**
                 * @override
                 */
                initObservable: function () {
                    this._super();
                    return this;
                },

                /**
                 * Handle redirect mode order placement
                 */
                placeOrder: function (data, event) {
                    var self = this;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);

                        var placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                        $.when(placeOrder).fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(this.afterPlaceOrder.bind(this));

                        return true;
                    }

                    return false;
                },

                /**
                 * After place order callback
                 */
                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.responseData;

                    // Handle redirect if RequiredAction is present
                    if (response && response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        window.location.replace(response.RequiredAction.RedirectURL);
                    } else {
                        this.redirectToSuccess();
                    }
                },

                /**
                 * Redirect to success page
                 */
                redirectToSuccess: function () {
                    window.location.replace(url.build('checkout/onepage/success/'));
                },

                /**
                 * @override
                 */
                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                        }
                    };
                },

            }
        );
    }
);
