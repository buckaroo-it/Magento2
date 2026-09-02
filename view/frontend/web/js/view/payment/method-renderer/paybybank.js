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
