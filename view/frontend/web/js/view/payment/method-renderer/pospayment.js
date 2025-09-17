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
        'jquery',
        'buckaroo/checkout/payment/default',
        'mage/url',
        'mage/translate',
        'Magento_Ui/js/modal/alert',
        'buckaroo/checkout/common'
    ],
    function (
        $,
        Component,
        urlBuilder,
        $t,
        alert,
        checkoutCommon
    ) {
        'use strict';

        function checkOrderState(orderId, interval)
        {
            $.ajax({
                url: urlBuilder.build('buckaroo/pos/checkOrderStatus'),
                type: 'POST',
                dataType: 'json',
                //showLoader: true,
                data: {
                    orderId: orderId
                }
            }).done(function (response) {
                if (response.redirect) {
                    clearInterval(interval);
                    location.href = response.redirect;
                }
            });
        }

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/default'
                },

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;
                    response = $.parseJSON(response);
                    checkoutCommon.redirectHandle(response);
                    if (typeof response.Order !== "undefined") {
                        alert({
                            title: $t('Follow the instructions on the payment terminal'),
                            content: $t('Your order will be completed as soon as payment has been made'),
                            actions: {always: function (){} }
                        });
                        var interval = setInterval(function () {
                            checkOrderState(response.Order, interval);
                        },3000);
                    }
                },

            }
        );
    }
);
