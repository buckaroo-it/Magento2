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
            console.log('Reload shopping cart on success');
        }
    });
}); 