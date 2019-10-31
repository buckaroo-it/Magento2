/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'uiComponent',
        'jquery',
        'Magento_Checkout/js/action/get-totals',
        'mage/storage',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/resource-url-manager',
        'Magento_Checkout/js/model/quote'
    ],
    function (Component, $, getTotals, storage, totals, resourceUrlManager, quote) {
        'use strict';

        return Component.extend(
            {

                initialize: function () {
                    this._super();

                    /**
                     * Observe the onclick event on all payment methods.
                     */
                    $('body').on(
                        'click',
                        '.payment-methods input[type="radio"][name="payment[method]"]',
                        this.savePaymentMethod
                    );
                },

                /**
                 * Save the selected payment method.
                 */
                savePaymentMethod: function () {
                    /**
                     * Build the URL for saving the selected payment method.
                     */
                    var params = {};
                    var payload = {};

                    /**
                     * If we're checking out as guest, we're going to need a cartId and a guest email
                     */
                    if (resourceUrlManager.getCheckoutMethod() == 'guest') {
                        params = {
                            cartId: quote.getQuoteId()
                        };
                        payload.email = quote.guestEmail;
                    }

                    var urls = {
                        'guest': '/guest-carts/:cartId/set-payment-information',
                        'customer': '/carts/mine/set-payment-information'
                    };
                    var url = resourceUrlManager.getUrl(urls, params);

                    /**
                     * The API expects a JSON object with the selected payment method and the selected billing address
                     */
                    payload.paymentMethod = {
                        method: $('.payment-methods input[type="radio"][name="payment[method]"]:checked').val(),
                        additional_data: {
                            buckaroo_skip_validation: true
                        }
                    };
                    payload.billingAddress = quote.billingAddress();

                    /**
                     * The standard Magento flow expects only checked agreements to be added. However,
                     * set-payment-information expects and validates if all required agreements are checked as well. Which
                     * isn't possible right after selecting a payment method. Therefore we simply send all agreements in
                     * order to pass the validation. The agreements will be validated properly when the order is being placed.
                     */
                    var agreementsConfig = window.checkoutConfig.checkoutAgreements;

                    if (agreementsConfig.isEnabled) {
                        var agreementData = $('.payment-method._active div[data-role=checkout-agreements] input');
                        var agreementIds = [];

                        agreementData.each(function (index, item) {
                            agreementIds.push(item.value);
                        });

                        if (payload.paymentMethod.extension_attributes === undefined) {
                            payload.paymentMethod.extension_attributes = {};
                        }

                        payload.paymentMethod.extension_attributes.agreement_ids = agreementIds;
                    }

                    /**
                     * Send the selected payment method, along with a cart identifier, the billing address and a 'skip
                     * validation' flag to the save payment method API.
                     */
                    storage.post(
                        url,
                        /**
                         * The APi expects a JSON object with the selected payment method and the selected billing address.
                         */
                        JSON.stringify(payload)
                    ).done(
                        function () {
                            /**
                             * Update the totals in the summary block.
                             *
                             * While the method is called 'getTotals', it will actually fetch the latest totals from
                             * Magento's API and then update the entire summary block.
                             *
                             * Please note that the empty array is required for this function. it may contain callbacks,
                             * however these MUST return true for the function to work as expected. otherwise it will
                             * silently crash.
                             */
                            getTotals([]);
                        }
                    ).error(
                        function () {
                            totals.isLoading(false);
                        }
                    );
                }
            }
        );
    }
);
