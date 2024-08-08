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
  "Magento_Checkout/js/view/payment/default",
  "Magento_Checkout/js/model/payment/additional-validators",
  "Buckaroo_Magento2/js/action/place-order",
  "ko",
  "mage/translate",
  "Magento_Checkout/js/checkout-data",
  "Magento_Checkout/js/action/select-payment-method",
  "buckaroo/checkout/common",
], function (
  $,
  Component,
  additionalValidators,
  placeOrderAction,
  ko,
  $t,
  checkoutData,
  selectPaymentMethodAction,
  checkoutCommon
) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "Buckaroo_Magento2/payment/buckaroo_magento2_paybybank",
      selectedBank: "",
      validationState: {},
      showAll: false,
      bankTypes: window.checkoutConfig.payment.buckaroo.paybybank.banks,
      isMobile: $(window).width() < 768,
      logo: require.toUrl('Buckaroo_Magento2/images/paybybank.gif')
    },
    redirectAfterPlaceOrder: false,
    selectionType:
      window.checkoutConfig.payment.buckaroo.paybybank.selectionType,
    subtext: window.checkoutConfig.payment.buckaroo.paybybank.subtext,
    subTextStyle: checkoutCommon.getSubtextStyle("paybybank"),
    currencyCode: window.checkoutConfig.quoteData.quote_currency_code,
    baseCurrencyCode: window.checkoutConfig.quoteData.base_currency_code,
    internalBanks: window.checkoutConfig.payment.buckaroo.paybybank.banks,
    /**
     * @override
     */
    initialize: function (options) {
      return this._super(options);
    },

    initObservable: function () {
      this._super().observe(["selectedBank", "validationState", "showAll", "isMobile"]);
      this.initialSelected();
        const self = this;
      $(window).resize(function () {
        const width = $(window).width();
        if(width < 768 && self.isMobile() === false) {
            self.isMobile(true);
        } else if (width >= 768) {
            self.isMobile(false);
        }
      });

      this.bankTypes = ko.computed(function () {
        const issuers = window.checkoutConfig.payment.buckaroo.paybybank.banks;
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

      /** Check used to see form is valid **/
      this.buttoncheck = ko.computed(function () {
        const state = this.validationState();
        const valid = ["issuer"]
          .map((field) => {
            if (state[field] !== undefined) {
              return state[field];
            }
            return false;
          })
          .reduce(function (prev, cur) {
            return prev && cur;
          }, true);
        return valid;
      }, this);

      this.logo = ko.computed(function () {
        let found  = this.internalBanks.find(function (bank) {
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
      let found = this.internalBanks.find(function (bank) {
        return bank.selected === true;
      });

      if (found !== undefined) {
        this.selectedBank(found.code);
        this.updateFormState(true);
      }
    },

    validateField(data, event) {
      this.updateFormState(
        $(event.target).valid()
      );
    },

    updateFormState(isValid) {
      let state = this.validationState();
      state["issuer"] = isValid;
      this.validationState(state);
    },

    toggleShow: function () {
      this.showAll(!this.showAll());
    },
    /**
     * Place order.
     *
     * placeOrderAction has been changed from Magento_Checkout/js/action/place-order to our own version
     * (Buckaroo_Magento2/js/action/place-order) to prevent redirect and handle the response.
     */
    placeOrder: function (data, event) {
      var self = this,
        placeOrder;

      if (event) {
        event.preventDefault();
      }

      if (this.validate() && additionalValidators.validate()) {
        this.isPlaceOrderActionAllowed(false);
        placeOrder = placeOrderAction(
          this.getData(),
          this.redirectAfterPlaceOrder,
          this.messageContainer
        );

        $.when(placeOrder)
          .fail(function () {
            self.isPlaceOrderActionAllowed(true);
          })
          .done(this.afterPlaceOrder.bind(this));
        return true;
      }
      return false;
    },

    afterPlaceOrder: function () {
      var response = window.checkoutConfig.payment.buckaroo.response;
      checkoutCommon.redirectHandle(response);
    },

    selectPaymentMethod: function () {
      selectPaymentMethodAction(this.getData());
      checkoutData.setSelectedPaymentMethod(this.item.method);
      return true;
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

    payWithBaseCurrency: function () {
      var allowedCurrencies =
        window.checkoutConfig.payment.buckaroo.paybybank.allowedCurrencies;

      return allowedCurrencies.indexOf(this.currencyCode) < 0;
    },

    getPayWithBaseCurrencyText: function () {
      var text = $.mage.__("The transaction will be processed using %s.");

      return text.replace("%s", this.baseCurrencyCode);
    },
  });
});
