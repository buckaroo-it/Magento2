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
                    if (typeof URLSearchParams === 'function') {
                        var searchParams = new URLSearchParams(window.location.search);
                        if (searchParams.get('bk_e')) {
                            var messagesComponent = messagesFactory();
                            if (messagesComponent && messagesComponent.cookieMessages) {
                                messagesComponent.cookieMessages.forEach(function (message) {
                                    if (message.type == 'error') {
                                        messageList.addErrorMessage({message: $.mage.__($("<textarea></textarea>").html(message.text).text())});
                                    }
                                });
                            }
                        }
                    }
                }
            }
        );
    }
);
