/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'ideal_new',
                component: 'Magento_SamplePaymentGateway/js/view/payment/method-renderer/ideal_new'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);