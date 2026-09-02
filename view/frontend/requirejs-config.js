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
var config = {
    config: {
        mixins: {
            'Buckaroo_Magento2/js/action/place-order': {
                'Buckaroo_Magento2/js/action/amasty-place-order-mixin': true
            },
        },
    },

    map: {
        '*': {
            "BuckarooSdk": "Buckaroo_Magento2/js/lib/buckaroo-sdk",
            "buckaroo/applepay/catalog-cart-view": "Buckaroo_Magento2/js/view/checkout/applepay/catalog-cart-view",
            "buckaroo/applepay/catalog-product-view": "Buckaroo_Magento2/js/view/checkout/applepay/catalog-product-view",
            "buckaroo/applepay/pay": "Buckaroo_Magento2/js/view/checkout/applepay/pay",
            "buckaroo/applepay/billing-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/billing-handler",
            "buckaroo/applepay/shipping-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/shipping-handler",
            "buckaroo/applepay/order-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/order-handler",
            "buckaroo/googlepay/catalog-cart-view": "Buckaroo_Magento2/js/view/checkout/googlepay/catalog-cart-view",
            "buckaroo/googlepay/catalog-product-view": "Buckaroo_Magento2/js/view/checkout/googlepay/catalog-product-view",
            "buckaroo/googlepay/pay": "Buckaroo_Magento2/js/view/checkout/googlepay/pay",
            "buckaroo/googlepay/handlers/order-handler": "Buckaroo_Magento2/js/view/checkout/googlepay/handlers/order-handler",
            "buckaroo/mrcash/pay": "Buckaroo_Magento2/js/view/checkout/mrcash/pay",
            "BuckarooClientSideEncryption": "//static.buckaroo.nl/script/ClientSideEncryption001.js",
            "BuckarooHostedFieldsSdkAlpha": "//hostedfields-externalapi.alpha.buckaroo.aws/v1/sdk",
            "BuckarooHostedFieldsSdk": "//hostedfields-externalapi.prod-pci.buckaroo.io/v1/sdk",
            "buckaroo/checkout/common": "Buckaroo_Magento2/js/view/checkout/common",
            "buckaroo/checkout/datepicker": "Buckaroo_Magento2/js/view/checkout/datepicker",
            "buckaroo/checkout/datepicker-enhanced": "Buckaroo_Magento2/js/view/checkout/datepicker-enhanced",
            "buckaroo/checkout/creditcards/error-gate": "Buckaroo_Magento2/js/view/checkout/creditcards/hosted-fields-error-gate",
            "buckaroo/paypal-express/pay": "Buckaroo_Magento2/js/view/checkout/paypal-express/pay",
            "buckaroo/paypal-express/button": "Buckaroo_Magento2/js/view/checkout/paypal-express/button",
            "buckaroo/ideal-fast-checkout/pay": "Buckaroo_Magento2/js/view/checkout/ideal-fast-checkout/pay",
            "buckaroo/checkout/payment/default": "Buckaroo_Magento2/js/view/payment/method-renderer/default"
        }
    },

    shim: {
        'BuckarooSdk': {
            deps: ['jquery'],
            exports: 'BuckarooSdk'
        }
    }
};
