/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Buckaroo_Magento2/js/model/buckaroo-fee',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
        'Magento_Ui/js/modal/alert',
        'ko',
        'mage/url',
        'Magento_Checkout/js/model/cart/totals-processor/default'
    ],
    function ($, Component, quote, totals, BuckarooFee, globalMessageList, $t, alert, ko, url, totalsDefaultProvider) {
        'use strict';

        return Component.extend(
            {
                defaults            : {
                    template : 'Buckaroo_Magento2/summary/buckaroo_fee'
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
                },

                getAlreadyPayTitle : function () {
                    return 'Paid with Giftcard';
                },

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

                removeGiftcard: function (transaction_id,) {
                    console.log(transaction_id);
                    alert({
                        title: $t('Remove giftcard'),
                        content: $t('By clicking the ok button you remove the giftcard from the order. The amount gets automatically refunded to your giftcard'),
                        buttons: [{
                        text: $t('Ok'),
                        class: 'action primary accept',
                            click: function () {
                                let removeModal = this;
                                $.ajax({
                                    url: url.build("buckaroo/checkout/giftcardRemove"),
                                    type: 'POST',
                                    dataType: 'json',
                                    showLoader: true, //use for display loader 
                                    data: { transaction_id },
                                    success: function() {
                                        totalsDefaultProvider.estimateTotals(quote.shippingAddress());
                                        removeModal.closeModal(true);
                                    },
                                    error: function() {
                                        console.log(removeModal);
                                        removeModal.closeModal(true);
                                        alert({
                                            title: $t('Error'),
                                            content: $t('Cannot remove the giftcard code, refresh the page and try again'),
                                            class: 'action primary accept',
                                            text: $t('Close'),
                                            click: function () {
                                                this.closeModal(true);
                                            }
                                        });
                                    }
                                })
                            }
                        }]
                    });
                }

            }
        );
    }
);
