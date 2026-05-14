/** @type {import('jest').Config} */
module.exports = {
    testEnvironment: 'node',
    rootDir: '../',
    testMatch: ['**/__tests__/**/*.test.js'],
    // Map AMD deps that pay.js require()s to lightweight stubs
    moduleNameMapper: {
        '^BuckarooSdk$': '<rootDir>/__tests__/__mocks__/BuckarooSdk.js',
    },
    // Reset module registry between tests so each test file starts fresh
    resetModules: true,
    clearMocks: true,
};
