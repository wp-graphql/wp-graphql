import { test, expect } from '@playwright/test';
import {
	loginToWordPressAdmin,
	setPluginActive,
	wpAdminUrl,
} from '../utils.js';

/**
 * @file site-health.spec.js
 * @description Checks that WPGraphQL's recommendations show in Tools > Site Health and follow the
 * settings. WPGraphQL only adds them on production sites, and the tests site is a local one, so the
 * `settings-page-spec/site-health-spec.php` test plugin is active during these tests to add them as
 * if the site were in production. The original setting is restored afterwards.
 */

const siteHealthPluginFile = 'settings-page-spec/site-health-spec.php';

const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings`;
const siteHealthUrl = `${wpAdminUrl}/site-health.php`;

const depthEnabled = '#wpuf-graphql_general_settings\\[query_depth_enabled\\]';

/**
 * Turns the query depth limit on or off from the settings page.
 *
 * @param {import('@playwright/test').Page} page    The Playwright page object.
 * @param {boolean}                         enabled Whether to limit query depth.
 */
async function setDepthLimit(page, enabled) {
	await page.goto(settingsUrl, { waitUntil: 'networkidle' });
	await page.locator(depthEnabled).setChecked(enabled);
	await page.getByRole('button', { name: 'Save Changes' }).click();
	await page.waitForURL(/settings-updated=true/);
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
	let originalDepthEnabled;

	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await setPluginActive(page, siteHealthPluginFile, true);
		await page.goto(settingsUrl, { waitUntil: 'networkidle' });
		originalDepthEnabled = await page.locator(depthEnabled).isChecked();
	});

	test.afterEach(async ({ page }) => {
		await setDepthLimit(page, originalDepthEnabled);
		await setPluginActive(page, siteHealthPluginFile, false);
	});

	test('recommends limiting query depth and links to the setting', async ({
		page,
	}) => {
		await setDepthLimit(page, false);
		await openSiteHealth(page);

		const recommended = page.locator('#health-check-issues-recommended');
		const issue = recommended.getByRole('button', {
			name: 'WPGraphQL does not limit query depth',
		});
		await expect(issue).toBeVisible();

		await issue.click();
		await expect(
			recommended.getByRole('link', {
				name: 'Review the query depth limit',
			})
		).toHaveAttribute('href', settingsUrl);

		// A recommendation, never a critical issue.
		await expect(
			page
				.locator('#health-check-issues-critical')
				.getByRole('button', { name: /WPGraphQL/ })
		).toHaveCount(0);
	});

	test('lists the query depth limit as passed once it is on', async ({
		page,
	}) => {
		await setDepthLimit(page, true);
		await openSiteHealth(page);

		await expect(
			page
				.locator('#health-check-issues-recommended')
				.getByRole('button', {
					name: 'WPGraphQL does not limit query depth',
				})
		).toHaveCount(0);

		await page.getByRole('button', { name: 'Passed tests' }).click();
		await expect(
			page.locator('#health-check-issues-good').getByRole('button', {
				name: 'WPGraphQL limits query depth',
			})
		).toBeVisible();
	});
});
