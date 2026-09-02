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
                    var response = window.checkoutConfig.payment.buckaroo.responseData;
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
