/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'uiComponent',
        'jquery',
        'Magento_Theme/js/view/messages',
        'Magento_Ui/js/model/messageList'
    ],
    function (Component, $, messagesFactory, messageList) {
        'use strict';

        return Component.extend(
            {
                initialize: function () {
                    this._super();
                    // Checkout removes page.messages, so the restore message only exists in the cookie.
                    if (typeof URLSearchParams !== 'function' || document.cookie.indexOf('mage-messages') === -1) {
                        return;
                    }
                    var messagesComponent = messagesFactory();
                    if (!messagesComponent || !messagesComponent.cookieMessages) {
                        return;
                    }
                    var showErrors = new URLSearchParams(window.location.search).get('bk_e');
                    var errors = [];
                    messagesComponent.cookieMessages.forEach(function (message) {
                        var text = $.mage.__($("<textarea></textarea>").html(message.text).text());
                        if (message.type == 'success') {
                            messageList.addSuccessMessage({message: text});
                        } else if (message.type == 'warning' || (showErrors && message.type == 'error')) {
                            // The checkout message list has no warning type. A warning, such as a product
                            // that could not be restored to the cart, must still reach the customer.
                            errors.push(text);
                        }
                    });
                    // addSuccessMessage() and addErrorMessage() clear the list before adding,
                    // so add these afterwards and without clearing to keep every message.
                    errors.forEach(function (text) {
                        messageList.errorMessages.push(text);
                    });
                }
            }
        );
    }
);
