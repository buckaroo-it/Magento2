/**
 * Unit tests for paypal-express/pay.js
 *
 * Covers:
 *  Fix 1  – cart subscription uses grand_total only (no +tax_amount double-count).
 *  Fix A  – onShippingChangeHandler includes tax_total in the PayPal order patch.
 *  Fix B  – setConfig seeds the initial amount from quote.totals() immediately,
 *            not only on future subscription events.
 *
 * Magento/PayPal doc references:
 *  – Magento KO quote model: observable() reads current value; .subscribe() fires
 *    on future changes only (Magento DevDocs: "Knockout observables in checkout").
 *  – PayPal JS SDK v2: breakdown.item_total + breakdown.shipping + breakdown.tax_total
 *    MUST equal the purchase_unit amount value or PayPal returns AMOUNT_MISMATCH
 *    (PayPal Developer Docs: "Update Order" / PATCH /v2/checkout/orders/{id}).
 *  – Magento quote totals: grand_total is the final customer-facing amount including
 *    all taxes (Magento DevDocs: "Quote Totals").
 *
 * Run with:  npx jest --testPathPattern=paypal-express/__tests__/pay.test.js
 */

'use strict';

// ---------------------------------------------------------------------------
// Minimal AMD stub so pay.js can be required without a full RequireJS stack
// ---------------------------------------------------------------------------
global.define = function (deps, factory) {
    // Capture the factory; resolve deps from stubs below
    global.__payFactory = factory;
};

// Stubs for AMD dependencies
const jqueryStub  = {
    post: jest.fn().mockResolvedValue({}),
    // $.extend is used by pay.js to merge the productPriceMixin into the module object
    extend: (target, ...sources) => Object.assign(target, ...sources),
};
const koStub      = {};
const urlStub     = { build: jest.fn((path) => '/' + path) };
const dataStub    = { set: jest.fn(), invalidate: jest.fn(), reload: jest.fn() };
const quoteStub   = { totals: null };
const buckarooSdkStub = require('./__mocks__/BuckarooSdk');
const translateStub = jest.fn((s) => s);
const priceMixinStub = {
    initProductPriceWatchers: jest.fn(),
    getProductTotalPrice: jest.fn().mockReturnValue(null),
    getProductTotalPriceWithShipping: jest.fn().mockReturnValue(null),
};

// Load the module under test
require('../pay.js');
const pay = global.__payFactory(
    jqueryStub, koStub, urlStub, dataStub, quoteStub, translateStub, priceMixinStub
);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build a KO observable: call with no args reads value, call with arg writes it. */
function makeObservable(initialValue) {
    let val = initialValue;
    const obs = function (newVal) {
        if (arguments.length) val = newVal;
        return val;
    };
    const subscribers = [];
    obs.subscribe = function (fn) { subscribers.push(fn); return { dispose: jest.fn() }; };
    obs.__fire = function (newVal) { val = newVal; subscribers.forEach((fn) => fn(newVal)); };
    return obs;
}

/** Return a minimal totals data object matching Magento's quote.totals shape. */
function makeTotals(grandTotal, currency = 'EUR') {
    return { grand_total: grandTotal, quote_currency_code: currency };
}

// ---------------------------------------------------------------------------
// Reset pay state before each test
// ---------------------------------------------------------------------------
beforeEach(() => {
    quoteStub.totals = makeObservable(null);
    global.BuckarooSdk = buckarooSdkStub;
    pay.result       = null;
    pay.cart_id      = null;
    pay.page         = null;
    pay.options      = {};
    pay.paypalInitialized = false;
    jqueryStub.post.mockClear();
    urlStub.build.mockClear();
    dataStub.set.mockClear();
    dataStub.invalidate.mockClear();
    dataStub.reload.mockClear();
    translateStub.mockClear();
    buckarooSdkStub.Base.setTestMode.mockClear();
    buckarooSdkStub.PayPal.initiate.mockClear();
});

afterEach(() => {
    delete global.BuckarooSdk;
    delete global.window;
});

// ===========================================================================
// Fix 1: grand_total subscription — no double-counting of tax_amount
// ===========================================================================
describe('Fix 1 — cart totals subscription uses grand_total only', () => {
    test('amount is set to grand_total, NOT grand_total + tax_amount', () => {
        const totalsObs = quoteStub.totals;

        pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid' }, 'cart');

        // Simulate a totals update with tax_amount present
        totalsObs.__fire({
            grand_total: 29.98,
            tax_amount: 4.79,            // must NOT be added to the amount
            quote_currency_code: 'EUR',
        });

        expect(pay.options.amount).toBe('29.98');
    });

    test('amount reflects discounted grand_total (coupon applied), not inflated value', () => {
        const totalsObs = quoteStub.totals;

        pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid' }, 'cart');

        // With coupon: grand_total 0.48, tax_amount 4.79 — old bug gave 5.27
        totalsObs.__fire({ grand_total: 0.48, tax_amount: 4.79, quote_currency_code: 'EUR' });

        expect(pay.options.amount).toBe('0.48'); // correct; old code: '5.27'
    });

    test('currency is updated from quote_currency_code', () => {
        const totalsObs = quoteStub.totals;

        pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid' }, 'cart');
        totalsObs.__fire({ grand_total: 50.00, tax_amount: 8.00, quote_currency_code: 'USD' });

        expect(pay.options.currency).toBe('USD');
    });
});

// ===========================================================================
// Fix B: initial amount seeded from quote.totals() on setConfig
// ===========================================================================
describe('Fix B — initial amount seeded immediately from quote.totals()', () => {
    test('amount is set from current observable value before any subscription fires', () => {
        // totals already populated when setConfig is called
        const existingTotals = makeTotals(34.77);
        quoteStub.totals = makeObservable(existingTotals);

        pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid' }, 'cart');

        // No subscription event fired — amount must still be set from the seed
        expect(pay.options.amount).toBe('34.77');
    });

    test('amount stays null-safe when quote.totals() returns null on page load', () => {
        quoteStub.totals = makeObservable(null); // totals not loaded yet

        // Must not throw even when initial totals are null
        expect(() => {
            pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid' }, 'cart');
        }).not.toThrow();
    });

    test('subscription still fires on subsequent totals change after initial seed', () => {
        const totalsObs = makeObservable(makeTotals(29.98));
        quoteStub.totals = totalsObs;

        pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid' }, 'cart');
        expect(pay.options.amount).toBe('29.98'); // seeded

        // Cart changes (coupon applied)
        totalsObs.__fire(makeTotals(14.99));
        expect(pay.options.amount).toBe('14.99'); // updated by subscription
    });

    test('invalid grand_total does not overwrite the configured amount', () => {
        quoteStub.totals = makeObservable({ grand_total: 'not-a-number', quote_currency_code: 'USD' });

        pay.setConfig({ buckarooWebsiteKey: 'key', paypalMerchantId: 'mid', amount: '19.95' }, 'cart');

        expect(pay.options.amount).toBe('19.95');
        expect(pay.options.currency).toBe('EUR');
    });
});

// ===========================================================================
// Fix A: onShippingChangeHandler includes tax_total in PayPal order patch
// ===========================================================================
describe('Fix A — onShippingChangeHandler includes tax_total in order patch', () => {
    function makeActionsOrderPatch() {
        const patchFn = jest.fn().mockResolvedValue(undefined);
        return { order: { patch: patchFn } };
    }

    /** Build a minimal server response matching QuoteCreate REST output. */
    function makeShippingResponse(overrides = {}) {
        return {
            value: '29.98',
            cart_id: 'abc123',
            breakdown: {
                item_total: { value: '25.19', currency_code: 'EUR' },
                shipping:   { value: '0.00',  currency_code: 'EUR' },
                tax_total:  { value: '4.79',  currency_code: 'EUR' },
            },
            ...overrides,
        };
    }

    async function invokeShippingChange(response, actionsOrder) {
        // Stub setShipping to resolve with our response
        const origSetShipping = pay.setShipping;
        pay.setShipping = jest.fn().mockResolvedValue(response);
        pay.options = { currency: 'EUR', amount: '29.98' };
        pay.page = 'cart';

        const shippingData = { shipping_address: { countryCode: 'NL', postalCode: '1234AB' } };
        await pay.onShippingChangeHandler(shippingData, actionsOrder);

        pay.setShipping = origSetShipping;
    }

    test('patch includes tax_total from server response', async () => {
        const actions = makeActionsOrderPatch();

        await invokeShippingChange(makeShippingResponse(), actions);

        expect(actions.order.patch).toHaveBeenCalledTimes(1);
        const patchArg = actions.order.patch.mock.calls[0][0][0];
        const breakdown = patchArg.value.breakdown;

        expect(breakdown).toHaveProperty('tax_total');
        expect(breakdown.tax_total.value).toBe('4.79');
    });

    test('patch breakdown components sum exactly to value (no AMOUNT_MISMATCH)', async () => {
        const actions = makeActionsOrderPatch();

        await invokeShippingChange(makeShippingResponse(), actions);

        const patchArg = actions.order.patch.mock.calls[0][0][0];
        const { value, breakdown } = patchArg.value;

        const total    = parseFloat(value);
        const sumParts = parseFloat(breakdown.item_total.value)
            + parseFloat(breakdown.shipping.value)
            + parseFloat(breakdown.tax_total.value);

        expect(Math.round(sumParts * 100)).toBe(Math.round(total * 100));
    });

    test('patch includes all three required PayPal breakdown keys', async () => {
        const actions = makeActionsOrderPatch();

        await invokeShippingChange(makeShippingResponse(), actions);

        const breakdown = actions.order.patch.mock.calls[0][0][0].value.breakdown;
        expect(breakdown).toHaveProperty('item_total');
        expect(breakdown).toHaveProperty('shipping');
        expect(breakdown).toHaveProperty('tax_total');
    });

    test('tax_total defaults to 0.00 when absent from server response (tax-free store)', async () => {
        const actions = makeActionsOrderPatch();
        const response = makeShippingResponse();
        delete response.breakdown.tax_total; // absent for tax-free stores

        await invokeShippingChange(response, actions);

        const breakdown = actions.order.patch.mock.calls[0][0][0].value.breakdown;
        expect(breakdown.tax_total.value).toBe('0.00');
    });

    test('patch value matches server grand_total', async () => {
        const actions = makeActionsOrderPatch();

        await invokeShippingChange(makeShippingResponse({ value: '34.98' }), actions);

        const patchValue = actions.order.patch.mock.calls[0][0][0].value.value;
        expect(patchValue).toBe('34.98');
    });

    test('options.amount is updated to the server grand_total after patch', async () => {
        const actions = makeActionsOrderPatch();

        await invokeShippingChange(makeShippingResponse({ value: '34.98' }), actions);

        expect(pay.options.amount).toBe('34.98');
    });

    test('invalid response totals reject instead of patching NaN values', async () => {
        const actions = makeActionsOrderPatch();

        await expect(
            invokeShippingChange(makeShippingResponse({ value: 'not-a-number' }), actions)
        ).rejects.toBe('Cannot update payment totals');

        expect(actions.order.patch).not.toHaveBeenCalled();
    });
});

// ===========================================================================
// Error handling and SDK wiring
// ===========================================================================
describe('displayErrorMessage', () => {
    test('shows parsed responseJSON.message to the customer', () => {
        pay.displayErrorMessage({ responseJSON: { message: 'Gateway declined' } });

        expect(dataStub.set).toHaveBeenCalledWith('messages', {
            messages: [{
                type: 'error',
                text: 'Gateway declined',
            }],
        });
        expect(translateStub).toHaveBeenCalledWith('Gateway declined');
    });

    test('shows parsed responseText message to the customer', () => {
        pay.displayErrorMessage({ responseText: JSON.stringify({ message: 'Address invalid' }) });

        expect(dataStub.set).toHaveBeenCalledWith('messages', {
            messages: [{
                type: 'error',
                text: 'Address invalid',
            }],
        });
    });
});

describe('SDK integration', () => {
    test('setConfig forwards isTestMode to BuckarooSdk.Base', () => {
        pay.setConfig({ isTestMode: true }, 'cart');

        expect(buckarooSdkStub.Base.setTestMode).toHaveBeenCalledWith(true);
    });

    test('init uses the global BuckarooSdk instance', () => {
        const globalSdk = {
            PayPal: { initiate: jest.fn() },
            Base: { setTestMode: jest.fn() },
        };
        global.window = { BuckarooSdk: globalSdk };

        pay.options = { amount: '10.00', currency: 'EUR' };
        pay.init();

        expect(globalSdk.PayPal.initiate).toHaveBeenCalledWith(pay.options);
    });
});
