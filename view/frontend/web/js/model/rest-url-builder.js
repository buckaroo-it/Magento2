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
/*browser:true*/
/*global define*/
define(['mage/url'], function (url) {
    'use strict';

    return {
        /**
         * Resolve the store code the REST call has to be made against.
         *
         * Magento_Checkout/js/model/url-builder reads window.checkoutConfig.storeCode at define
         * time, which throws on catalog and cart pages where no checkoutConfig exists — that is
         * why the express-checkout components cannot simply use it. window.buckarooStoreCode is
         * emitted on every frontend page by Buckaroo_Magento2::js/store-code.phtml.
         *
         * @returns {String|null}
         */
        getStoreCode: function () {
            if (window.buckarooStoreCode) {
                return window.buckarooStoreCode;
            }

            if (window.checkoutConfig && window.checkoutConfig.storeCode) {
                return window.checkoutConfig.storeCode;
            }

            return null;
        },

        /**
         * Build an absolute REST URL that carries the current store code.
         *
         * Without the store code the request goes to rest/V1/..., which Magento's PathProcessor
         * leaves unhandled: no store is set and every scoped config read inside resolves against
         * the default store view instead of the shopper's.
         *
         * Falls back to the storeless route when the code cannot be resolved, so the call still
         * works rather than failing outright.
         *
         * @param {String} path Endpoint path starting with a slash, e.g. '/buckaroo/voucher/apply'
         * @returns {String}
         */
        createUrl: function (path) {
            var storeCode = this.getStoreCode();

            return url.build('rest/' + (storeCode ? storeCode + '/' : '') + 'V1' + path);
        }
    };
});
