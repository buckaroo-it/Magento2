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
/**
 * The product form on the product page, as express payments use it: checked with Magento's own validation
 * (the same check the Add to Cart button runs) and read at the moment the payment starts, so the request
 * matches what the shopper selected.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate',
    'mage/validation'
], function ($, customerData, $t) {
    'use strict';

    var FORM = '#product_addtocart_form';

    return {
        /**
         * Run Magento's add to cart validation; missing options and an invalid quantity are marked at the fields.
         * Without the product form there is nothing reliable to check or send, so the express payment does not start.
         *
         * @returns {Boolean}
         */
        validate: function () {
            var form = $(FORM);

            if (!form.length) {
                customerData.set('messages', {
                    messages: [{
                        type: 'error',
                        text: $t('Please select your product options and use Add to Cart.')
                    }]
                });

                return false;
            }

            if (!form.data('validator')) {
                form.validation();
            }

            return form.valid();
        },

        /**
         * The product form as Magento submits it to add the product to the cart
         *
         * @returns {String}
         */
        serialize: function () {
            return $(FORM).serialize();
        },

        /**
         * @returns {{id: String|null, qty: String|Number, selected_options: Object}} selected_options: attribute id => option id
         */
        read: function () {
            var selection = {id: null, qty: 1, selected_options: {}};

            $(FORM).serializeArray().forEach(function (field) {
                var match = field.name.match(/^super_attribute\[(\d+)\]$/);

                if (match) {
                    if (field.value) {
                        selection.selected_options[match[1]] = field.value;
                    }
                } else if (field.name === 'product') {
                    selection.id = field.value;
                } else if (field.name === 'qty' && field.value) {
                    selection.qty = field.value;
                }
            });

            return selection;
        }
    };
});
