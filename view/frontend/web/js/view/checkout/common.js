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
