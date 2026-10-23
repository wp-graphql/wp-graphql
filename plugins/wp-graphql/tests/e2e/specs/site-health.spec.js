import { test, expect } from '@playwright/test';
import {
	loginToWordPressAdmin,
	setPluginActive,
	wpAdminUrl,
} from '../utils.js';

/**
 * @file site-health.spec.js
 * @description Checks that each of WPGraphQL's recommendations shows in Tools > Site Health and
 * follows the settings. WPGraphQL only adds them on production sites, and the tests site is a local
 * one, so the `settings-page-spec/site-health-spec.php` test plugin is active during these tests to
 * add them as if the site were in production. The original settings are restored afterwards.
 */

const siteHealthPluginFile = 'settings-page-spec/site-health-spec.php';

const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings`;
const siteHealthUrl = `${wpAdminUrl}/site-health.php`;

/**
 * The settings the tests change. Checkboxes are set before selects, because a select that depends
 * on a checkbox is hidden until the checkbox is on.
 */
const checkboxes = [
	'public_introspection_enabled',
	'debug_mode_enabled',
	'query_depth_enabled',
	'tracing_enabled',
	'query_logs_enabled',
];
const selects = ['tracing_user_role', 'query_log_user_role'];

/**
 * Every setting flagged by a recommendation.
 */
const flagged = {
	public_introspection_enabled: true,
	debug_mode_enabled: true,
	query_depth_enabled: false,
	tracing_enabled: true,
	tracing_user_role: 'any',
	query_logs_enabled: true,
	query_log_user_role: 'any',
};

/**
 * Every setting the way the recommendations suggest.
 */
const passing = {
	public_introspection_enabled: false,
	debug_mode_enabled: false,
	query_depth_enabled: true,
	tracing_enabled: true,
	tracing_user_role: 'administrator',
	query_logs_enabled: false,
	query_log_user_role: 'administrator',
};

/**
 * The heading of each check when it recommends a change, and when it passes.
 */
const checks = [
	{
		recommended: 'WPGraphQL shows the schema to logged-out visitors',
		good: 'WPGraphQL only shows the schema to logged-in users',
	},
	{
		recommended: 'WPGraphQL debug mode is on',
		good: 'WPGraphQL debug mode is off',
	},
	{
		recommended: 'WPGraphQL does not limit query depth',
		good: 'WPGraphQL limits query depth',
	},
	{
		recommended: 'WPGraphQL shows tracing or query logs to any user',
		good: 'WPGraphQL tracing and query logs are not shown to everyone',
	},
];

/**
 * Returns the locator of a WPGraphQL setting on the settings page.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @param {string}                          name The setting name.
 * @return {import('@playwright/test').Locator} The field.
 */
function field(page, name) {
	return page.locator(
		`[name="graphql_general_settings[${name}]"]:not([type="hidden"])`
	);
}

/**
 * Reads the settings the tests change from the settings page.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<Object<string, boolean|string>>} The saved values.
 */
async function readSettings(page) {
	await page.goto(settingsUrl, { waitUntil: 'networkidle' });

	const values = {};
	for (const name of checkboxes) {
		values[name] = await field(page, name).isChecked();
	}
	for (const name of selects) {
		values[name] = await field(page, name).inputValue();
	}
	return values;
}

/**
 * Submits the settings form.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 */
async function submitSettings(page) {
	await page.getByRole('button', { name: 'Save Changes' }).click();
	await page.waitForURL(/settings-updated=true/);
}

/**
 * Saves settings from the settings page.
 *
 * Debug mode is saved first, because the settings page disables the introspection checkbox
 * while debug mode is on. A field the page disables is left as it is.
 *
 * @param {import('@playwright/test').Page} page   The Playwright page object.
 * @param {Object<string, boolean|string>}  values The values to save, keyed by setting name.
 */
async function saveSettings(page, values) {
	await page.goto(settingsUrl, { waitUntil: 'networkidle' });
	await field(page, 'debug_mode_enabled').setChecked(
		values.debug_mode_enabled
	);
	await submitSettings(page);

	for (const name of checkboxes) {
		const checkbox = field(page, name);
		if (await checkbox.isEnabled()) {
			await checkbox.setChecked(values[name]);
		}
	}
	for (const name of selects) {
		const select = field(page, name);
		// A select whose checkbox is off is hidden, and keeps its saved value.
		if (await select.isVisible()) {
			await select.selectOption(values[name]);
		}
	}
	await submitSettings(page);
}

/**
 * Opens Site Health and waits for its tests to finish.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 */
async function openSiteHealth(page) {
	await page.goto(siteHealthUrl, { waitUntil: 'networkidle' });
	await expect(page.locator('.site-health-progress-wrapper')).not.toHaveClass(
		/loading/
	);
}

test.describe('Site Health', () => {
	let original;

	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await setPluginActive(page, siteHealthPluginFile, true);
		original = await readSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await saveSettings(page, original);
		await setPluginActive(page, siteHealthPluginFile, false);
	});

	test('recommends a change for each flagged setting', async ({ page }) => {
		await saveSettings(page, flagged);
		await openSiteHealth(page);

		const recommended = page.locator('#health-check-issues-recommended');

		for (const check of checks) {
			await expect(
				recommended.getByRole('button', { name: check.recommended })
			).toBeVisible();
		}

		// A recommendation, never a critical issue.
		await expect(
			page
				.locator('#health-check-issues-critical')
				.getByRole('button', { name: /WPGraphQL/ })
		).toHaveCount(0);

		// The introspection recommendation explains how to get the schema without it, and links
		// to the setting and the docs. Debug mode is on too, so the link points at debug mode.
		await recommended
			.getByRole('button', { name: checks[0].recommended })
			.click();
		const details = recommended.locator('.health-check-accordion-panel', {
			hasText: 'wp graphql generate-static-schema',
		});
		await expect(details).toContainText('application password');
		await expect(
			details.getByRole('link', { name: 'Review debug mode' })
		).toHaveAttribute('href', settingsUrl);
		await expect(
			details.getByRole('link', {
				name: /Learn more about WPGraphQL security/,
			})
		).toHaveAttribute(
			'href',
			'https://www.wpgraphql.com/docs/security#getting-the-schema-without-public-introspection'
		);
	});

	test('lists each check as passed once its setting follows the recommendation', async ({
		page,
	}) => {
		await saveSettings(page, passing);
		await openSiteHealth(page);

		const recommended = page.locator('#health-check-issues-recommended');
		for (const check of checks) {
			await expect(
				recommended.getByRole('button', { name: check.recommended })
			).toHaveCount(0);
		}

		await page.getByRole('button', { name: 'Passed tests' }).click();
		const good = page.locator('#health-check-issues-good');
		for (const check of checks) {
			await expect(
				good.getByRole('button', { name: check.good })
			).toBeVisible();
		}
	});
});
