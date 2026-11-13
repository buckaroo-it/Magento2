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
         * Whether the checkout totals show the order as fully paid with at least one voucher among
         * the partial payments. A cart paid by giftcards alone is not a voucher payment.
         */
        function isCoveredWithVoucherByTotals()
        {
            const alreadyPaid = totals.getSegment('buckaroo_already_paid');
            const remaining = totals.getSegment('remaining_amount');
            if (!alreadyPaid || !(parseFloat(alreadyPaid.value) > 0) || !remaining) {
                return false;
            }

            let payments = [];
            try {
                payments = JSON.parse(alreadyPaid.title);
            } catch (e) {
                return false;
            }

            const hasVoucher = Array.isArray(payments) && payments.some(function (payment) {
                return payment && payment.servicecode === 'buckaroovoucher';
            });

            return hasVoucher && (parseFloat(remaining.value) || 0) < 0.005;
        }

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_voucher',
                    code: '',
                    isSubmitting: false,
                    coveredByApply: false
                },

                initObservable: function () {
                    this._super().observe(['code', 'isSubmitting', 'coveredByApply']);

                    /**
                     * Vouchers already cover the whole order: there is nothing left to apply and
                     * the order only has to be placed. Read from the checkout totals. Right after a
                     * voucher covered the rest, the apply response says so until fresh totals arrive.
                     */
                    this.isFullyPaid = ko.pureComputed(function () {
                        totals.totals();

                        return this.coveredByApply() === true || isCoveredWithVoucherByTotals();
                    }, this);

                    // Placing the order failed (e.g. the total changed): let fresh totals decide again
                    this.isPlaceOrderActionAllowed.subscribe(function (allowed) {
                        if (allowed && this.coveredByApply()) {
                            this.coveredByApply(false);
                            this.refreshTotals();
                        }
                    }, this);

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
                        if (!(parseFloat(data.remainder_amount) > 0)) {
                            // Fully paid: placing the order must not depend on the totals reload
                            this.coveredByApply(true);
                        }
                        const totalsReload = this.refreshTotals();
                        totalsReload.always(() => {
                            if (totalsReload.state() === 'resolved') {
                                // Fresh totals decide from here on
                                this.coveredByApply(false);
                            }
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
