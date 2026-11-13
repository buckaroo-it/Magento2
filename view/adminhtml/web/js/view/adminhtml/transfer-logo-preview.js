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
