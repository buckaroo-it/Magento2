define([
    'jquery',
    'ko',
    'uiComponent',
    'underscore',
    'uiRegistry',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Ui/js/modal/alert'
], function ($, ko, Component, _, uiRegistry, stepNavigator, alert) {
    'use strict';

    /**
     * idinstep - iDIN identification,
     */
    return Component.extend({
        defaults: {
            template: (window.checkoutConfig.buckarooIdin.active === null || window.checkoutConfig.buckarooIdin.active == 0) ? false : 'Buckaroo_Magento2/checkout/idinstep'
        },

        banktypes: [],
        idinIssuer: null,
        selectedBankDropDown: null,

        // add here your logic to display step,
        isVisible: ko.observable(true),

        /**
         * @returns {*}
         */
        initialize: function () {

            this._super();

            if(window.checkoutConfig.buckarooIdin.active === null || window.checkoutConfig.buckarooIdin.active == 0){
                stepNavigator.next();
                return this;
            }

            stepNavigator.registerStep(
                'step_idin',
                null,
                'Age verification',
                this.isVisible,
                _.bind(this.navigate, this),
                1
            );

            return this;
        },

        initObservable: function () {
            this._super().observe(['selectedBank', 'banktypes']);
            this.banktypes = ko.observableArray(window.checkoutConfig.buckarooIdin.issuers);
            return this;
        },

        isOsc: function () {
            return document.querySelector('.action.primary.checkout.iosc-place-order-button');
        },

        setSelectedBankDropDown: function() {
            if(this.isOsc){
                this.verificateIDIN();
            }
            return true;
        },

        verificateIDIN: function () {
            var el = document.getElementById("buckaroo_magento2_idin_issuer");
            var issuer = el.options[el.selectedIndex].value;
                self = this;

                if(!issuer){
                    alert({
                        title: $t('Error'),
                        content: $t('Please choose bank'),
                        actions: {always: function(){} }
                    });
                    return ;
                }

                $.ajax({
                    url: "/buckaroo/checkout/idin",
                    type: 'POST',
                    dataType: 'json',
                    showLoader: true, //use for display loader 
                    data: {
                        issuer: issuer,
                    }
               }).done(function (response) {
                    if (response.RequiredAction !== undefined && response.RequiredAction.RedirectURL !== undefined) {
                        window.location.replace(response.RequiredAction.RedirectURL);
                    }
                });

        },

        navigate: function () {
            this.isVisible(true);
        },

        navigateToNextStep: function () {
            stepNavigator.next();
        }
    });
});