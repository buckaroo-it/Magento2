/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
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
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
            }
        );
        /**
         * Add view logic here if needed
         */
        return Component.extend({});
    }
);
