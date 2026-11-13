import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file data-sharing-notice.spec.js
 * @description Checks the Data Sharing notice that asks about usage data and email updates in place
 * of Appsero's opt-in notice: the two questions are separate, answering usage data brings the email
 * question back once, and the email form sends one subscribe request. The
 * `settings-page-spec/usage-tracking-spec.php` test plugin blocks usage tracking requests and resets
 * the opt-in state, and `settings-page-spec/email-updates-spec.php` answers subscribe requests in place
 * of the telemetry service and records them.
 */

const trackingPluginFile = 'settings-page-spec/usage-tracking-spec.php';
const emailUpdatesPluginFile = 'settings-page-spec/email-updates-spec.php';
const dashboardUrl = `${wpAdminUrl}/index.php`;
const notice = '#graphql-data-sharing-notice';
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
 * Reads the real opt-in state the usage tracking test plugin prints.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<string>} `yes` or `no`.
 */
async function trackingState(page) {
	return (
		await page.locator('#wpgraphql-e2e-tracking-state').textContent()
	).trim();
}

/**
 * Reads the subscribe requests the email updates test plugin recorded.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<Array<{email: string, hashes: string[]}>>} Each subscribe request.
 */
async function subscribeRequests(page) {
	return JSON.parse(
		await page
			.locator('#wpgraphql-e2e-email-updates-requests')
			.textContent()
	);
}

test.describe('Data sharing notice', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await setPluginActive(page, trackingPluginFile, true);
		await setPluginActive(page, emailUpdatesPluginFile, true);
		await page.goto(
			`${wpAdminUrl}/plugins.php?wpgraphql_e2e_email_updates=reset`
		);
		await page.goto(
			`${wpAdminUrl}/plugins.php?wpgraphql_e2e_tracking=undecided`
		);
	});

	test.afterEach(async ({ page }) => {
		await page.goto(
			`${wpAdminUrl}/plugins.php?wpgraphql_e2e_email_updates=reset`
		);
		await page.goto(`${wpAdminUrl}/plugins.php?wpgraphql_e2e_tracking=no`);
		await setPluginActive(page, emailUpdatesPluginFile, false);
		await setPluginActive(page, trackingPluginFile, false);
	});

	test('asks both questions separately, in place of the Appsero notice', async ({
		page,
	}) => {
		await page.goto(dashboardUrl);

		await expect(page.locator(notice)).toHaveCount(1);
		await expect(page.locator(notice)).toContainText(
			'Help improve WPGraphQL? Share usage data with the WPGraphQL team.'
		);
		await expect(page.locator(notice)).toContainText(
			"It doesn't sign anyone up for emails."
		);
		await expect(
			page.locator(notice).getByRole('button', { name: 'Allow' })
		).toBeVisible();
		await expect(
			page.locator(notice).getByRole('button', { name: 'No thanks' })
		).toBeVisible();
		await expect(
			page
				.locator(notice)
				.getByRole('button', { name: 'Send confirmation email' })
		).toBeVisible();

		// WPGraphQL's Appsero notice links to an opt-in URL named for its client slug; it isn't
		// shown. Plugins not on the Data Sharing tab yet, such as Smart Cache when it's active,
		// still show their own, so only WPGraphQL's is checked.
		await expect(
			page.locator('a[href*="wp-graphql_tracker_optin=true"]')
		).toHaveCount(0);
	});

	test('allowing opts in, sends no email request, and then offers email updates once', async ({
		page,
	}) => {
		await page.goto(dashboardUrl);
		await page
			.locator(notice)
			.getByRole('button', { name: 'Allow' })
			.click();
		await page.waitForURL(/graphql_data_sharing_notice=decided/);

		expect(await trackingState(page)).toBe('yes');
		expect(await subscribeRequests(page)).toEqual([]);

		await expect(page.locator(notice)).toContainText(
			'Your choice was saved. Want email updates too?'
		);
		await expect(
			page.locator(notice).getByRole('button', { name: 'Allow' })
		).toHaveCount(0);

		// Only once: the next page doesn't ask again.
		await page.goto(dashboardUrl);
		await expect(page.locator(notice)).toHaveCount(0);
	});

	test('the email form sends one subscribe request and says what happened', async ({
		page,
	}) => {
		await page.goto(dashboardUrl);
		await page
			.locator(notice)
			.getByRole('textbox', { name: 'Email address' })
			.fill('e2e-notice@example.com');
		await page
			.locator(notice)
			.getByRole('button', { name: 'Send confirmation email' })
			.click();
		await page.waitForURL(/graphql_data_sharing_notice=emailed/);

		expect(await subscribeRequests(page)).toEqual([
			{ email: 'e2e-notice@example.com', hashes: [coreHash] },
		]);
		await expect(
			page.getByText(
				'A confirmation email was requested for e2e-notice@example.com for WPGraphQL updates.'
			)
		).toBeVisible();

		// Usage data is still unanswered, and signing up for email didn't opt the site in.
		expect(await trackingState(page)).toBe('no');
		await expect(
			page.locator(notice).getByRole('button', { name: 'Allow' })
		).toBeVisible();
	});

	test('no thanks declines without opting in', async ({ page }) => {
		await page.goto(dashboardUrl);
		await page
			.locator(notice)
			.getByRole('button', { name: 'No thanks' })
			.click();
		await page.waitForURL(/graphql_data_sharing_notice=decided/);

		expect(await trackingState(page)).toBe('no');
		await expect(page.locator(notice)).toContainText(
			'Your choice was saved. Want email updates too?'
		);

		await page.goto(dashboardUrl);
		await expect(page.locator(notice)).toHaveCount(0);
	});
});
