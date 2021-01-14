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
                type: 'buckaroo_magento2_ideal',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/ideal'
            },
            {
                type: 'buckaroo_magento2_idealprocessing',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/idealprocessing'
            },
            {
                type: 'buckaroo_magento2_afterpay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/afterpay'
            },
            {
                type: 'buckaroo_magento2_afterpay2',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/afterpay2'
            },
            {
              type:  'buckaroo_magento2_afterpay20',
              component:  'Buckaroo_Magento2/js/view/payment/method-renderer/afterpay20'
            },
            {
              type:  'buckaroo_magento2_billink',
              component:  'Buckaroo_Magento2/js/view/payment/method-renderer/billink'
            },
            {
                type: 'buckaroo_magento2_payperemail',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/payperemail'
            },
            {
                type: 'buckaroo_magento2_sepadirectdebit',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/sepadirectdebit'
            },
            {
                type: 'buckaroo_magento2_paypal',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/paypal'
            },
            {
                type: 'buckaroo_magento2_payconiq',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/payconiq'
            },
            {
                type: 'buckaroo_magento2_creditcard',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/creditcard'
            },
            {
                type: 'buckaroo_magento2_creditcards',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/creditcards'
            },
            {
                type: 'buckaroo_magento2_transfer',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/transfer'
            },
            {
                type: 'buckaroo_magento2_giropay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/giropay'
            },
            {
                type: 'buckaroo_magento2_mrcash',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/mrcash'
            },
            {
                type: 'buckaroo_magento2_sofortbanking',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/sofortbanking'
            },
            {
                type: 'buckaroo_magento2_eps',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/eps'
            },
            {
                type: 'buckaroo_magento2_giftcards',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/giftcards'
            },
            {
                type: 'buckaroo_magento2_paymentguarantee',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/paymentguarantee'
            },
            {
                type: 'buckaroo_magento2_kbc',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/kbc'
            },
            {
                type: 'buckaroo_magento2_klarnakp',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/klarnakp'
            },
            {
                type: 'buckaroo_magento2_emandate',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/emandate'
            },
            {
                type: 'buckaroo_magento2_applepay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/applepay'
            },
            {
                type: 'buckaroo_magento2_capayablein3',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/capayablein3'
            },
            {
                type: 'buckaroo_magento2_capayablepostpay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/capayablepostpay'
            },
            {
                type: 'buckaroo_magento2_alipay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/alipay'
            },
            {
                type: 'buckaroo_magento2_wechatpay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/wechatpay'
            },
            {
                type: 'buckaroo_magento2_p24',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/p24'
            },
            {
                type: 'buckaroo_magento2_trustly',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/trustly'
            },
            {
                type: 'buckaroo_magento2_rtp',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/rtp'
            },
            {
                type: 'buckaroo_magento2_pospayment',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/pospayment'
            },
            {
                type: 'buckaroo_magento2_tinka',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/tinka'
            }
        );
        /**
         * Add view logic here if needed
         */
        return Component.extend({});
    }
);
