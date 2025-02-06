define([
    'uiComponent',
    'Magento_Customer/js/customer-data'
], function (
    Component,
    customerData
) {
    'use strict';

    return Component.extend({
        /**
         * Automatically triggers once this component is initialized by Magento UI
         */
        initialize: function () {
            this._super();
            this.reloadCart();
            return this;
        },

        /**
         * Your original logic to reload the 'cart' section
         */
        reloadCart: function () {
            var sections = ['cart'];
            customerData.initStorage();
            customerData.reload(sections, true);
            customerData.invalidate(sections);
            console.log('Reload shopping cart on success');
        }
    });
});
