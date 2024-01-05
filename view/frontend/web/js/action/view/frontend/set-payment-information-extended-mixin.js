define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    return function (setPaymentInformationExtended) {
        return wrapper.wrap(setPaymentInformationExtended, function (originalSetPaymentInformationExtended, messageContainer, paymentData, skipBilling) {
            if (paymentData.method.startsWith('buckaroo_magento2_')) {
                skipBilling = false;
            }
            originalSetPaymentInformationExtended(messageContainer, paymentData, skipBilling);
        });
    };
});
