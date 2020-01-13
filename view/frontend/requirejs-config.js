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
var config = {
    map: {
        '*': {
            "BuckarooSDK": "//checkout.buckaroo.nl/api/buckaroosdk/script",
            "buckaroo/applepay/catalog-product-view": "Buckaroo_Magento2/js/view/checkout/applepay/catalog-product-view",
            "buckaroo/applepay/checkout-cart": "Buckaroo_Magento2/js/view/checkout/applepay/checkout-cart",
            "buckaroo/applepay/pay": "Buckaroo_Magento2/js/view/checkout/applepay/pay",
            "buckaroo/applepay/billing-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/billing-handler",
            "buckaroo/applepay/shipping-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/shipping-handler",
            "buckaroo/applepay/order-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/order-handler",
            "buckaroo/payconiq/pay": "Buckaroo_Magento2/js/view/checkout/payconiq/pay",
            "BuckarooClientSideEncryption": "//static.buckaroo.nl/script/ClientSideEncryption001.js"
        }
    },
    shim: {
        'BuckarooSDK': {
            deps: ['jquery']
        }
    }
};
