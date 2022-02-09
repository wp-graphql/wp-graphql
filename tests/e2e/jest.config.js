/**
 * WordPress dependencies
 */
const baseConfig = require( '@wordpress/scripts/config/jest-e2e.config' );

module.exports = {
    ...baseConfig,
    testMatch: [ '<rootDir>/?(*.)test.[jt]s' ],
    setupFilesAfterEnv: [
        './setup-test-framework.js',
        '@wordpress/jest-console',
        '@wordpress/jest-puppeteer-axe',
        'expect-puppeteer',
        'puppeteer-testing-library/extend-expect',
    ],
    testPathIgnorePatterns: [
        '/node_modules/',
        'e2e-tests/specs/performance/',
    ]
};
