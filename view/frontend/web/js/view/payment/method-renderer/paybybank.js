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
    ko,
) {
    "use strict";

    return Component.extend({
        defaults: {
            template: "Buckaroo_Magento2/payment/buckaroo_magento2_paybybank",
            selectedBank: "",
            showAll: false,
            isMobile: $(window).width() < 768,
            logo: require.toUrl('Buckaroo_Magento2/images/paybybank.gif')
        },
        redirectAfterPlaceOrder: false,
      /**
       * @override
       */
        initialize: function (options) {
            return this._super(options);
        },

        initObservable: function () {
            this._super().observe(["selectedBank",  "showAll", "isMobile"]);
            this.initialSelected();
            const self = this;
            $(window).resize(function () {
                const width = $(window).width();
                if (width < 768 && self.isMobile() === false) {
                    self.isMobile(true);
                } else if (width >= 768) {
                    self.isMobile(false);
                }
            });

            this.bankTypes = ko.computed(function () {
                const issuers = this.buckaroo.banks;
                if (this.showAll() === false && !this.isMobile()) {
                    if (this.selectedBank() !== "") {
                        return issuers.filter(function (bank) {
                            return bank.code  === this.selectedBank();
                        }, this);
                    }
                    return issuers.slice(0, 4);
                }
                return issuers;
            }, this);


            this.logo = ko.computed(function () {
                let found  = this.buckaroo.banks.find(function (bank) {
                    return bank.code  === this.selectedBank();
                }, this);
       
                if (found !== undefined) {
                    return found.img;
                }
                return require.toUrl('Buckaroo_Magento2/images/paybybank.gif')
            }, this);
            return this;
        },


        initialSelected() {
            let found = this.buckaroo.banks.find(function (bank) {
                return bank.selected === true;
            });

            if (found !== undefined) {
                this.selectedBank(found.code);
            }
        },

        toggleShow: function () {
            this.showAll(!this.showAll());
        },

        getData: function () {
            return {
                method: this.item.method,
                po_number: null,
                additional_data: {
                    issuer: this.selectedBank(),
                },
            };
        },

     
    });
});
