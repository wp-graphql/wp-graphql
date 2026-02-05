/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig } from '@playwright/test';

/**
 * WordPress dependencies
 */
const baseConfig = require('@wordpress/scripts/config/playwright.config');

process.env.WP_ARTIFACTS_PATH ??= path.join(process.cwd(), 'artifacts');
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

const config = defineConfig({
	...baseConfig,
	globalSetup: require.resolve('./config/global-setup.js'),
	// Only start webServer if not in CI (wp-env is already started by GitHub Actions)
	webServer: process.env.CI
		? undefined
		: {
				...baseConfig.webServer,
				command: 'npm run wp-env -- start',
			},
	// Use multiple reporters in CI for better test output visibility
	// - 'line' shows real-time test-by-test progress
	// - 'github' provides GitHub Actions annotations
	// - 'list' shows final summary
	reporter: process.env.CI
		? [
				['line'], // Real-time test progress
				['github'], // GitHub Actions annotations
				['list'], // Final summary
			]
		: baseConfig.reporter || 'list',
});

export default config;
