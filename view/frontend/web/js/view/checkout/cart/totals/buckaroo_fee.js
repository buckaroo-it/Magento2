/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'Buckaroo_Magento2/js/view/summary/totals'
    ],
    function (Component) {
        "use strict";
        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/cart/totals/buckaroo_fee'
                },
                /**
             * @override
             *
             * @returns {boolean}
             */
                isDisplayed: function () {
                    return true;
                },

                /**
             * @override
             */
                isFullMode: function () {
                    return true;
                }
            }
        );
    }
);
