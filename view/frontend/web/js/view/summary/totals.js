/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'TIG_Buckaroo/js/model/buckaroo-fee',
        'ko'
    ],
    function (Component, quote, totals, BuckarooFee, ko) {
        'use strict';

        return Component.extend(
            {
                defaults            : {
                    template : 'TIG_Buckaroo/summary/buckaroo_fee'
                },
                totals              : quote.getTotals(),
                model               : {},
                excludingTaxMessage : '(Excluding Tax)',
                includingTaxMessage : '(Including Tax)',

                /**
                 * @override
                 */
                initialize : function (options) {
                    this.model = new BuckarooFee();

                    window.checkoutConfig.buckarooFee.title = ko.observable(options.title);

                    return this._super(options);
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
                 * Get buckaroo fee price (including tax) based on options.
                 *
                 * @returns {int}
                 */
                getIncludingTaxValue : function () {
                    var price = 0,
                        buckarooFeeSegment;

                    if (this.totals()
                        && totals.getSegment('buckaroo_fee')
                        && totals.getSegment('buckaroo_fee').hasOwnProperty('extension_attributes')
                    ) {
                        buckarooFeeSegment = totals.getSegment('buckaroo_fee')['extension_attributes'];

                        price = buckarooFeeSegment.hasOwnProperty('buckaroo_fee_incl_tax') ?
                            buckarooFeeSegment['buckaroo_fee_incl_tax'] :
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

                /**
                 * Check if both buckaroo fee prices should be displayed.
                 *
                 * @returns {Boolean}
                 */
                displayBothPrices : function () {
                    return (true == this.model.displayBothPrices());
                },

                /**
                 * Check if buckaroo fee prices should be displayed including tax.
                 *
                 * @returns {Boolean}
                 */
                displayPriceInclTax : function () {
                    var displayPriceInclTax = this.model.displayInclTaxPrice();

                    return displayPriceInclTax && !this.displayBothPrices();
                },

                /**
                 * Check if buckaroo fee prices should be displayed excluding tax.
                 *
                 * @returns {Boolean}
                 */
                displayPriceExclTax : function () {
                    return !this.displayPriceInclTax() && !this.displayBothPrices();
                },

                getTitle : function () {
                    return window.checkoutConfig.buckarooFee.title();
                }
            }
        );
    }
);
