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

                initObservable: function () {
                    this._super().observe(['selectedCard']);
                    return this;
                },

                validateField: function () {
                    var el = document.getElementById("buckaroo_magento2_creditcard_issuer");
                    this.selectedCard(el.options[el.selectedIndex].value);
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








