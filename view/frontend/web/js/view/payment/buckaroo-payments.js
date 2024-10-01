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
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/ideal',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_ideal}
            },
            {
                type: 'buckaroo_magento2_afterpay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/afterpay',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_afterpay}
            },
            {
                type: 'buckaroo_magento2_afterpay2',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/afterpay',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_afterpay2}
            },
            {
                type:  'buckaroo_magento2_afterpay20',
                component:  'Buckaroo_Magento2/js/view/payment/method-renderer/afterpay20',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_afterpay20}
            },
            {
                type:  'buckaroo_magento2_billink',
                component:  'Buckaroo_Magento2/js/view/payment/method-renderer/billink',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_billink}
            },
            {
                type: 'buckaroo_magento2_payperemail',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/payperemail',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_payperemail}
            },
            {
                type: 'buckaroo_magento2_sepadirectdebit',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/sepadirectdebit',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_sepadirectdebit}
            },
            {
                type: 'buckaroo_magento2_paypal',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_paypal}
            },
            {
                type: 'buckaroo_magento2_payconiq',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/payconiq',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_payconiq}
            },
            {
                type: 'buckaroo_magento2_creditcard',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/creditcard',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_creditcard}
            },
            {
                type: 'buckaroo_magento2_creditcards',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/creditcards',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_creditcards}
            },
            {
                type: 'buckaroo_magento2_transfer',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/transfer',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_transfer}
            },
            {
                type: 'buckaroo_magento2_mrcash',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/mrcash',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_mrcash}
            },
            {
                type: 'buckaroo_magento2_sofortbanking',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_sofortbanking}
            },
            {
                type: 'buckaroo_magento2_belfius',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_belfius}
            },
            {
                type: 'buckaroo_magento2_blik',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_blik}
            },
            {
                type: 'buckaroo_magento2_eps',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_eps}
            },
            {
                type: 'buckaroo_magento2_giftcards',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/giftcards',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_giftcards}
            },
            {
                type: 'buckaroo_magento2_kbc',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_kbc}
            },
            {
                type: 'buckaroo_magento2_klarna',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/klarna',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_klarna}
            },
            {
                type: 'buckaroo_magento2_klarnain',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/klarna',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_klarnain}
            },
            {
                type: 'buckaroo_magento2_klarnakp',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/klarnakp',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_klarnakp}
            },
            {
                type: 'buckaroo_magento2_applepay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/applepay',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_applepay}
            },
            {
                type: 'buckaroo_magento2_capayablein3',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/capayablein3',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_capayablein3}
            },
            {
                type: 'buckaroo_magento2_capayablepostpay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/capayablepostpay',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_capayablepostpay}
            },
            {
                type: 'buckaroo_magento2_alipay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_alipay}
            },
            {
                type: 'buckaroo_magento2_wechatpay',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_wechatpay}
            },
            {
                type: 'buckaroo_magento2_p24',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_p24}
            },
            {
                type: 'buckaroo_magento2_trustly',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_trustly}
            },
            {
                type: 'buckaroo_magento2_pospayment',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/pospayment',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_pospayment}
            },
            {
                type: 'buckaroo_magento2_voucher',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/voucher',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_voucher}
            },
            {
                type: 'buckaroo_magento2_paybybank',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/paybybank',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_paybybank}
            },
            {
                type: 'buckaroo_magento2_multibanco',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_multibanco}
            },
            {
                type: 'buckaroo_magento2_mbway',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_mbway}
            },
            {
                type: 'buckaroo_magento2_knaken',
                component: 'Buckaroo_Magento2/js/view/payment/method-renderer/default',
                config: {buckaroo: window.checkoutConfig.payment.buckaroo.buckaroo_magento2_knaken}
            }
        );
        /**
         * Add view logic here if needed
         */
        return Component.extend({});
    }
);
