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
define(
    [
        'jquery',
        'jquery/ui',
        'mage/translate'
    ],
    function ($) {
        'use strict';

        $.datepicker.setDefaults({
            dayNamesMin: [
                $.mage.__('Su'),
                $.mage.__('Mo'),
                $.mage.__('Tu'),
                $.mage.__('We'),
                $.mage.__('Th'),
                $.mage.__('Fr'),
                $.mage.__('Sa')
            ],
            monthNamesShort: [
                $.mage.__('Jan'),
                $.mage.__('Feb'),
                $.mage.__('Mar'),
                $.mage.__('Apr'),
                $.mage.__('May'),
                $.mage.__('Jun'),
                $.mage.__('Jul'),
                $.mage.__('Aug'),
                $.mage.__('Sep'),
                $.mage.__('Okt'),
                $.mage.__('Nov'),
                $.mage.__('Dec')
            ]
        });

        return {
            addPickerClass(input, inst) {
                $(inst.dpDiv).addClass('bk-datepicker');
            },

            removePickerClass(input, inst) {
                setTimeout(() => {
                    $(inst.dpDiv).removeClass('bk-datepicker');
                }, 300);
            }
        };
    }
);
