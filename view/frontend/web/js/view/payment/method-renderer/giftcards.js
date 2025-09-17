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
define(
    [
        'jquery',
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Buckaroo_Magento2/js/action/place-order',
        'ko',
        'mage/translate',
        'mage/url',
        'Magento_Ui/js/modal/alert',
        'Magento_Checkout/js/action/get-totals',
        'Magento_Customer/js/customer-data',
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        ko,
        $t,
        url,
        alert,
        getTotalsAction,
        customerData,
    ) {
        'use strict';

        function checkLabels()
        {
            $.each($('.buckaroo_magento2_image_title span'), function (key, item) {
                var label = $(item).html(),
                    label_part = label.split(' + ');
                $(item).html(label_part[0]);
            });
        }

        function checkPayments()
        {
            var p = ["billink","klarnakp","capayableinstallments","transfer","sepadirectdebit","capayablein3","creditcard","mrcash","payperemail"];
            p.forEach(function (item) {
                $('.buckaroo_magento2_' + item).remove();
            });
            $('.buckaroo_magento2_flow_authorize').remove();
            checkLabels();
        }

        function refreshTotals()
        {
            var deferred = $.Deferred();
            getTotalsAction([], deferred);
            customerData.reload(['cart'], true);
        }

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_giftcards',
                    alreadyFullPayed : false,
                    cardNumber: null,
                    pin:        null,
                    currentGiftcard: null
                },
                redirectAfterPlaceOrder: false,



                initObservable: function () {
                    this._super().observe(['alreadyFullPayed','cardNumber','pin', 'currentGiftcard']);
                    return this;
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
                        //mageplaza only check
                        if (document.querySelector('#checkoutSteps.opc.one-step-checkout-container .place-order-primary button.checkout')) {
                            if (data || event) {
                                return false;
                            }
                        }

                        this.isPlaceOrderActionAllowed(false);
                        placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder, this.messageContainer);

                        $.when(placeOrder).fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(this.afterPlaceOrder.bind(this));
                        return true;
                    }
                    return false;
                },

                afterPlaceOrder: function () {
                    this._super();
                    if (this.alreadyFullPayed()) {
                        window.location.replace(url.build('checkout/onepage/success/'));
                    }
                },

                validate: function() {
                    // If already fully paid, validation passes
                    if (this.alreadyFullPayed() === true) {
                        return true;
                    }

                    // For grouped mode (redirect), no validation needed
                    if (this.buckaroo.groupGiftcards === true) {
                        return true;
                    }

                    // For individual mode, check if a giftcard is selected
                    if (!this.buckaroo.groupGiftcards && this.currentGiftcard()) {
                        return true;
                    }

                    return false;
                },

                validateForm: function () {
                    // For individual giftcard mode, find the form within the specific giftcard container
                    if (!this.buckaroo.groupGiftcards) {
                        var giftcardSelector = '.buckaroo_magento2_' + this.currentGiftcard() + ' form';
                        var $form = $(giftcardSelector);
                        if ($form.length > 0) {
                            return $form.valid();
                        }
                    }

                    // For grouped mode or fallback, use the general form selector
                    var $generalForm = $('.buckaroo_magento2_giftcards .payment-method-second-col form');
                    if ($generalForm.length > 0) {
                        return $generalForm.valid();
                    }

                    // If no form found, return true to avoid blocking
                    return true;
                },

                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "giftcard_method" : this.currentGiftcard()
                        }
                    };
                },

                applyGiftcard: function () {
                    if (!this.validateForm()) {
                        return;
                    }
                    self = this;

                    $.ajax({
                        url: url.build('buckaroo/checkout/giftcard'),
                        type: 'POST',
                        dataType: 'json',
                        showLoader: true, //use for display loader
                        data: {
                            card: self.currentGiftcard,
                            cardNumber: self.cardNumber(),
                            pin: self.pin()
                        }
                    }).done(function (data) {
                        self.cardNumber('');
                        self.pin('');

                        if (data.alreadyPaid) {
                            if (data.RemainderAmount == 0) {
                                self.alreadyFullPayed(true);
                                self.placeOrder(null, null);
                            }
                            checkPayments();
                        }
                        // Refresh checkout summary totals so 'Already Paid' and 'Remaining Amount' appear immediately
                        refreshTotals();
                        if (data.error) {
                                alert({
                                    title: $t('Error'),
                                    content: $t(data.error),
                                    actions: {always: function (){} }
                                });
                                self.messageContainer.addErrorMessage({'message': $t(data.error)});
                        } else {
                            if (data.RemainderAmount != 0) {
                                checkPayments();
                                alert({
                                    title: $t('Success'),
                                    content: $t(data.message),
                                    actions: {always: function (){} },
                                    buttons: [{
                                        text: $t(data.PayRemainingAmountButton),
                                        class: 'action primary accept',
                                        click: function () {
                                            this.closeModal(true);
                                            window.location.reload();
                                        }
                                    }]
                                });
                            }
                            self.messageContainer.addSuccessMessage({'message': $t(data.message)});
                        }
                    });

                },
                /**
                 * Select giftcard method in individual mode
                 */
                selectGiftcardMethod: function(giftcardCode) {
                    this.selectPaymentMethod();
                    this.currentGiftcard(giftcardCode);
                    this.setTestParameters(giftcardCode);
                    return true;
                },



                /**
                 * Determine if individual giftcards should show (Inline mode)
                 * @returns {boolean} True for inline mode, false for redirect mode
                 */
                shouldShowIndividual: function() {
                    return !this.buckaroo.groupGiftcards;
                },

                /**
                 * Determine if grouped giftcards should show (Redirect mode) 
                 * @returns {boolean} True for redirect mode, false for inline mode
                 */
                shouldShowGrouped: function() {
                    return !!this.buckaroo.groupGiftcards;
                },

                setTestParameters: function(giftcardCode) {
                    // Allow function to work with both direct code parameter and event object
                    var code = giftcardCode;
                    if (typeof giftcardCode === 'object' && giftcardCode.target) {
                        code = giftcardCode.target.value;
                    }
                    if (!code && this.currentGiftcard()) {
                        code = this.currentGiftcard();
                    }

                    let cardNumber = '';
                    let pin = '';
                    if (this.buckaroo.isTestMode && !this.buckaroo.groupGiftcards) {
                        if (["boekenbon","vvvgiftcard","yourgift","customgiftcard","customgiftcard1","customgiftcard2"].indexOf(code) !== -1) {
                            cardNumber = '0000000000000000001';
                            pin = '1000';
                        }

                        if (code === 'fashioncheque') {
                            cardNumber = '1000001000';
                            pin = '2000';
                        }
                    }
                    this.cardNumber(cardNumber);
                    this.pin(pin);
                }
            }
        );
    }
);


