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
        'jquery',
        'buckaroo/checkout/payment/default',
        'mageUtils',
        'mage/url',
    ],
    function (
        $,
        Component,
        utils,
        url,
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/default'
                },
                redirectAfterPlaceOrder: false,

                afterPlaceOrder: function () {
                    var response = window.checkoutConfig.payment.buckaroo.response;
                    if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        var formKey = $.mage.cookies.get('form_key');
                        window.history.pushState(
                            null,
                            null,
                            url.build('/buckaroo/payconiq/process/?cancel=1&form_key=' + formKey + '&transaction_key=' + response.Key)
                        );
                        var data = {};
                        data['transaction_key'] = response.key;

                        utils.submit({
                            url: this.buckaroo.redirecturl,
                            data: response
                        });
                    }
                },


            }
        );
    }
);
