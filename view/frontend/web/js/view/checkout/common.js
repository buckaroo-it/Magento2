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
    ],
    function (
    ) {
        'use strict';

        return {

            /**
             * Current storefront form key, sent with POSTs to Buckaroo endpoints that check it.
             *
             * Uses the form_key cookie first, because Magento copies it into the session on
             * every request, then falls back to the checkout config value.
             *
             * @returns {string}
             */
            getFormKey: function () {
                var match = document.cookie.match(/(?:^|; )form_key=([^;]*)/);

                if (match) {
                    return decodeURIComponent(match[1]);
                }

                return (window.checkoutConfig && window.checkoutConfig.formKey) || '';
            },

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
            }
        };
    }
);
