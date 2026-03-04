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
    'jquery'
], function (Component, $) {
    'use strict';

    return Component.extend({
        defaults: {
            logoSelectId: '',
            genericLogoUrl: '',
            sepaLogoUrl: '',
            optionGeneric: 'generic_bank_logo',
            optionSepa: 'sepa_credit_transfer'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.bindEvents();
            return this;
        },

        /**
         * Bind change event on logo select to update preview image
         */
        bindEvents: function () {
            var self = this;
            var $select = $('#' + this.logoSelectId);

            if ($select.length) {
                $select.on('change', function () {
                    var value = $(this).val();
                    var src = value === self.optionSepa ? self.sepaLogoUrl : self.genericLogoUrl;
                    $('#transfer-logo-preview-img').attr('src', src);
                });
            }
        }
    });
});
