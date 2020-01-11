/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
define(
    [
        'jquery',
        'mageUtils',
        'mage/url',
        'mage/translate',
        'BuckarooPayconiqSDK'
    ],
    function(
        $,
        utils,
        url
    ) {
        'use strict';

        return {
            transactionKey : null,

            setTransactionKey: function(newKey) {
                this.transactionKey = newKey;
            },

            showQrCode: function() {
                BuckarooSdk.Payconiq.initiate(
                    "#tig_buckaroo_payconiq_qr",
                    this.transactionKey,
                    function(status, params) {
                        if (status === 'SUCCESS') {
                            $('#tig_buckaroo_payconiq_cancel').hide();
                        }

                        return true;
                    }
                );
            },

            cancelPayment: function() {
                var cancelText = $.mage.__('You have canceled the order. We kindly ask you to not complete the payment in the Payconiq app - Your order will not be processed. Place the order again if you still want to make the payment.');
                $('#tig_buckaroo_payconiq_qr').html(cancelText);

                var data = {};
                data['transaction_key'] = this.transactionKey;

                var formKey = $.mage.cookies.get('form_key');

                utils.submit({
                    url: url.build('/buckaroo/payconiq/process/?form_key=' + formKey),
                    data: data
                });
            }
        };
    }
);
