/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
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








