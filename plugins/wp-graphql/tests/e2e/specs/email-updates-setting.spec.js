import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file email-updates-setting.spec.js
 * @description Checks the Email Updates rows of the Data Sharing tab: they are off by default and
 * separate from the usage data toggle, checking a box and saving sends one subscribe request and
 * re-saving sends no more, and the page says when email updates aren't available yet. The
 * `settings-page-spec/email-updates-spec.php` test plugin answers subscribe requests in place of the
 * telemetry service, records each one, and sets its answers and clears the email updates state from
 * the `wpgraphql_e2e_email_updates` query argument.
 */

const emailUpdatesPluginFile = 'settings-page-spec/email-updates-spec.php';
const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings#graphql_data_sharing_settings`;
// The fields are named for WPGraphQL's Appsero client slug, its folder name.
const checkbox =
	'#wpuf-graphql_data_sharing_settings\\[email_updates_for_wp-graphql\\]';
const address = '#graphql_data_sharing_settings\\[email_updates_address\\]';
const usageToggle =
	'#wpuf-graphql_data_sharing_settings\\[share_usage_data_wp-graphql\\]';
const coreHash = 'cd0d1172-95a0-4460-a36a-2c303807c9ef';

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
 * Runs an action of the test plugin: `reset`, `accept` or `unavailable`.
 *
 * @param {import('@playwright/test').Page} page   The Playwright page object.
 * @param {string}                          action The action.
 */
async function emailUpdatesSpec(page, action) {
	await page.goto(
		`${wpAdminUrl}/plugins.php?wpgraphql_e2e_email_updates=${action}`
	);
}

/**
 * Saves the settings page and returns the subscribe requests sent so far.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<Array<{email: string, hashes: string[]}>>} Each subscribe request.
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
		await emailUpdatesSpec(page, 'reset');
	});

	test.afterEach(async ({ page }) => {
		await emailUpdatesSpec(page, 'reset');
		await setPluginActive(page, emailUpdatesPluginFile, false);
	});

	test('the rows follow the usage data rows, unchecked, with the address prefilled', async ({
		page,
	}) => {
		await page.goto(settingsUrl);

		const rows = page.locator('#graphql_data_sharing_settings tr');
		const headings = await rows.locator('th').allTextContents();
		const emailUpdates = headings.indexOf('Email Updates');

		expect(emailUpdates).toBeGreaterThan(headings.indexOf('WPGraphQL'));
		expect(headings[emailUpdates + 1]).toBe('Email Address');

		const row = page.locator('#graphql_data_sharing_settings tr', {
			has: page.locator(checkbox),
		});
		await expect(row.locator('th')).toHaveText('WPGraphQL');
		await expect(row.locator('td label')).toHaveText(
			'Email me about security releases and breaking changes in WPGraphQL'
		);

		await expect(page.locator(checkbox)).not.toBeChecked();
		await expect(page.locator(address)).toBeVisible();
		// Nothing is filled in; the account's address is only a suggestion.
		await expect(page.locator(address)).toHaveValue('');
		await expect(page.locator(address)).toHaveAttribute(
			'placeholder',
			/@/
		);
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

		const sent = [{ email: 'e2e-updates@example.com', hashes: [coreHash] }];

		expect(await saveAndReadSubscribeRequests(page)).toEqual(sent);
		await expect(
			page.getByText(
				'A confirmation email was requested for e2e-updates@example.com for WPGraphQL updates.'
			)
		).toBeVisible();

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).toBeChecked();
		await expect(page.locator(address)).toHaveValue(
			'e2e-updates@example.com'
		);

		expect(await saveAndReadSubscribeRequests(page)).toEqual(sent);

		// Unchecking sends nothing either.
		await page.goto(settingsUrl);
		await page.locator(checkbox).uncheck();

		expect(await saveAndReadSubscribeRequests(page)).toEqual(sent);

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).not.toBeChecked();
	});

	test('says so when email updates are not available yet', async ({
		page,
	}) => {
		await emailUpdatesSpec(page, 'unavailable');

		await page.goto(settingsUrl);
		await page.locator(checkbox).check();
		await page.locator(address).fill('e2e-updates@example.com');

		expect(await saveAndReadSubscribeRequests(page)).toHaveLength(1);
		await expect(
			page.getByText("Email updates aren't available for WPGraphQL yet.")
		).toBeVisible();

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
			page.getByText('Enter a valid email address and try again.')
		).toBeVisible();

		await page.goto(settingsUrl);
		await expect(page.locator(checkbox)).not.toBeChecked();
	});
});
