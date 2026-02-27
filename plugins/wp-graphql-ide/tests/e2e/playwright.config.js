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
	// CI: list reporter for checkmarks/x's in logs; github for annotations. Fail fast (no retries). Standard port 8889.
	reporter: process.env.CI ? [['list'], ['github']] : baseConfig.reporter,
	retries: process.env.CI ? 0 : (baseConfig.retries ?? 0),
	globalSetup: require.resolve('./config/global-setup.js'),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run wp-env -- start',
	},
});

export default config;
