import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file usage-tracking-setting.spec.js
 * @description Checks that the "Share Usage Data" toggle on the settings page shows the site's real
 * opt-in state and that saving it opts the site in or out. The
 * `settings-page-spec/usage-tracking-spec.php` test plugin blocks usage tracking requests, prints the
 * real opt-in state, and sets it from the `wpgraphql_e2e_tracking` query argument.
 */

const trackingPluginFile = 'settings-page-spec/usage-tracking-spec.php';
const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings`;
const toggle = '#wpuf-graphql_general_settings\\[share_usage_data\\]';

/**
 * Activates or deactivates a plugin from the Plugins screen.
 *
 * The row is found by its plugin file, because every plugin in the same folder shares a slug.
 *
 * @param {import('@playwright/test').Page} page       The Playwright page object.
 * @param {string}                          pluginFile The plugin file, relative to the plugins folder.
 * @param {boolean}                         active     Whether the plugin should be active.
 */
async function setPluginActive(page, pluginFile, active) {
	await page.goto(`${wpAdminUrl}/plugins.php`, { waitUntil: 'networkidle' });

	const row = page.locator(`tr[data-plugin="${pluginFile}"]`);
	const isActive = await row.locator('.deactivate a').isVisible();

	if (isActive !== active) {
		await row.locator(active ? '.activate a' : '.deactivate a').click();
		await page.waitForLoadState('networkidle');
	}

	await expect(
		row.locator(active ? '.deactivate a' : '.activate a')
	).toBeVisible();
}

/**
 * Sets the real opt-in state directly, the way the admin notice leaves it.
 *
 * @param {import('@playwright/test').Page} page  The Playwright page object.
 * @param {'yes'|'no'}                      state The opt-in state.
 */
async function setTrackingState(page, state) {
	await page.goto(
		`${wpAdminUrl}/plugins.php?wpgraphql_e2e_tracking=${state}`
	);
}

/**
 * Saves the settings page and returns the real opt-in state afterwards.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<string>} The real opt-in state, `yes` or `no`.
 */
async function saveAndReadTrackingState(page) {
	await page.getByRole('button', { name: 'Save Changes' }).click();
	await page.waitForLoadState('networkidle');

	return (
		await page.locator('#wpgraphql-e2e-tracking-state').textContent()
	).trim();
}

test.describe('Usage tracking setting', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await setPluginActive(page, trackingPluginFile, true);
	});

	test.afterEach(async ({ page }) => {
		await setTrackingState(page, 'no');
		await setPluginActive(page, trackingPluginFile, false);
	});

	test('a site that opted in through the notice can opt out from the settings page', async ({
		page,
	}) => {
		await setTrackingState(page, 'yes');

		await page.goto(settingsUrl);
		await expect(page.locator(toggle)).toBeChecked();

		await page.locator(toggle).uncheck();
		expect(await saveAndReadTrackingState(page)).toBe('no');

		await page.goto(settingsUrl);
		await expect(page.locator(toggle)).not.toBeChecked();
	});

	test('checking the toggle opts the site in', async ({ page }) => {
		await setTrackingState(page, 'no');

		await page.goto(settingsUrl);
		await expect(page.locator(toggle)).not.toBeChecked();

		await page.locator(toggle).check();
		expect(await saveAndReadTrackingState(page)).toBe('yes');

		await page.goto(settingsUrl);
		await expect(page.locator(toggle)).toBeChecked();
	});
});
