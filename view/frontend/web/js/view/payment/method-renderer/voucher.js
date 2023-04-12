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
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Ui/js/modal/alert',
        'mage/url',
        'mage/translate'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        alert,
        url,
        $t
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_voucher',
                    code: '',
                    isFormValid: false
                },
                paymentFeeLabel: window.checkoutConfig.payment.buckaroo.voucher.paymentFeeLabel,
                currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
                baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,


                /**
                 * @override
                 */
                initialize: function (options) {
                    if (checkoutData.getSelectedPaymentMethod() == options.index) {
                        window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);
                    }
                    return this._super(options);
                },

                initObservable: function () {
                    this._super().observe(['code', 'isFormValid']);

                    this.code.subscribe(function (code) {
                        this.isFormValid($('.' + this.getCode() + ' .payment [data-validate]').valid());
                    }.bind(this))
                    return this;
                },

                applyVoucher: function () {
                    if (this.isFormValid()) {
                        const voucherCode = this.code();
                        let self = this;
                        $.ajax({
                            url: url.build(`rest/default/V1/buckaroo/voucher/apply`),
                            type: 'POST',
                            dataType: 'json',
                            showLoader: true, //use for display loader 
                            data: { voucherCode: voucherCode }
                        }).done(function (data) {
                            self.code(null);
                            if (data.remainder_amount == 0) {
                                self.placeOrder(null, null);
                            }


                            if (data.error) {
                                self.displayErrorModal(self, data.error);
                            } else {
                                if (data.remainder_amount != 0) {
                                    alert({
                                        title: $t('Success'),
                                        content: $t(data.message),
                                        actions: { always: function () { } },
                                        buttons: [{
                                            text: $t(data.remaining_amount_message),
                                            class: 'action primary accept',
                                            click: function () {
                                                this.closeModal(true);
                                            }
                                        }]
                                    });
                                }
                                self.messageContainer.addSuccessMessage({ 'message': $t(data.message) });
                            }
                        }).error((err) => {
                            if (err.responseJSON && err.responseJSON.message) {
                                self.displayErrorModal(self, err.responseJSON.message);
                            }
                        });
                    }
                },

                displayErrorModal: function (self, message) {
                    alert({
                        title: $t('Error'),
                        content: $t(message),
                        actions: { always: function () { } }
                    });
                    self.messageContainer.addErrorMessage({ 'message': $t(message) });
                },
                selectPaymentMethod: function () {
                    window.checkoutConfig.buckarooFee.title(this.paymentFeeLabel);

                    selectPaymentMethodAction(this.getData());
                    checkoutData.setSelectedPaymentMethod(this.item.method);
                    return true;
                },


                payWithBaseCurrency: function () {
                    var allowedCurrencies = window.checkoutConfig.payment.buckaroo.voucher.allowedCurrencies;

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








