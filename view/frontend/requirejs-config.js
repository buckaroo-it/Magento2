/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
var config = {
    map: {
        '*': {
            "BuckarooSDK": "//checkout.buckaroo.nl/api/buckaroosdk/script",
            "buckaroo/applepay/catalog-product-view": "TIG_Buckaroo/js/view/checkout/applepay/catalog-product-view",
            "buckaroo/applepay/checkout-cart": "TIG_Buckaroo/js/view/checkout/applepay/checkout-cart",
            "buckaroo/applepay/pay": "TIG_Buckaroo/js/view/checkout/applepay/pay",
            "buckaroo/applepay/billing-handler": "TIG_Buckaroo/js/view/checkout/applepay/handlers/billing-handler",
            "buckaroo/applepay/shipping-handler": "TIG_Buckaroo/js/view/checkout/applepay/handlers/shipping-handler",
            "buckaroo/applepay/order-handler": "TIG_Buckaroo/js/view/checkout/applepay/handlers/order-handler",
            "buckaroo/payconiq/pay": "TIG_Buckaroo/js/view/checkout/payconiq/pay",
            "BuckarooClientSideEncryption": "//static.buckaroo.nl/script/ClientSideEncryption001.js"
        }
    },
    shim: {
        'BuckarooSDK': {
            deps: ['jquery']
        }
    }
};
