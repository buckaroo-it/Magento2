define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals'
], function (Component, quote, priceUtils, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Buckaroo_Magento2/checkout/summary/already-paid'
        },
        isDisplayed: function () {
            return this.getAlreadyPaidTotal() > 0;
        },
        getValue: function () {
            var alreadyPaid = this.getAlreadyPaidTotal();
            return this.getFormattedPrice(alreadyPaid);
        },
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
        getFormattedPrice: function (price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        }
    });
});
