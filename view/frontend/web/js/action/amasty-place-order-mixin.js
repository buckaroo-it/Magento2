define([
    'jquery',
    'mage/utils/wrapper',
], function ($, wrapper) {
    'use strict';

    let getAmastyPromise = function () {
        let requestDeferred = $.Deferred();
        if (window.checkoutConfig.amOrderAttribute !== undefined) {
            require([
              'Amasty_Orderattr/js/model/attribute-sets/payment-attributes',
              'Amasty_Orderattr/js/model/validate-and-save'
            ], function (attributesForm, validateAndSave) {
                requestDeferred.resolve(validateAndSave(attributesForm));
            })
        } else {
            requestDeferred.resolve($.when());
        }

        return requestDeferred.promise().then(function (resp) {
            return resp;
        });
    }
    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, redirectOnSuccess, messageContainer) {
            var result = $.Deferred();
            getAmastyPromise().done(
                function () {
                    $.when(
                        originalAction(paymentData, redirectOnSuccess, messageContainer)
                    ).fail(
                        function () {
                            result.reject.apply(this, arguments);
                        }
                    ).done(
                        function () {
                            result.resolve.apply(this, arguments);
                        }
                    );
                }
            ).fail(
                function () {
                    result.reject();
                }
            );

            return result.promise();
        });
    };
});
