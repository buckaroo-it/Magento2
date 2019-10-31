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
define(
    [
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/resource-url-manager',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/create-shipping-address',
        'Magento_Checkout/js/action/select-shipping-address',
        'Magento_Checkout/js/action/select-shipping-method',
        'Magento_Checkout/js/action/select-billing-address',
        'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
        'Magento_Checkout/js/checkout-data'
    ],
    function (
        $,
        urlBuilder,
        resourceUrlManager,
        quote,
        createShippingAddress,
        selectShippingAddress,
        selectShippingMethod,
        selectBillingAddress,
        payloadExtender,
        checkoutData
    ) {
        'use strict';

        return {
            setShippingAddress: function (address) {
                var addressData = this.getAddressData(address);

                var newShippingAddress = createShippingAddress(addressData);
                selectShippingAddress(newShippingAddress);
                checkoutData.setSelectedShippingAddress(newShippingAddress.getKey());
                checkoutData.setNewCustomerShippingAddress($.extend(true, {}, addressData));

                return newShippingAddress;
            },

            selectShippingMethod: function (newMethod) {
                selectShippingMethod(newMethod);
                checkoutData.setSelectedShippingRate(newMethod['carrier_code'] + '_' + newMethod['method_code']);
            },

            saveShipmentInfo: function () {
                var payload;

                if (!quote.billingAddress()) {
                    selectBillingAddress(quote.shippingAddress());
                }

                payload = {
                    addressInformation: {
                        'shipping_address': quote.shippingAddress(),
                        'billing_address': quote.billingAddress(),
                        'shipping_method_code': quote.shippingMethod()['method_code'],
                        'shipping_carrier_code': quote.shippingMethod()['carrier_code']
                    }
                };

                payloadExtender(payload);

                var url = resourceUrlManager.getUrlForSetShippingInformation(quote);

                $.ajax({
                    url: urlBuilder.build(url),
                    type: 'POST',
                    data: JSON.stringify(payload),
                    global: false,
                    contentType: 'application/json',
                    async: false
                });
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
