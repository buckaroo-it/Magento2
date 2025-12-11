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
            logoColor: '',
            logoColorElement: '',
            lightLogoUrl: '',
            darkLogoUrl: ''
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initLogoPreview();
            return this;
        },

        /**
         * Initialize logo preview functionality
         */
        initLogoPreview: function () {
            var self = this;

            // Get the color select element
            var colorElement = $('#' + this.logoColorElement);

            if (colorElement.length) {
                // Listen for changes to the color selection
                colorElement.on('change', function() {
                    self.updateLogoPreview($(this).val());
                });
            }
        },

        /**
         * Update the logo preview image
         * @param {string} color - The selected color value
         */
        updateLogoPreview: function(color) {
            var logoUrl = (color === 'Light') ? this.lightLogoUrl : this.darkLogoUrl;
            $('#ideal-button-preview-image').attr('src', logoUrl);
        }
    });
});

