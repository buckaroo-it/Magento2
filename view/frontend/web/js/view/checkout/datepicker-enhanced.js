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

        // Set default translations
        $.datepicker.setDefaults({
            dayNamesMin: translations.dayNamesMin,
            monthNamesShort: translations.monthNamesShort
        });

        // Enhanced datepicker with multiple format support
        const enhancedDatepicker = {
            // Custom parseDate function that supports both dd/mm/yyyy and dd-mm-yyyy
            parseDate: function(dateString, format) {
                if (!dateString) return null;
                
                // Remove any whitespace
                dateString = dateString.trim();
                
                // Try to parse dd/mm/yyyy format
                let match = dateString.match(/^(\d{1,2})[\/](\d{1,2})[\/](\d{4})$/);
                if (match) {
                    return new Date(parseInt(match[3]), parseInt(match[2]) - 1, parseInt(match[1]));
                }
                
                // Try to parse dd-mm-yyyy format
                match = dateString.match(/^(\d{1,2})[-](\d{1,2})[-](\d{4})$/);
                if (match) {
                    return new Date(parseInt(match[3]), parseInt(match[2]) - 1, parseInt(match[1]));
                }
                
                // Try to parse dd.mm.yyyy format (for completeness)
                match = dateString.match(/^(\d{1,2})[.](\d{1,2})[.](\d{4})$/);
                if (match) {
                    return new Date(parseInt(match[3]), parseInt(match[2]) - 1, parseInt(match[1]));
                }
                
                return null;
            },

            // Custom formatDate function that outputs in dd/mm/yyyy format
            formatDate: function(date, format) {
                if (!date) return '';
                
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                
                return day + '/' + month + '/' + year;
            },

            addPickerClass: function(input, inst) {
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

            removePickerClass: function(input, inst) {
                setTimeout(() => {
                    $(inst.dpDiv).removeClass('bk-datepicker');
                }, 300);
            },

            // Handle input events to allow both formats
            onInputChange: function(input) {
                const $input = $(input);
                const value = $input.val();
                
                // If user types a dash, convert it to slash for consistency
                if (value.includes('-')) {
                    const convertedValue = value.replace(/-/g, '/');
                    $input.val(convertedValue);
                }
            },

            // Get enhanced datepicker options
            getOptions: function() {
                return {
                    beforeShow: this.addPickerClass,
                    onClose: this.removePickerClass,
                    changeMonth: true,
                    changeYear: true,
                    yearRange: ((new Date()).getFullYear()-120) + ':' + (new Date()).getFullYear(),
                    dateFormat: 'dd/mm/yy',
                    // Allow more flexible input
                    constrainInput: false,
                    // Hide the datepicker trigger button and text
                    showOn: 'focus',
                    buttonImageOnly: false,
                    buttonText: '',
                    // Override the default parseDate function
                    beforeShowDay: function(date) {
                        // This is just a placeholder - the actual parsing is handled by our custom parseDate
                        return [true, ''];
                    },
                    // Allow both formats to be typed
                    onSelect: function(dateText, inst) {
                        // This will be handled by our custom parsing
                    }
                };
            }
        };

        // Override jQuery UI datepicker's parseDate function globally for our enhanced version
        const originalParseDate = $.datepicker.parseDate;
        $.datepicker.parseDate = function(format, value, settings) {
            // Try our enhanced parsing first
            const enhancedDate = enhancedDatepicker.parseDate(value, format);
            if (enhancedDate && !isNaN(enhancedDate.getTime())) {
                return enhancedDate;
            }
            
            // Fall back to original parsing
            try {
                return originalParseDate.call(this, format, value, settings);
            } catch (e) {
                // If original parsing fails, try our enhanced parsing again
                return enhancedDatepicker.parseDate(value, format);
            }
        };

        // Override jQuery UI datepicker's keydown handler to allow dashes
        const originalKeydown = $.datepicker._doKeyDown;
        $.datepicker._doKeyDown = function(event) {
            const input = event.target;
            const keyCode = event.keyCode;
            
            // Allow dash (keyCode 189) and hyphen (keyCode 109) to be typed
            if (keyCode === 189 || keyCode === 109) {
                // Allow the dash to be typed normally
                return true;
            }
            
            // For all other keys, use the original handler
            return originalKeydown.call(this, event);
        };

        // Add input event handler to allow both formats
        $(document).on('input', 'input[data-bind*="datepicker"]', function() {
            const $input = $(this);
            const value = $input.val();
            
            // If user types a dash, allow it but don't convert automatically
            // The parsing will handle both formats
            if (value.match(/^\d{1,2}[-]\d{1,2}[-]\d{4}$/)) {
                // Valid dash format, let it be
                return;
            }
        });

        return enhancedDatepicker;
    }
);
