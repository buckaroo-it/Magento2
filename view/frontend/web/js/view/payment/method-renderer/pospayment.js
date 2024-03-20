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
        'buckaroo/checkout/payment/parent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'mage/storage',
        'mage/url',
        'mage/translate',
        'Magento_Ui/js/modal/alert',
        'buckaroo/checkout/common'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        storage,
        urlBuilder,
        $t,
        alert,
        checkoutCommon
    ) {
        'use strict';

        function checkOrderState(orderId, interval)
        {
            //console.log('==================31', orderId);
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
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_pospayment'
                },
                paymentFeeLabel : window.checkoutConfig.payment.buckaroo.pospayment.paymentFeeLabel,
                subtext : window.checkoutConfig.payment.buckaroo.pospayment.subtext,
                subTextStyle : checkoutCommon.getSubtextStyle('pospayment'),
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                isTestMode: window.checkoutConfig.payment.buckaroo.pospayment.isTestMode,

                /**
                 * @override
                 */
                initialize : function (options) {
                    if (checkoutData.getSelectedPaymentMethod() == options.index) {
                        window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                    }

                    return this._super(options);
                },

                /**
                 * Place order.
                 *
                 * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
                 * (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
                 */
                placeOrder: function (data, event) {
                    var self = this,
                        placeOrder;

                    if (event) {
                        event.preventDefault();
                    }

                    if (this.validate() && additionalValidators.validate()) {
                        this.isPlaceOrderActionAllowed(false);
                        placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                        $.when(placeOrder).fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(this.afterPlaceOrder.bind(this));
                        return true;
                    }
                    return false;
                },

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;
                    response = $.parseJSON(response);
                    checkoutCommon.redirectHandle(response);
                    if (typeof response.Order !== "undefined") {
                        alert({
                            title: $t('Follow the instructions on the payment terminal'),
                            content: $t('Your order will be completed as soon as payment has been made'),
                            actions: {always: function (){} }/*,
                            buttons: [{
                                text: $t(333),
                                class: 'action primary accept',
                                click: function () {
                                    this.closeModal(true);
                                }
                            }]*/
                        });
                        var interval = setInterval(function () {
                            checkOrderState(response.Order, interval);
                        },3000);
                    }
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.pospayment.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                }
            }
        );
    }
);
