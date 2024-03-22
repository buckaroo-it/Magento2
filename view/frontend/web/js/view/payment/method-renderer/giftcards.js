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
            var p = ["billink","klarnakp","capayableinstallments","sofortbanking","giropay","transfer","sepadirectdebit","capayablein3","creditcard","mrcash","payperemail", "tinka"];
            p.forEach(function (item) {
                $('.buckaroo_magento2_' + item).remove();
            });
            $('.buckaroo_magento2_flow_authorize').remove();
            checkLabels();
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
                    this.currentGiftcard.subscribe(function(code) {
                        this.setTestParameters(code);
                    }.bind(this));
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

                validate: function() {
                    return this.alreadyFullPayed() === true;
                },

                validateForm: function () {
                    return $('.buckaroo_magento2_' + this.currentGiftcard() + ' .payment-method-second-col form').valid();
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
                        }
                        if (data.error) {
                                alert({
                                    title: $t('Error'),
                                    content: $t(data.error),
                                    actions: {always: function (){} }
                                });
                                self.messageContainer.addErrorMessage({'message': $t(data.error)});
                        } else {
                            if (data.RemainderAmount != 0) {
                                alert({
                                    title: $t('Success'),
                                    content: $t(data.message),
                                    actions: {always: function (){} },
                                    buttons: [{
                                        text: $t(data.PayRemainingAmountButton),
                                        class: 'action primary accept',
                                        click: function () {
                                            this.closeModal(true);
                                        }
                                    }]
                                });
                            }
                            self.messageContainer.addSuccessMessage({'message': $t(data.message)});
                        }
                    });

                },
                checkForPayments: function () {
                    checkPayments();
                },

                setTestParameters(giftcardCode) {
                    if (this.buckaroo.isTestMode && !this.buckaroo.groupGiftcards) {
                        if (["boekenbon","vvvgiftcard","yourgift","customgiftcard","customgiftcard1","customgiftcard2"].indexOf(giftcardCode) !== -1) {
                            this.cardNumber('0000000000000000001')
                            this.pin('1000')
                        }

                        if (giftcardCode === 'fashioncheque') {
                            this.cardNumber('1000001000')
                            this.pin('2000')
                        }
                    }
                }
            }
        );
    }
);


