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
    config: {
        mixins: {
            'Buckaroo_Magento2/js/action/place-order': {
                'Buckaroo_Magento2/js/action/amasty-place-order-mixin': true
            },
        },
    },
    map: {
        '*': {
            "BuckarooSDK": "//checkout.buckaroo.nl/api/buckaroosdk/script",
            // "BuckarooSDK": "//testcheckout.buckaroo.nl/api/buckaroosdk/script",
            "buckaroo/applepay/billing-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/billing-handler",
            "buckaroo/applepay/shipping-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/shipping-handler",
            "buckaroo/applepay/order-handler": "Buckaroo_Magento2/js/view/checkout/applepay/handlers/order-handler",
            "buckaroo/payconiq/pay": "Buckaroo_Magento2/js/view/checkout/payconiq/pay",
            "buckaroo/mrcash/pay": "Buckaroo_Magento2/js/view/checkout/mrcash/pay",
            "BuckarooClientSideEncryption": "//static.buckaroo.nl/script/ClientSideEncryption001.js",
            "BuckarooHostedFieldsSdkAlpha": "//hostedfields-externalapi.alpha.buckaroo.aws/v1/sdk",
            "BuckarooHostedFieldsSdk": "//hostedfields-externalapi.prod-pci.buckaroo.io/v1/sdk",
            "buckaroo/checkout/common": "Buckaroo_Magento2/js/view/checkout/common",
            "buckaroo/checkout/datepicker": "Buckaroo_Magento2/js/view/checkout/datepicker",
            "buckaroo/paypal-express/pay": "Buckaroo_Magento2/js/view/checkout/paypal-express/pay",
            "buckaroo/paypal-express/button": "Buckaroo_Magento2/js/view/checkout/paypal-express/button",
            "buckaroo/ideal-fast-checkout/pay": "Buckaroo_Magento2/js/view/checkout/ideal-fast-checkout/pay",
        }
    },
    shim: {
        'BuckarooSDK': {
            deps: ['jquery']
        }
    }
};
