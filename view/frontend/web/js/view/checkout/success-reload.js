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

define([
    'uiComponent',
    'Magento_Customer/js/customer-data'
], function (Component, customerData) {
    'use strict';

    return Component.extend({
        
        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.reloadCartData();
            return this;
        },

        /**
         * Reload cart data after successful payment
         */
        reloadCartData: function () {
            var sections = ['cart'];
            customerData.initStorage();
            customerData.reload(sections, true);
            customerData.invalidate(sections);
        }
    });
}); 