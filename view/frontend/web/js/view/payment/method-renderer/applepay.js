define([
    'Magento_Checkout/js/view/payment/default',
    'Buckaroo_Magento2/js/view/payment/method-renderer/applepay-client',
    'Buckaroo_Magento2/js/view/payment/method-renderer/applepay-redirect',
    'Magento_Checkout/js/model/quote'
], function (Component, ClientComponent, RedirectComponent, quote) {
    'use strict';

    var mode = window.checkoutConfig.payment.buckaroo.applepay.integrationMode;

    // Extend and return the appropriate renderer dynamically
    return mode ? RedirectComponent : ClientComponent;
});
