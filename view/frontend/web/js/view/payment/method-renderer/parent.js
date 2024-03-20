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
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'buckaroo/checkout/common'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/common'
                },
                currencyCode : window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode : window.checkoutConfig.quoteData.base_currency_code,
                config: null, 

                /**
                 * @override
                 */
                initialize : function (options) {
                    if (checkoutData.getSelectedPaymentMethod() == options.index) {
                        window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                    }

                    const init = this._super(options);

                    return init;
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
                        placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder, this.messageContainer);

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
                },

                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },

                payWithBaseCurrency: function () {
                    var allowedCurrencies = this.buckaroo.allowedCurrencies;

                    return allowedCurrencies.indexOf(this.currencyCode) < 0;
                },

                getPayWithBaseCurrencyText: function () {
                    var text = $.mage.__('The transaction will be processed using %s.');

                    return text.replace('%s', this.baseCurrencyCode);
                },

                validate: function () {
                    const form = $('.' + this.getCode() + ' .payment-method-second-col form');
                    if (form.length) {
                        return form.valid();
                    }
                    return true;
                },

                validateField(data, event) {
                    $(event.target).valid();
                },

                getSubtextStyle: function () {
                    let config = this.buckaroo;
                    if (config === undefined) {
                        return;
                    }
                    let subtextColor = config.subtext_color || '#757575';
                    let subtextStyle = config.subtext_style || 'regular';
    
                    let style = { color: subtextColor }
                    if (subtextStyle == 'bold') {
                        style.fontWeight = 'bold';
                    }
    
                    if (subtextStyle == 'italic') {
                        style.fontStyle = 'italic';
                    }
                    return style;
                }
                
            }
        );
    }
);
