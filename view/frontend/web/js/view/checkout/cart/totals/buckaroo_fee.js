/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'TIG_Buckaroo/js/view/summary/totals'
    ],
    function (Component) {
        "use strict";
        return Component.extend(
            {
                defaults: {
                    template: 'TIG_Buckaroo/cart/totals/buckaroo_fee'
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
