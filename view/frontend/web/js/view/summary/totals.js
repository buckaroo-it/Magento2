/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define([
    'jquery',
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'ko'
],
function ($, Component, quote, totals , globalMessageList, $t, alert, ko) {
    'use strict';

    return Component.extend({
        defaults : {
            template : 'Buckaroo_Magento2/summary/buckaroo_fee'
        },
        totals : quote.getTotals(),

        /**
         * @override
         */
        initialize : function (options) {
            this._super();

            quote.paymentMethod.subscribe(this.updateFeeVisibility.bind(this));

            return this;
        },

        /**
         * Update visibility of the Buckaroo fee based on payment method.
         */
        updateFeeVisibility: function (paymentMethod) {
            if (!this.hasFee(paymentMethod)) {
                // Clear the fee segment if the selected payment method has no fee
                totals.getSegment('buckaroo_fee', null);
            }
            totals.isLoading(true); // Force totals to recalculate
        },

        /**
         * Determine if the selected payment method includes a fee.
         */
        hasFee: function (paymentMethod) {
            return paymentMethod && paymentMethod.method && paymentMethod.method.includes('buckaroo');
        },

        /**
         * Get buckaroo fee price based on options.
         *
         * @returns {int}
         */
        getValue : function () {
            var price = 0,
                buckarooFeeSegment;
            if (this.totals()
                && totals.getSegment('buckaroo_fee')
                && totals.getSegment('buckaroo_fee').hasOwnProperty('extension_attributes')
            ) {
                buckarooFeeSegment = totals.getSegment('buckaroo_fee')['extension_attributes'];

                price = buckarooFeeSegment.hasOwnProperty('buckaroo_fee') ?
                    buckarooFeeSegment['buckaroo_fee'] :
                    0;
            }

            return this.getFormattedPrice(price);
        },

        /**
         * Check buckaroo fee option availability.
         *
         * @returns {Boolean}
         */
        isAvailable : function () {
            var isAvailable = false;
            if (!this.isFullMode()) {
                return false;
            }

            if (this.totals()
                && totals.getSegment('buckaroo_fee')
                && totals.getSegment('buckaroo_fee').hasOwnProperty('extension_attributes')
            ) {
                isAvailable = (0 < totals.getSegment('buckaroo_fee')['extension_attributes'].buckaroo_fee);
            }

            return isAvailable;
        },

        getTitle: function () {
            return $t('Payment Fee');
        },

        /**
         * Title for 'Paid with Giftcard' option.
         *
         * @returns {string}
         */
        getAlreadyPayTitle: function () {
            return $t('Paid with Giftcard');
        },

        /**
         * Get value for 'Paid with Giftcard'.
         *
         * @returns {string|boolean}
         */
        getAlreadyPayValue : function () {
            var buckarooFeeSegment = totals.getSegment('buckaroo_already_paid');
            try {
                if (buckarooFeeSegment.title) {
                    var items = JSON.parse(buckarooFeeSegment.title);
                    if ((typeof items === 'object') && (items.length > 0)) {
                        for (var i = 0; i < items.length; i++) {
                            items[i].amount = this.getFormattedPrice(items[i].amount);
                        }
                        return items;
                    }
                }
            } catch (e) {
                // console.log(e);
            }

            return buckarooFeeSegment.value ?
                this.getFormattedPrice(buckarooFeeSegment.value) :
                false;
        },
    });
});
