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
            var price = 0;

            if (this.totals()) {
                price = totals.getSegment('grand_total').value;

                if(!isNaN(parseFloat(this.getAlreadyPayTotal()))){
                    price = parseFloat(price) - parseFloat(this.getAlreadyPayTotal());
                }
            }

            return this.getFormattedPrice(price);
        },

        /**
         * @return {*|String}
         */
        getBaseValue: function () {
            var price = 0;

            if (this.totals()) {
                price = this.totals()['base_grand_total'];
            }

            return priceUtils.formatPrice(price, quote.getBasePriceFormat());
        },

        /**
         * @return {*}
         */
        getGrandTotalExclTax: function () {
            var total = this.totals();

            if (!total) {
                return 0;
            }

            return this.getFormattedPrice(total['grand_total']);
        },

        /**
         * @return {Boolean}
         */
        isBaseGrandTotalDisplayNeeded: function () {
            var total = this.totals();

            if (!total) {
                return false;
            }

            return total['base_currency_code'] != total['quote_currency_code']; //eslint-disable-line eqeqeq
        },

        getAlreadyPayTotal : function () {
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
                // console.log(e);
            }

            return parseFloat(buckarooFeeSegment.value).toFixed(2);
        }
    });
});
