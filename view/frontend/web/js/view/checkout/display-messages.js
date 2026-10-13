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
                    messagesComponent.cookieMessages.forEach(function (message) {
                        var text = $.mage.__($("<textarea></textarea>").html(message.text).text());
                        if (message.type == 'success') {
                            messageList.addSuccessMessage({message: text});
                        } else if (showErrors && message.type == 'error') {
                            messageList.addErrorMessage({message: text});
                        }
                    });
                }
            }
        );
    }
);
