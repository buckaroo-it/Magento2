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
    ],
    function (
    ) {
        'use strict';

        return {

            redirectHandle: function (response) {
                if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                    if (window.location.hash && (window.location.hash == '#payment')) {
                        window.history.pushState(
                            null,
                            null,
                            `${window.location.pathname}${window.location.hash}`
                        );
                    }
                    window.location.replace(response.RequiredAction.RedirectURL);
                }
            },
            getSubtextStyle: function(paymentCode) {
                let config = window.checkoutConfig.payment.buckaroo[paymentCode];
                console.log(config);
                if(config === undefined) {
                    return;
                }
                let subtextColor = config.subtext_color || '#757575';
                let subtextStyle = config.subtext_style || 'regular';

                let style = { color: subtextColor }
                if(subtextStyle == 'bold') {
                 style.fontWeight = 'bold';
                }

                if(subtextStyle == 'italic') {
                 style.fontStyle = 'italic';
                }
                return style;
             }

        };
    }
);
