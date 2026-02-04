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
});

export default config;
