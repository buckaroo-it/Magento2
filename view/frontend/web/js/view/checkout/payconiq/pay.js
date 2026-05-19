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
    url,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            transactionKey: null
        },

        /**
         * Component initialization
         */
        initialize: function () {
            this._super();
            // If we have a transaction key already passed in, set it
            if (this.transactionKey) {
                this.setTransactionKey(this.transactionKey);
            }
            // Optionally, show the QR code automatically right away
            // or call this from a Knockout binding, your choice
            this.showQrCode();
            return this;
        },

        /**
         * Set the transaction key
         */
        setTransactionKey: function (newKey) {
            this.transactionKey = newKey;
        },

        /**
         * Show the QR code in the #buckaroo_magento2_payconiq_qr element
         */
        showQrCode: function () {
            // Safeguard if there's no transaction key yet
            if (!this.transactionKey) {
                return;
            }

            BuckarooSdk.Payconiq.initiate(
                '#buckaroo_magento2_payconiq_qr',
                this.transactionKey,
                function (status, params) {
                    if (status === 'SUCCESS') {
                        $('#buckaroo_magento2_payconiq_cancel').hide();
                    }
                    return true;
                }
            );
        },

        /**
         * Cancel the payment
         */
        cancelPayment: function () {
            var cancelText = $t(
                'You have canceled the order. We kindly ask you to not complete the payment in the Payconiq app - ' +
                'Your order will not be processed. Place the order again if you still want to make the payment.'
            );
            $('#buckaroo_magento2_payconiq_qr').html(cancelText);

            var data = { transaction_key: this.transactionKey };
            var formKey = $.mage.cookies.get('form_key');

            utils.submit({
                url: url.build('/buckaroo/payconiq/process/?cancel=1&form_key=' + formKey),
                data: data
            });
        }
    });
});
