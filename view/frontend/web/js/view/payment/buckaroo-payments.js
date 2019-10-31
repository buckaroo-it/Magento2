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
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        ko,
        Component,
        rendererList
    ) {
        'use strict';

        ko.extenders.uppercase = function (target) {
            target.subscribe(
                function (newValue) {
                    target(newValue.toUpperCase());
                }
            );
            return target;
        };

        rendererList.push(
            {
                type: 'tig_buckaroo_ideal',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/ideal'
            },
            {
                type: 'tig_buckaroo_idealprocessing',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/idealprocessing'
            },
            {
                type: 'tig_buckaroo_afterpay',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/afterpay'
            },
            {
                type: 'tig_buckaroo_afterpay2',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/afterpay2'
            },
            {
              type:  'tig_buckaroo_afterpay20',
              component:  'TIG_Buckaroo/js/view/payment/method-renderer/afterpay20'
            },
            {
                type: 'tig_buckaroo_payperemail',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/payperemail'
            },
            {
                type: 'tig_buckaroo_sepadirectdebit',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/sepadirectdebit'
            },
            {
                type: 'tig_buckaroo_paypal',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/paypal'
            },
            {
                type: 'tig_buckaroo_payconiq',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/payconiq'
            },
            {
                type: 'tig_buckaroo_creditcard',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/creditcard'
            },
            {
                type: 'tig_buckaroo_creditcards',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/creditcards'
            },
            {
                type: 'tig_buckaroo_transfer',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/transfer'
            },
            {
                type: 'tig_buckaroo_giropay',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/giropay'
            },
            {
                type: 'tig_buckaroo_mrcash',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/mrcash'
            },
            {
                type: 'tig_buckaroo_sofortbanking',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/sofortbanking'
            },
            {
                type: 'tig_buckaroo_eps',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/eps'
            },
            {
                type: 'tig_buckaroo_giftcards',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/giftcards'
            },
            {
                type: 'tig_buckaroo_paymentguarantee',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/paymentguarantee'
            },
            {
                type: 'tig_buckaroo_kbc',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/kbc'
            },
            {
                type: 'tig_buckaroo_klarna',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/klarna'
            },
            {
                type: 'tig_buckaroo_emandate',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/emandate'
            },
            {
                type: 'tig_buckaroo_applepay',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/applepay'
            },
            {
                type: 'tig_buckaroo_capayablein3',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/capayablein3'
            },
            {
                type: 'tig_buckaroo_capayablepostpay',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/capayablepostpay'
            },
            {
                type: 'tig_buckaroo_alipay',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/alipay'
            },
            {
                type: 'tig_buckaroo_wechatpay',
                component: 'TIG_Buckaroo/js/view/payment/method-renderer/wechatpay'
            }

        );
        /**
         * Add view logic here if needed
         */
        return Component.extend({});
    }
);
