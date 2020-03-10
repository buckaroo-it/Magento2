/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [

    ],
    function () {
        "use strict";

        var buckarooFeeConfig = window.buckarooConfig ?
            window.buckarooConfig.buckarooFee :
            window.checkoutConfig.buckarooFee;

        return function (itemId) {
            return {
                itemId: itemId,

                /**
                 * @param key
                 * @returns {*}
                 */
                getConfigValue: function (key) {
                    return buckarooFeeConfig[key];
                },

                /**
                 * @returns {window.buckarooConfig.priceFormat|*|mage.configurable.options.priceFormat|.options.priceFormat|priceFormat}
                 */
                getPriceFormat: function () {
                    return window.buckarooConfig.priceFormat;
                },

                /**
                 * Get buckaroo fee price display mode.
                 *
                 * @returns {Boolean}
                 */
                displayBothPrices: function () {
                    return !!buckarooFeeConfig.cart.displayBuckarooFeeBothPrices;
                },

                /**
                 * Get buckaroo fee price display mode.
                 *
                 * @returns {Boolean}
                 */
                displayInclTaxPrice: function () {
                    return !!buckarooFeeConfig.cart.displayBuckarooFeeInclTax;
                }
            };
        };
    }
);
