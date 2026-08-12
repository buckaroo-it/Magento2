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
define([], function () {
    'use strict';

    /**
     * Order fields the method's own fields are prefilled from, as [source id, target id] pairs.
     */
    var PREFILL_SOURCES = [
            ['email', 'buckaroo_magento2_payperemail_Email'],
            ['order-billing_address_firstname', 'buckaroo_magento2_payperemail_BillingFirstName'],
            ['order-billing_address_middlename', 'buckaroo_magento2_payperemail_BillingMiddleName'],
            ['order-billing_address_lastname', 'buckaroo_magento2_payperemail_BillingLastName']
        ],
        PREFIX_SOURCE_ID = 'order-billing_address_prefix',
        GENDER_SELECT_ID = 'buckaroo_magento2_payperemail_genderSelect',
        ORDER_EMAIL_ID = 'email',
        METHOD_EMAIL_ID = 'buckaroo_magento2_payperemail_Email',
        FEMALE_PREFIXES = ['mrs.', 'mrs', 'ms.', 'ms', 'mevr.', 'mevr', 'mw.', 'mw'],
        GENDER_MALE = '1',
        GENDER_FEMALE = '2';

    /**
     * Copy the order's billing values into the still empty method fields.
     *
     * @returns {void}
     */
    function prefillFromBillingAddress() {
        PREFILL_SOURCES.forEach(function (pair) {
            var source = document.getElementById(pair[0]),
                target = document.getElementById(pair[1]);

            if (target && target.value.length === 0 && source && source.value.length > 0) {
                target.value = source.value;
            }
        });
    }

    /**
     * Derive the salutation from the billing address prefix.
     *
     * @returns {void}
     */
    function prefillGender() {
        var source = document.getElementById(PREFIX_SOURCE_ID),
            target = document.getElementById(GENDER_SELECT_ID);

        if (!target || !source || source.value.length === 0) {
            return;
        }

        target.value = FEMALE_PREFIXES.indexOf(source.value.toLowerCase()) !== -1 ?
            GENDER_FEMALE :
            GENDER_MALE;
    }

    /**
     * Keep the method's email in sync with the order email.
     *
     * @returns {void}
     */
    function bindEmailSync() {
        var orderEmail = document.getElementById(ORDER_EMAIL_ID),
            methodEmail = document.getElementById(METHOD_EMAIL_ID);

        if (!orderEmail || !methodEmail) {
            return;
        }

        orderEmail.addEventListener('change', function () {
            methodEmail.value = orderEmail.value;
        });
    }

    /**
     * The fields render collapsed; core's order.setPaymentMethod() expands them by clearing the inline
     * display, so they are only revealed here when this method is already the selected one.
     *
     * @param {Object} config
     * @param {HTMLElement} element
     * @returns {void}
     */
    function revealWhenSelected(config, element) {
        var radio = document.getElementById('p_method_' + config.code);

        if (radio && radio.checked) {
            element.style.display = '';
        }
    }

    return function (config, element) {
        revealWhenSelected(config, element);
        prefillFromBillingAddress();
        prefillGender();
        bindEmailSync();
    };
});
