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
define([
    'jquery',
    'ko',
    'uiComponent',
    'underscore',
    'uiRegistry',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'Magento_Checkout/js/model/quote'
], function ($, ko, Component, _, uiRegistry, stepNavigator, alert, $t, quote) {
    'use strict';

    function isOsc() {
        return window.checkoutConfig && window.checkoutConfig.buckarooIdin && window.checkoutConfig.buckarooIdin.isOscEnabled;
    }

    function isOscMagePlaza() {
        return window.checkoutConfig && window.checkoutConfig.oscConfig;
    }

    window.onhashchange = function(){
        if (!isOscMagePlaza() && !isOsc()) {
            if (window.checkoutConfig.buckarooIdin.active > 0 && window.location.hash.replace('#', '') != 'step_idin') {
                window.location.replace('#step_idin');
            }
        }
    };

    /**
     * idinstep - iDIN identification,
     */
    return Component.extend({
        defaults: {
            template: 'Buckaroo_Magento2/checkout/idinstep'
        },

        banktypes: [],
        idinIssuer: null,
        selectedBankDropDown: null,
        hideSubmitButton: null,
        hideIdinBlock: null,

        // isVisible: ko.observable(false),
        isVisible: ko.observable(window.checkoutConfig.buckarooIdin.active > 0),

        /**
         * @returns {*}
         */
        initialize: function () {
            this._super();

            if(window.checkoutConfig.buckarooIdin.active > 0){
                this.isVisible(true);
                if (isOscMagePlaza() || isOsc()) {
                    if (!window.checkoutConfig.buckarooIdin.verified) {
                        this.hideSubmitButton = true;
                    }
                } else {
                    stepNavigator.registerStep(
                        'step_idin',
                        null,
                        $t('Age verification'),
                        this.isVisible,
                        _.bind(this.navigate, this),
                        1
                    );

                    if (window.location.hash.replace('#', '') != 'step_idin') {
                        window.location.replace('#step_idin');
                    }
                }
            }else{
                if (isOscMagePlaza() || isOsc()) {
                    this.hideIdinBlock = true;
                }
                this.isVisible(false);
            }

            return this;
        },

        initObservable: function () {
            this._super().observe(['selectedBank', 'banktypes']);
            this.banktypes = ko.observableArray(window.checkoutConfig.buckarooIdin.issuers);
            return this;
        },

        setSelectedBankDropDown: function() {
            return true;
        },

        verificateIDIN: function () {
            var el = document.getElementById("buckaroo_magento2_idin_issuer");
            var issuer = el.options[el.selectedIndex].value;
                self = this;

                if(!issuer){
                    alert({
                        title: $t('Error'),
                        content: $t('Select your bank'),
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
                    }else{
                        alert({
                            title: $t('Error'),
                            content: $t('Unfortunately iDIN not verified!'),
                            actions: {always: function(){} }
                        }); 
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
