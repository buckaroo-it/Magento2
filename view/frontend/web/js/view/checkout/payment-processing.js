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
/**
 * Full-page "payment is being processed" overlay, shown from the moment an express payment starts
 * until the shopper is sent on. The order request can take a while when Buckaroo answers late; without
 * it the page looks frozen.
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    var OVERLAY_ID = 'buckaroo-payment-processing';

    // Coming back with the browser's back button restores the page from cache, overlay included
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            $('#' + OVERLAY_ID).remove();
        }
    });

    return {
        /**
         * Show the overlay (once)
         */
        show: function () {
            if (document.getElementById(OVERLAY_ID)) {
                return;
            }

            var overlay = $('<div></div>', {
                id: OVERLAY_ID,
                role: 'status',
                'aria-live': 'polite',
                css: {
                    position: 'fixed',
                    top: 0,
                    right: 0,
                    bottom: 0,
                    left: 0,
                    zIndex: 100000,
                    background: 'rgba(255, 255, 255, 0.85)',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    textAlign: 'center',
                    padding: '20px'
                }
            });

            var spinner = $('<div></div>', {
                css: {
                    width: '48px',
                    height: '48px',
                    border: '4px solid #ddd',
                    borderTopColor: '#333',
                    borderRadius: '50%',
                    marginBottom: '16px',
                    animation: 'buckaroo-payment-processing-spin 0.9s linear infinite'
                }
            });

            if (!document.getElementById(OVERLAY_ID + '-style')) {
                $('<style></style>', {
                    id: OVERLAY_ID + '-style',
                    text: '@keyframes buckaroo-payment-processing-spin { to { transform: rotate(360deg); } }'
                }).appendTo('head');
            }

            overlay
                .append(spinner)
                .append($('<strong></strong>', {text: $t('Your payment is being processed.'), css: {fontSize: '18px'}}))
                .append($('<span></span>', {text: $t('Please do not close or refresh this page.'), css: {marginTop: '6px'}}))
                .appendTo('body');
        },

        /**
         * Remove the overlay, for example when the order could not be placed
         */
        hide: function () {
            $('#' + OVERLAY_ID).remove();
        }
    };
});
