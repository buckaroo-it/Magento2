define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals'
], function (Component, quote, priceUtils, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Buckaroo_Magento2/checkout/summary/remaining-amount'
        },
        isDisplayed: function () {
            return this.getRemainingAmount() > 0;
        },
        getValue: function () {
            return this.getFormattedPrice(this.getRemainingAmount());
        },
        getRemainingAmount: function () {
            var remainingAmount = 0;
            if (totals.getSegment('remaining_amount')) {
                remainingAmount = totals.getSegment('remaining_amount').value;
            }
            return parseFloat(remainingAmount) || 0;
        },
        getAlreadyPaidTotal: function () {
            var remainingAmount = 0;
            if (totals.getSegment('buckaroo_already_paid')) {
                remainingAmount = totals.getSegment('buckaroo_already_paid').value;
            }
            return remainingAmount;
        },
        getTitle: function () {
            return this.title;
        }
    });
});
