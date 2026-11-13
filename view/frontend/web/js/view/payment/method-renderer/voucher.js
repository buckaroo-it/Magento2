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
        'ko',
        'buckaroo/checkout/payment/default',
        'Magento_Ui/js/modal/alert',
        'mage/translate',
        'Magento_Checkout/js/action/get-totals',
        'Magento_Checkout/js/model/totals',
        'Magento_Customer/js/customer-data',
        'buckaroo/rest-url-builder',
    ],
    function (
        $,
        ko,
        Component,
        alert,
        $t,
        getTotalsAction,
        totals,
        customerData,
        restUrlBuilder,
    ) {
        'use strict';

        /**
         * Amount still open once applied vouchers are taken off, from the checkout totals
         */
        function getRemainingAmount()
        {
            const alreadyPaid = totals.getSegment('buckaroo_already_paid');
            if (!alreadyPaid || !(parseFloat(alreadyPaid.value) > 0)) {
                return null;
            }

            const remaining = totals.getSegment('remaining_amount');

            return remaining ? parseFloat(remaining.value) || 0 : null;
        }

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_voucher',
                    code: '',
                    isSubmitting: false
                },

                initObservable: function () {
                    this._super().observe(['code', 'isSubmitting']);

                    /**
                     * Vouchers already cover the whole order: there is nothing left to apply and
                     * the order only has to be placed. Read from the totals, so it also holds
                     * after the shopper reloads the checkout.
                     */
                    this.isFullyPaid = ko.pureComputed(function () {
                        totals.totals();
                        const remaining = getRemainingAmount();

                        return remaining !== null && remaining < 0.005;
                    });

                    return this;
                },

                /**
                 * The voucher code field is only required while there is something left to pay
                 */
                validate: function () {
                    if (this.isFullyPaid()) {
                        return true;
                    }

                    return this._super();
                },

                validateForm: function () {
                    return $('.' + this.getCode() + ' .payment [data-validate]').valid();
                },

                applyVoucher: function () {
                    if (this.isSubmitting() || !this.validateForm()) {
                        return;
                    }

                    this.isSubmitting(true);

                    $.ajax({
                        url: restUrlBuilder.createUrl('/buckaroo/voucher/apply'),
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        showLoader: true,
                        data: JSON.stringify({voucherCode: this.code()})
                    }).done((data) => {
                        this.code('');
                        this.refreshTotals().always(() => {
                            this.isSubmitting(false);

                            if (parseFloat(data.remainder_amount) > 0) {
                                this.showPartialPayment(data);
                                return;
                            }

                            this.messageContainer.addSuccessMessage({message: data.message});
                            this.placeOrder(null, null);
                        });
                    }).fail((err) => {
                        this.isSubmitting(false);
                        this.displayErrorModal(
                            err.responseJSON && err.responseJSON.message
                                ? err.responseJSON.message
                                : $t('The voucher could not be redeemed. Please check the code and try again.')
                        );
                    });
                },

                /**
                 * Reload the checkout totals and the minicart, so the paid amount shows at once
                 *
                 * @returns {jQuery.Deferred}
                 */
                refreshTotals: function () {
                    const deferred = $.Deferred();

                    getTotalsAction([], deferred);
                    customerData.reload(['cart'], true);

                    return deferred;
                },

                showPartialPayment: function (data) {
                    alert({
                        title: $t('Success'),
                        content: data.message,
                        actions: {always: function () {}},
                        buttons: [{
                            text: data.remaining_amount_message,
                            class: 'action primary accept',
                            click: function () {
                                this.closeModal(true);
                            }
                        }]
                    });
                    this.messageContainer.addSuccessMessage({message: data.message});
                },

                displayErrorModal: function (message) {
                    alert({
                        title: $t('Error'),
                        content: message,
                        actions: {always: function () {}}
                    });
                    this.messageContainer.addErrorMessage({message: message});
                }
            }
        );
    }
);
