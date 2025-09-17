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
        initialize: function (config) {
            this._super();

            // Set element from config if provided
            if (config && config.element) {
                this.element = $(config.element)[0];
            }

            this.bindEvents();
            return this;
        },

        /**
         * Bind click events
         */
        bindEvents: function () {
            var self = this;

            if (this.element) {
                $(this.element).off('click.buckaroo-checker').on('click.buckaroo-checker', function (e) {
                    e.preventDefault();
                    self.checkCredentials();
                });
            } else {
                // Fallback: try to find the button by ID
                setTimeout(function() {
                    var button = $('#buckaroo_magento2_credentials_checker_button');
                    if (button.length) {
                        self.element = button[0];
                        button.off('click.buckaroo-checker').on('click.buckaroo-checker', function (e) {
                            e.preventDefault();
                            self.checkCredentials();
                        });
                    }
                }, 500);
            }
        },

        /**
         * Check credentials via AJAX
         */
        checkCredentials: function () {
            var self = this;

            // Get form data
            var mode = $("#buckaroo_magento2_settings_magento2_account_section_active").val();
            var secretKey = $("#buckaroo_magento2_settings_buckaroo_magento2_account_section_secret_key").val();
            var merchantKey = $("#buckaroo_magento2_settings_buckaroo_magento2_account_section_merchant_key").val();

            // Validate required fields
            if (!secretKey || !merchantKey) {
                self.showMessage('Please fill in both Secret Key and Merchant Key before testing credentials.', true);
                return;
            }

            // Show loading message
            self.showMessage('Checking credentials...', false);

            $.ajax({
                url: urlBuilder.build('/buckaroo/credentialschecker/index'),
                type: 'POST',
                dataType: 'json',
                showLoader: true,
                timeout: 30000,
                data: {
                    mode: mode,
                    secretKey: secretKey,
                    merchantKey: merchantKey,
                    form_key: window.FORM_KEY || $('input[name="form_key"]').val()
                }
            }).done(function (response) {
                if (response && response.success) {
                    self.showMessage('✓ Your credentials have been verified successfully!', false);
                } else {
                    var errorMsg = (response && response.error_message) ? response.error_message : 'Unknown error occurred during validation';
                    self.showMessage('✗ ' + errorMsg, true);
                }
            }).fail(function (jqXHR, textStatus) {
                var errorMessage = 'Network error: Unable to validate credentials.';

                if (jqXHR.status === 404) {
                    errorMessage = 'Error: Credentials validation endpoint not found.';
                } else if (jqXHR.status === 403) {
                    errorMessage = 'Error: Access denied. Please check admin permissions.';
                } else if (jqXHR.status === 500) {
                    errorMessage = 'Error: Server error occurred. Please check server logs.';
                } else if (textStatus === 'timeout') {
                    errorMessage = 'Error: Request timed out. Please try again.';
                }

                self.showMessage('✗ ' + errorMessage, true);
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
                msgEl.css({
                    'color': isError ? 'red' : 'green',
                    'font-weight': 'bold'
                }).html(text).show();

                // Auto-clear success messages after 5 seconds
                if (!isError) {
                    setTimeout(function() {
                        msgEl.fadeOut(500);
                    }, 5000);
                }
            } else {
                // Fallback to alert if message element is missing
                alert((isError ? 'Error: ' : 'Success: ') + text);
            }
        }
    });
});
