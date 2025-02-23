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
    'jquery',
    'mageUtils',
    'mage/url',
    'mage/translate',
    'BuckarooSDK'
], function (
    Component,
    $,
    utils,
    $url,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            transactionKey: null
        },

        /**
         * Initialize the component
         */
        initialize: function () {
            this._super();

            if (this.transactionKey) {
                this.setTransactionKey(this.transactionKey);
            }

            this.showQrCode();

            return this;
        },

        setTransactionKey: function (newKey) {
            this.transactionKey = newKey;
        },

        showQrCode: function () {
            if (!this.transactionKey) {
                return;
            }

            // Show the "Open in app" button if user is on mobile
            if (/iPhone|iPad|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                $('#buckaroo_magento2_mrcash_url').show();
            }

            // Initiate Bancontact QR
            BuckarooSdk.BancontactMobile.initiateSeparate(
                '#buckaroo_magento2_mrcash_qr',
                '#buckaroo_magento2_mrcash_url',
                this.transactionKey,
                function (status /*, params */) {
                    if (status === 'SUCCESS') {
                        $('#buckaroo_magento2_mrcash_cancel').hide();
                    }
                    return true;
                }
            );
        },

        cancelPayment: function () {
            var cancelText = $t(
                'You have canceled the order. We kindly ask you to not complete the payment in the Bancontact app - ' +
                'Your order will not be processed. Place the order again if you still want to make the payment.'
            );

            $('#buckaroo_magento2_mrcash_title').hide();
            $('#buckaroo_magento2_mrcash_cancel').hide();
            $('#buckaroo_magento2_mrcash_qr').html(cancelText);

            var data = {
                transaction_key: this.transactionKey
            };
            var formKey = $.mage.cookies.get('form_key');

            utils.submit({
                url: $url.build('/buckaroo/mrcash/process/?cancel=1&form_key=' + formKey),
                data: data
            });
        }
    });
});
