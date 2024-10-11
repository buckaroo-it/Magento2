/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */

define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals'
], function (Component, quote, priceUtils, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            isFullTaxSummaryDisplayed: window.checkoutConfig.isFullTaxSummaryDisplayed || false,
            template: 'Buckaroo_Magento2/checkout/summary/grand-total'
        },
        totals: quote.getTotals(),
        isTaxDisplayedInGrandTotal: window.checkoutConfig.includeTaxInGrandTotal || false,

        /**
         * @return {*}
         */
        isDisplayed: function () {
            return this.isFullMode();
        },

        /**
         * @return {*|String}
         */
        getValue: function () {
            let grandTotal = this._getGrandTotalValue();
            const alreadyPaid = this.getAlreadyPaidTotal();

            if (!isNaN(alreadyPaid)) {
                grandTotal -= alreadyPaid;
            }

            return this.getFormattedPrice(grandTotal);
        },

        /**
         * @return {*|String}
         */
        getBaseValue: function () {
            var price = 0;

            if (this.totals()) {
                price = this.totals()['base_grand_total'];
            }

            return priceUtils.formatPriceLocale(price, quote.getBasePriceFormat());
        },

        /**
         * @return {*}
         */
        getGrandTotalExclTax: function () {
            var total = this.totals(),
                amount;

            if (!total) {
                return this.getFormattedPrice(0);
            }

            amount = total['grand_total'] - total['tax_amount'] - this.getAlreadyPaidTotal();

            if (amount < 0) {
                amount = 0;
            }

            return this.getFormattedPrice(amount);
        },

        /**
         * @return {Boolean}
         */
        isBaseGrandTotalDisplayNeeded: function () {
            const total = this.totals();
            if (!total) {
                return false;
            }

            return total['base_currency_code'] !== total['quote_currency_code'];
        },

        /**
         * Retrieve the already paid total from the totals segments
         *
         * @returns {Number}
         */
        getAlreadyPaidTotal: function () {
            var buckarooFeeSegment = totals.getSegment('buckaroo_already_paid');
            try {
                if (buckarooFeeSegment.title) {
                    var items = JSON.parse(buckarooFeeSegment.title);
                    var total = 0;
                    if ((typeof items === 'object') && (items.length > 0)) {
                        for (var i = 0; i < items.length; i++) {
                            total = parseFloat(total) + parseFloat(items[i].serviceamount);
                        }
                        return parseFloat(total).toFixed(2);
                    }
                }
            } catch (e) {
            }

            return parseFloat(buckarooFeeSegment.value).toFixed(2);
        },

        /**
         * Helper method to retrieve the grand total value
         *
         * @returns {Number}
         */
        _getGrandTotalValue: function () {
            if (this.totals()) {
                return parseFloat(totals.getSegment('grand_total').value) || 0;
            }
            return 0;
        }
    });
});
