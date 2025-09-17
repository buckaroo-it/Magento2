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
        'Magento_Ui/js/modal/alert',
        'mage/url',
        'mage/translate',
        'Magento_Checkout/js/action/get-totals',
        'Magento_Customer/js/customer-data',
    ],
    function (
        $,
        Component,
        alert,
        url,
        $t,
        getTotalsAction,
        customerData,
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_voucher',
                    code: '',
                    isSubmitting: false
                },

                initObservable: function () {
                    this._super().observe(['code', 'isSubmitting']);

                    return this;
                },

                validateForm: function() {
                    return $('.' + this.getCode() + ' .payment [data-validate]').valid();
                },
                applyVoucher: function () {
                    if (this.validateForm()) {
                        this.isSubmitting(true);
                        const voucherCode = this.code();
                        let self = this;
                        $.ajax({
                            url: url.build(`rest/V1/buckaroo/voucher/apply`),
                            type: 'POST',
                            dataType: 'json',
                            showLoader: true, //use for display loader
                            data: {voucherCode: voucherCode}
                        }).done(function (data) {
                            self.code(null);
                            if (data.remainder_amount == 0) {
                                self.placeOrder(null, null);
                            }

                            this.isSubmitting(false);

                            // Ensure checkout summary totals are refreshed so custom totals appear immediately
                            var deferred = $.Deferred();
                            getTotalsAction([], deferred);
                            customerData.reload(['cart'], true);

                            if (data.error) {
                                self.displayErrorModal(self, data.error);
                            } else {
                                if (data.remainder_amount != 0) {
                                    alert({
                                        title: $t('Success'),
                                        content: $t(data.message),
                                        actions: {
                                            always: function () {
                                            }
                                        },
                                        buttons: [{
                                            text: $t(data.remaining_amount_message),
                                            class: 'action primary accept',
                                            click: function () {
                                                this.closeModal(true);
                                            }
                                        }]
                                    });
                                }
                                self.messageContainer.addSuccessMessage({'message': $t(data.message)});
                            }
                        }).fail((err) => {
                            this.isSubmitting(false);
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
                        actions: {
                            always: function () {
                            }
                        }
                    });
                    self.messageContainer.addErrorMessage({ 'message': $t(message) });
                }
            }
        );
    }
);
