/**
 * External dependencies
 */
const path = require('node:path');

/**
 * WordPress dependencies
 */
const baseConfig = require('@wordpress/scripts/config/playwright.config');
const { defineConfig } = require('@playwright/test');

process.env.WP_ARTIFACTS_PATH ??= path.join(process.cwd(), 'artifacts');
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

const storageStatePath = process.env.STORAGE_STATE_PATH ?? path.join(
	process.env.WP_ARTIFACTS_PATH || path.join(process.cwd(), 'artifacts'),
	'storage-states/admin.json'
);

const config = defineConfig({
	...baseConfig,
	testDir: path.join(__dirname, 'specs'),
	globalSetup: path.join(__dirname, 'config', 'global-setup.js'),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run wp-env -- start',
	},
	projects: baseConfig.projects?.map((project) => ({
		...project,
		use: {
			...project.use,
			storageState: storageStatePath,
		},
	})) ?? baseConfig.projects,
});

module.exports = config;
