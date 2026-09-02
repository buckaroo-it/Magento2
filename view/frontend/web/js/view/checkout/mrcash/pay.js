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
define(
    [
        'jquery',
        'mageUtils',
        'mage/url',
        'mage/translate',
        'BuckarooSdk'
    ],
    function (
        $,
        utils,
        url
    ) {
        'use strict';

        return {
            transactionKey : null,

            setTransactionKey: function (newKey) {
                this.transactionKey = newKey;
            },

            showQrCode: function () {
                if (/iPhone|iPad|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    $('#buckaroo_magento2_mrcash_url').show();
                }
                BuckarooSdk.BancontactMobile.initiateSeparate(
                    "#buckaroo_magento2_mrcash_qr",
                    "#buckaroo_magento2_mrcash_url",
                    this.transactionKey,
                    function (status, params) {
                        if (status === 'SUCCESS') {
                            $('#buckaroo_magento2_mrcash_cancel').hide();
                        }

                        return true;
                    }
                );
            },

            cancelPayment: function () {
                var cancelText = $.mage.__('You have canceled the order. We kindly ask you to not complete the payment in the Bancontact app - Your order will not be processed. Place the order again if you still want to make the payment.');
                $('#buckaroo_magento2_mrcash_title').hide();
                $('#buckaroo_magento2_mrcash_cancel').hide();
                $('#buckaroo_magento2_mrcash_qr').html(cancelText);

                var data = {};
                data['transaction_key'] = this.transactionKey;

                var formKey = $.mage.cookies.get('form_key');

                utils.submit({
                    url: url.build('/buckaroo/mrcash/process/?cancel=1&form_key=' + formKey),
                    data: data
                });
            }
        };
    }
);
