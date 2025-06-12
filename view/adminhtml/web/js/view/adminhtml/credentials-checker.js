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
    'mage/url'
], function (Component, $, urlBuilder) {
    'use strict';

    return Component.extend({

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.bindEvents();
            return this;
        },

        /**
         * Bind click events
         */
        bindEvents: function () {
            var self = this;
            $(this.element).click(function () {
                self.checkCredentials();
            });
        },

        /**
         * Check credentials via AJAX
         */
        checkCredentials: function () {
            var self = this;

            $.ajax({
                url: urlBuilder.build('/buckaroo/credentialschecker/index'),
                type: 'POST',
                dataType: 'json',
                showLoader: true,
                data: {
                    mode: $("#buckaroo_magento2_settings_magento2_account_section_active").val(),
                    secretKey: $("#buckaroo_magento2_settings_buckaroo_magento2_account_section_secret_key").val(),
                    merchantKey: $("#buckaroo_magento2_settings_buckaroo_magento2_account_section_merchant_key").val()
                }
            }).done(function (response) {
                if (response) {
                    if (response.success) {
                        self.showMessage('Your credentials have been verified successfully!');
                        return true;
                    } else {
                        if (response.error_message) {
                            self.showMessage(response.error_message, true);
                            return false;
                        }
                    }
                }
                self.showMessage('general error', true);
            });
        },

        /**
         * Show message
         * @param {string} text
         * @param {boolean} isError
         */
        showMessage: function (text, isError) {
            isError = isError || false;
            var msgEl = $('#buckaroo_magento2_credentials_checker_msg');
            if (msgEl.length) {
                msgEl.css('color', isError ? 'red' : 'green');
                msgEl.html(text);
            }
        }
    });
}); 