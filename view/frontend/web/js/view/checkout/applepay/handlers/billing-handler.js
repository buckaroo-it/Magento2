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
define(
    [
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/resource-url-manager',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/create-billing-address',
        'Magento_Checkout/js/action/select-billing-address',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data'
    ],
    function (
        $,
        urlBuilder,
        resourceUrlManager,
        quote,
        createBillingAddress,
        selectBillingAddress,
        selectPaymentMethodAction,
        checkoutData
    ) {
        'use strict';

        return {
            setBillingAddress: function (address) {
                var addressData = this.getAddressData(address);

                var newBillingAddress = createBillingAddress(addressData);
                selectBillingAddress(newBillingAddress);
                checkoutData.setSelectedBillingAddress(newBillingAddress.getKey());
                checkoutData.setNewCustomerBillingAddress($.extend(true, {}, addressData));

                return newBillingAddress;
            },

            selectPaymentMethod: function (paymentMethodData) {
                selectPaymentMethodAction(paymentMethodData);
                checkoutData.setSelectedPaymentMethod('buckaroo_magento2_applepay');

                return true;
            },

            savePaymentInfo: function () {
                var params = {};
                var payload = {};

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

                payload.paymentMethod = {
                    method: 'buckaroo_magento2_applepay'
                };
                payload.billingAddress = quote.billingAddress();
                payload.shippingAddress = quote.shippingAddress();

                $.ajax({
                    url: urlBuilder.build(url),
                    type: 'POST',
                    data: JSON.stringify(payload),
                    global: false,
                    contentType: 'application/json',
                    async: false
                }).done(function (result) {
                }.bind(this));
            },

            getAddressData: function (address) {
                var street = address.addressLines;

                if (street instanceof Array) {
                    street = street.join(' ');
                }

                var addressData = {
                    firstname: address.givenName,
                    lastname: address.familyName,
                    comapny: '',
                    street: [street],
                    city: address.locality,
                    postcode: address.postalCode,
                    region: address.administrativeArea,
                    region_id: 0,
                    country_id: address.countryCode,
                    telephone: address.phoneNumber,
                    email: address.emailAddress,
                    save_in_address_book: 0,
                };

                return addressData;
            },
        };
    }
);
