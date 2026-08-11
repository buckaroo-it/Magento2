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

(function (root, factory) {
    'use strict';

    var gate = factory();

    root.BuckarooHostedFieldsErrorGate = gate;

    if (typeof define === 'function' && define.amd) {
        define([], function () {
            return gate;
        });
    }
}(typeof window !== 'undefined' ? window : this, function () {
    'use strict';

    /**
     * Absorbs event bursts and the ordering race between a Blur and the trailing
     * Invalid event for the same keystroke. Not a user-facing delay: a shopper only
     * ever waits this long when a message is about to appear anyway.
     */
    var RENDER_DEBOUNCE_MS = 250;

    var TARGET_CARD_HOLDER_NAME = 'CardHolderName';
    var TARGET_CARD_NUMBER = 'CardNumber';
    var TARGET_EXPIRY_DATE = 'ExpiryDate';
    var TARGET_CVC = 'Cvc';

    var EVENT_BLUR = 'Blur';
    var EVENT_FOCUS = 'Focus';
    var EVENT_VALID = 'Valid';
    var EVENT_INVALID = 'Invalid';
    var EVENT_EMPTY = 'Empty';

    var EMPTY_FIELD_STATE = {
        message: '',
        isValid: false,
        isEmpty: true,
        isTouched: false
    };

    /**
     * Resolve the SDK's target type names, falling back to the literals when the SDK
     * is not (yet) on the page or renames its enum.
     *
     * @returns {Object}
     */
    function resolveTargetTypes() {
        var sdk = typeof window !== 'undefined' ? window.BuckarooHostedFieldsSdk : null;
        var enumerated = sdk && sdk.EventTargetType ? sdk.EventTargetType : {};

        return {
            cardHolderName: enumerated.CardHolderName || TARGET_CARD_HOLDER_NAME,
            cardNumber: enumerated.CardNumber || TARGET_CARD_NUMBER,
            expiryDate: enumerated.ExpiryDate || TARGET_EXPIRY_DATE,
            cvc: enumerated.Cvc || TARGET_CVC
        };
    }

    /**
     * Write a message into an error element, if that element is on the page.
     *
     * @param {String} elementId
     * @param {String} message
     */
    function writeMessage(elementId, message) {
        var element = document.getElementById(elementId);

        if (element) {
            element.innerText = message;
        }
    }

    /**
     * @param {Object} options
     * @param {Object} options.errorElementIds Element ids keyed by field name:
     *                                         cardHolderName, cardNumber, expiryDate, cvc
     * @returns {Object}
     */
    function createErrorGate(options) {
        var errorElementIds = (options || {}).errorElementIds || {};
        var targetTypes = resolveTargetTypes();

        /** Maps an SDK target type onto our field name. */
        var fieldNamesByTarget = {};
        var states = {};
        var timers = {};

        /**
         * "This field is required" is only fair once the shopper tries to pay. While
         * they are still working through the form an untouched empty field is not a
         * mistake, so those messages stay hidden until showAll() opens the gate.
         */
        var isShowingEmptyMessages = false;

        fieldNamesByTarget[targetTypes.cardHolderName] = 'cardHolderName';
        fieldNamesByTarget[targetTypes.cardNumber] = 'cardNumber';
        fieldNamesByTarget[targetTypes.expiryDate] = 'expiryDate';
        fieldNamesByTarget[targetTypes.cvc] = 'cvc';

        Object.keys(errorElementIds).forEach(function (fieldName) {
            states[fieldName] = EMPTY_FIELD_STATE;
            timers[fieldName] = null;
        });

        function cancelPendingRender(fieldName) {
            if (timers[fieldName] !== null) {
                clearTimeout(timers[fieldName]);
                timers[fieldName] = null;
            }
        }

        /**
         * Render what the current state says should be visible for this field.
         *
         * @param {String} fieldName
         */
        function render(fieldName) {
            var state = states[fieldName];
            var isReportable = !state.isEmpty || isShowingEmptyMessages;
            var shouldShow = state.isTouched && !state.isValid && isReportable;

            writeMessage(errorElementIds[fieldName], shouldShow ? state.message : '');
        }

        /**
         * Replace a field's state and render immediately.
         *
         * @param {String} fieldName
         * @param {Object} changes
         */
        function applyAndRender(fieldName, changes) {
            cancelPendingRender(fieldName);
            states[fieldName] = Object.assign({}, states[fieldName], changes);
            render(fieldName);
        }

        /**
         * Replace a field's state, then render after the debounce window — but only
         * for a field the shopper has already left once. An untouched field stays
         * silent no matter what it reports while being filled in.
         *
         * @param {String} fieldName
         * @param {Object} changes
         */
        function applyAndRenderDebounced(fieldName, changes) {
            cancelPendingRender(fieldName);
            states[fieldName] = Object.assign({}, states[fieldName], changes);

            if (!states[fieldName].isTouched) {
                return;
            }

            timers[fieldName] = setTimeout(function () {
                timers[fieldName] = null;
                render(fieldName);
            }, RENDER_DEBOUNCE_MS);
        }

        return {
            /**
             * Feed the gate every event the SDK session emits.
             *
             * @param {Object} event
             */
            handle: function (event) {
                if (!event) {
                    return;
                }

                var fieldName = fieldNamesByTarget[event.targetType];

                if (!fieldName || !errorElementIds[fieldName]) {
                    return;
                }

                switch (event.eventType) {
                    case EVENT_FOCUS:
                        // The shopper is working on this field again: get out of the way.
                        applyAndRender(fieldName, { isTouched: false });
                        break;

                    case EVENT_BLUR:
                        applyAndRender(fieldName, { isTouched: true });
                        break;

                    case EVENT_VALID:
                        applyAndRender(fieldName, {
                            isValid: true,
                            isEmpty: false,
                            message: ''
                        });
                        break;

                    case EVENT_INVALID:
                        applyAndRenderDebounced(fieldName, {
                            isValid: false,
                            isEmpty: false,
                            message: event.data || ''
                        });
                        break;

                    case EVENT_EMPTY:
                        // Keep the message for the place order attempt, but stay quiet
                        // about a field the shopper simply has not reached yet.
                        applyAndRenderDebounced(fieldName, {
                            isValid: false,
                            isEmpty: true,
                            message: event.data || ''
                        });
                        break;

                    default:
                        // Filled and any future event type carry no display meaning.
                        break;
                }
            },

            /**
             * Surface every outstanding message at once, for the place order attempt.
             */
            showAll: function () {
                isShowingEmptyMessages = true;

                Object.keys(states).forEach(function (fieldName) {
                    applyAndRender(fieldName, { isTouched: true });
                });
            },

            /**
             * Drop all state and clear the error elements, for a form reset.
             */
            reset: function () {
                isShowingEmptyMessages = false;

                Object.keys(states).forEach(function (fieldName) {
                    cancelPendingRender(fieldName);
                    states[fieldName] = EMPTY_FIELD_STATE;
                    writeMessage(errorElementIds[fieldName], '');
                });
            }
        };
    }

    return {
        createErrorGate: createErrorGate
    };
}));
