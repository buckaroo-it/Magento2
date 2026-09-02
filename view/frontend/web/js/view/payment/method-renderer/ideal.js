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
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_ideal',
                },
                redirectAfterPlaceOrder: false,

                /**
                 * @override
                 */
                initObservable: function () {
                    this._super();
                    return this;
                },

                /**
                 * @override
                 * @returns {Object}
                 */
                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                        }
                    };
                },
            }
        );
    }
);
