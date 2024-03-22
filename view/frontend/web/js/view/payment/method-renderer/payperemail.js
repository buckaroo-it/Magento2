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
        'Magento_Checkout/js/model/quote',
        'ko',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/select-payment-method',
        'buckaroo/checkout/common'
    ],
    function (
        $,
        Component,
        additionalValidators,
        placeOrderAction,
        quote,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        checkoutCommon
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Buckaroo_Magento2/payment/buckaroo_magento2_payperemail',
                    selectedGender: null,
                    firstName: null,
                    middleName: null,
                    lastName: null,
                    email: null
                },
                redirectAfterPlaceOrder: true,

              
                initObservable: function () {
                    this._super().observe(
                        [
                            'selectedGender',
                            'firstName',
                            'middleName',
                            'lastName',
                            'email',
                            'validationState'
                        ]
                    );
                    quote.billingAddress.subscribe(function (address) {
                        if (address !== null) {
                            this.firstName(address.firstname || '');
                            this.lastName(address.lastname || '');
                            this.middleName(address.middlename || '');
                        }
                    }, this);

                    if (typeof customerData === 'object' && customerData.hasOwnProperty('email')) {
                        this.email(customerData.email);
                       
                    }

                    if (quote.guestEmail) {
                        this.email(quote.guestEmail);
                    }

  

                    return this;
                },
                
                getData: function () {
                    return {
                        "method": this.item.method,
                        "po_number": null,
                        "additional_data": {
                            "customer_gender": this.selectedGender(),
                            "customer_billingFirstName": this.firstName(),
                            "customer_billingMiddleName": this.middleName(),
                            "customer_billingLastName": this.lastName(),
                            "customer_email": this.email()
                        }
                    };
                },
            }
        );
    }
);

