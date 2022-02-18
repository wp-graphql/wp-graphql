/**
 * WordPress dependencies
 */
const baseConfig = require( '@wordpress/scripts/config/jest-e2e.config' );

module.exports = {
    ...baseConfig,
    setupFilesAfterEnv: [
        './setup-test-framework.js',
        '@wordpress/jest-console',
        '@wordpress/jest-puppeteer-axe',
        'expect-puppeteer',
        'puppeteer-testing-library/extend-expect',
    ],
};
