import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file email-updates-setting.spec.js
 * @description Checks that the "Email Updates" opt-in on the settings page is off by default, separate
 * from the usage data toggle, and that checking it and saving sends one subscribe request, and only
 * one. The `settings-page-spec/email-updates-spec.php` test plugin blocks requests to the telemetry
 * service, records the address of each subscribe request, and clears the email updates state from the
 * `wpgraphql_e2e_email_updates` query argument.
 */

const emailUpdatesPluginFile = 'settings-page-spec/email-updates-spec.php';
const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings`;
const checkbox = '#wpuf-graphql_general_settings\\[email_updates\\]';
const address = '#graphql_general_settings\\[email_updates_address\\]';
const usageToggle = '#wpuf-graphql_general_settings\\[share_usage_data\\]';

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
 * Clears the email updates state and the recorded subscribe requests.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 */
async function resetEmailUpdates(page) {
	await page.goto(
		`${wpAdminUrl}/plugins.php?wpgraphql_e2e_email_updates=reset`
	);
}

/**
 * Saves the settings page and returns the addresses of the subscribe requests sent so far.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<string[]>} The address of each subscribe request.
 */
async function saveAndReadSubscribeRequests(page) {
	// Wait for the redirect back from options.php, or the old page's state would be read.
	await Promise.all([
		page.waitForURL(/settings-updated=true/),
		page.getByRole('button', { name: 'Save Changes' }).click(),
	]);

	return JSON.parse(
		await page
			.locator('#wpgraphql-e2e-email-updates-requests')
			.textContent()
	);
}

test.describe('Email updates setting', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await setPluginActive(page, emailUpdatesPluginFile, true);
		await resetEmailUpdates(page);
	});

	test.afterEach(async ({ page }) => {
		await resetEmailUpdates(page);
		await setPluginActive(page, emailUpdatesPluginFile, false);
	});

	test('is off by default, with the address shown only once it is checked', async ({
		page,
	}) => {
		await page.goto(settingsUrl);

		await expect(page.locator(checkbox)).not.toBeChecked();
		await expect(page.locator(address)).toBeHidden();

		await page.locator(checkbox).check();

		await expect(page.locator(address)).toBeVisible();
		await expect(page.locator(address)).not.toHaveValue('');
	});

	test('saving with it unchecked sends no subscribe request', async ({
		page,
	}) => {
		await page.goto(settingsUrl);
		const usageWasChecked = await page.locator(usageToggle).isChecked();

		expect(await saveAndReadSubscribeRequests(page)).toEqual([]);

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).not.toBeChecked();
		await expect(page.locator(usageToggle)).toBeChecked({
			checked: usageWasChecked,
		});
	});

	test('checking it sends one subscribe request, and re-saving sends no more', async ({
		page,
	}) => {
		await page.goto(settingsUrl);
		await page.locator(checkbox).check();
		await page.locator(address).fill('e2e-updates@example.com');

		expect(await saveAndReadSubscribeRequests(page)).toEqual([
			'e2e-updates@example.com',
		]);
		await expect(
			page.getByText(
				'A confirmation email was requested for e2e-updates@example.com.'
			)
		).toBeVisible();

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).toBeChecked();
		await expect(page.locator(address)).toHaveValue(
			'e2e-updates@example.com'
		);

		expect(await saveAndReadSubscribeRequests(page)).toEqual([
			'e2e-updates@example.com',
		]);

		// Unchecking sends nothing either.
		await page.goto(settingsUrl);
		await page.locator(checkbox).uncheck();

		expect(await saveAndReadSubscribeRequests(page)).toEqual([
			'e2e-updates@example.com',
		]);

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).not.toBeChecked();
	});

	test('an invalid address sends nothing and shows an error', async ({
		page,
	}) => {
		await page.goto(settingsUrl);
		await page.locator(checkbox).check();
		await page.locator(address).fill('not-an-email');

		expect(await saveAndReadSubscribeRequests(page)).toEqual([]);
		await expect(
			page.getByText('Enter a valid email address and save again.')
		).toBeVisible();

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).not.toBeChecked();
	});
});
