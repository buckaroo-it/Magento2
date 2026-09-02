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
        'buckaroo/checkout/payment/default',
    ],
    function (
        Component,
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_creditcard',
                },
                redirectAfterPlaceOrder: false,
                selectedCard: null,
                cardSelectionError: null,

                initObservable: function () {
                    this._super().observe(['selectedCard', 'cardSelectionError']);
                    return this;
                },

                validateField: function (data, event) {
                    if (event && event.target && event.target.tagName === 'SELECT') {
                        this.selectedCard(event.target.value);
                    }

                    if (this.hasSelectedCard()) {
                        this.cardSelectionError('');
                    }

                    return true;
                },

                hasSelectedCard: function () {
                    return !!this.selectedCard();
                },

                validate: function () {
                    if (!this._super()) {
                        return false;
                    }

                    if (!this.hasSelectedCard()) {
                        this.cardSelectionError($.mage.__('Please select a credit card or debit card brand/issuer.'));
                        return false;
                    }

                    this.cardSelectionError('');
                    return true;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "card_type": this.selectedCard()
                        }
                    };
                },
            }
        );
    }
);








