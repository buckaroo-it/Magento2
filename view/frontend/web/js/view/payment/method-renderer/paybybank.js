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
define([
  "jquery",
  "buckaroo/checkout/payment/default",
  "ko"
], function (
    $,
    Component,
    ko
) {
    "use strict";

    return Component.extend({
        defaults: {
            template: "Buckaroo_Magento2/payment/buckaroo_magento2_paybybank",
            isMobile: $(window).width() < 768
        },
        redirectAfterPlaceOrder: false,
      /**
       * @override
       */
        initialize: function (options) {
            return this._super(options);
        },

        initObservable: function () {
            this._super().observe(["isMobile"]);
            const self = this;
            $(window).resize(function () {
                const width = $(window).width();
                if (width < 768 && self.isMobile() === false) {
                    self.isMobile(true);
                } else if (width >= 768) {
                    self.isMobile(false);
                }
            });

            this.logo = ko.computed(function () {
                return this.buckaroo.logo;
            }, this);
            return this;
        }
    });
});
