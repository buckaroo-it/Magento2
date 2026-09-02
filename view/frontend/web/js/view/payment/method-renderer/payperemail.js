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
define(
    [
        'buckaroo/checkout/payment/default',
        'Magento_Checkout/js/model/quote',
    ],
    function (
        Component,
        quote,
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

