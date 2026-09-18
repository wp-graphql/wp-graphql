import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file settings-review.spec.js
 * @description Walks through the settings review, saves a change, and checks that the settings page
 * reflects it. The original values are restored afterwards.
 */

const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings`;
const reviewUrl = `${wpAdminUrl}/admin.php?page=graphql-settings-review`;

const selectors = {
	depthEnabled: '#wpuf-graphql_general_settings\\[query_depth_enabled\\]',
	depthMax: 'input[name="graphql_general_settings[query_depth_max]"]',
};

/**
 * Reads the query depth settings from the settings page.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<{enabled: boolean, max: string}>} The saved values.
 */
async function readDepthSettings(page) {
	await page.goto(settingsUrl, { waitUntil: 'networkidle' });
	return {
		enabled: await page.locator(selectors.depthEnabled).isChecked(),
		max: await page.locator(selectors.depthMax).inputValue(),
	};
}

test.describe('Settings review', () => {
	let original;

	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		original = await readDepthSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await page.goto(settingsUrl, { waitUntil: 'networkidle' });
		await page.locator(selectors.depthEnabled).setChecked(true);
		await page.locator(selectors.depthMax).fill(original.max);
		await page.locator(selectors.depthEnabled).setChecked(original.enabled);
		await page.getByRole('button', { name: 'Save Changes' }).click();
		await page.waitForLoadState('networkidle');
	});

	test('walks through the steps and saves the chosen settings', async ({
		page,
	}) => {
		// The Settings page links to the review.
		await page.goto(settingsUrl, { waitUntil: 'networkidle' });
		await page.getByRole('link', { name: 'Review your settings' }).click();
		await page.waitForLoadState('networkidle');
		await expect(page).toHaveURL(reviewUrl);
		await expect(page).toHaveTitle(/Review WPGraphQL Settings/);

		await expect(
			page.getByRole('heading', {
				name: 'Review how your GraphQL API is set up',
			})
		).toBeVisible();

		await page.getByRole('button', { name: 'Start' }).click();
		await expect(
			page.getByRole('heading', { name: 'Access' })
		).toBeVisible();

		await page.getByRole('button', { name: 'Next' }).click();
		await expect(
			page.getByRole('heading', { name: 'Request limits' })
		).toBeVisible();

		// The maximum depth only shows while depth limiting is on.
		const depthToggle = page.getByLabel('Limit query depth');
		const maxDepth = page.getByLabel('Maximum query depth');

		await depthToggle.setChecked(false);
		await expect(maxDepth).toBeHidden();

		await depthToggle.setChecked(true);
		await expect(maxDepth).toBeVisible();
		await maxDepth.fill('23');

		await page.getByRole('button', { name: 'Next' }).click();
		await expect(
			page.getByRole('heading', { name: 'Diagnostics' })
		).toBeVisible();

		await page.getByRole('button', { name: 'Next' }).click();
		await expect(
			page.getByRole('heading', { name: 'Review and save' })
		).toBeVisible();

		const maxDepthRow = page.getByRole('row', {
			name: /Maximum query depth/,
		});
		await expect(maxDepthRow).toContainText('23');

		// Only the changed setting is sent, so unchanged settings keep their current stored value.
		const saveRequest = page.waitForRequest(
			(request) =>
				request.method() === 'POST' &&
				request.url().includes('settings-review')
		);
		await page.getByRole('button', { name: 'Save settings' }).click();
		const sent = (await saveRequest).postDataJSON();
		expect(sent.status).toBe('completed');
		expect(Object.keys(sent.settings.graphql_general_settings)).toContain(
			'query_depth_max'
		);
		expect(sent.settings.graphql_general_settings).not.toHaveProperty(
			'batch_limit'
		);
		// The notice text is also announced in a screen reader live region, so check the visible notice.
		await expect(
			page
				.locator('.components-notice')
				.getByText('Your settings are saved.')
		).toBeVisible();

		const saved = await readDepthSettings(page);
		expect(saved.enabled).toBe(true);
		expect(saved.max).toBe('23');

		// Once the review is saved it has no GraphQL menu item of its own.
		await expect(
			page.locator('#adminmenu a[href$="page=graphql-settings-review"]')
		).toHaveCount(0);

		// Opened again from the Settings page, GraphQL > Settings is highlighted in the menu.
		await page.getByRole('link', { name: 'Review your settings' }).click();
		await page.waitForLoadState('networkidle');
		await expect(page).toHaveURL(reviewUrl);
		await expect(
			page.locator('#adminmenu .current a[href$="page=graphql-settings"]')
		).toHaveCount(1);
	});
});
