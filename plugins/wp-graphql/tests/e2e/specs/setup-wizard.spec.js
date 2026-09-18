import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file setup-wizard.spec.js
 * @description Walks through the setup wizard, saves a change, and checks that the settings page
 * reflects it. The original values are restored afterwards.
 */

const settingsUrl = `${wpAdminUrl}/admin.php?page=graphql-settings`;
const wizardUrl = `${wpAdminUrl}/admin.php?page=graphql-setup-wizard`;

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

test.describe('Setup wizard', () => {
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
		// The wizard has no menu item of its own. It's reached from the Settings page.
		await page.goto(settingsUrl, { waitUntil: 'networkidle' });
		await expect(
			page.locator('#adminmenu a[href$="page=graphql-setup-wizard"]')
		).toHaveCount(0);

		await page.getByRole('link', { name: 'Run the setup wizard' }).click();
		await page.waitForLoadState('networkidle');
		await expect(page).toHaveURL(wizardUrl);
		await expect(page).toHaveTitle(/WPGraphQL Setup Wizard/);

		// While the wizard is open, GraphQL > Settings is highlighted in the menu.
		await expect(
			page.locator('#adminmenu .current a[href$="page=graphql-settings"]')
		).toHaveCount(1);

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

		await page.getByRole('button', { name: 'Save settings' }).click();
		// The notice text is also announced in a screen reader live region, so check the visible notice.
		await expect(
			page
				.locator('.components-notice')
				.getByText('Your settings are saved.')
		).toBeVisible();

		const saved = await readDepthSettings(page);
		expect(saved.enabled).toBe(true);
		expect(saved.max).toBe('23');
	});
});
