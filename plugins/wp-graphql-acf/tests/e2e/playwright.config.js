/**
 * Playwright config for wp-graphql-acf E2E tests.
 *
 * Local and CI both use the test site at http://localhost:8889 (baseURL from
 * @wordpress/scripts; overridable via WP_BASE_URL). CI workflow verifies 8889
 * is ready before running tests and sets WP_BASE_URL explicitly.
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
	// In CI, use both 'list' (streaming progress) and 'github' (annotations). Base config uses only 'github' so no live output.
	reporter: process.env.CI ? [['list'], ['github']] : baseConfig.reporter,
	// Fail fast in CI: no retries so we don't burn time re-running flaky tests (base config uses 2 retries in CI).
	retries: process.env.CI ? 0 : baseConfig.retries ?? 0,
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
