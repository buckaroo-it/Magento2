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
        'jquery-ui-modules/datepicker',
        'mage/translate'
    ],
    function ($) {
        'use strict';

        const translations = {
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
        };
        $.datepicker.setDefaults({
            dayNamesMin: translations.dayNamesMin,
            monthNamesShort: translations.monthNamesShort
        });

        return {
            addPickerClass(input, inst) {
                $(inst.dpDiv).addClass('bk-datepicker');
                
                // Fix positioning by ensuring the datepicker appears near the input field
                setTimeout(() => {
                    const $input = $(input);
                    const $datepicker = $(inst.dpDiv);
                    const offset = $input.offset();
                    
                    if (offset) {
                        $datepicker.css({
                            'position': 'absolute',
                            'z-index': '9999',
                            'top': offset.top + $input.outerHeight() + 'px',
                            'left': offset.left + 'px'
                        });
                    }
                }, 10);
            },

            removePickerClass(input, inst) {
                setTimeout(() => {
                    $(inst.dpDiv).removeClass('bk-datepicker');
                }, 300);
            }
        };
    }
);
