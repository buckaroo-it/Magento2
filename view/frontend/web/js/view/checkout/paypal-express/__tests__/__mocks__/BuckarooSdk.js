/** Minimal BuckarooSdk stub for Jest — only the surface pay.js touches. */
'use strict';
module.exports = {
    PayPal: { initiate: jest.fn() },
    Base:   { setTestMode: jest.fn() },
};
