import { loginToWordPressAdmin, selectors, wpAdminUrl } from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Checks that WPGraphQL admin notices on the dedicated IDE page are shown above the IDE, push it
 * down however tall they are, and never make the page scroll.
 *
 * Notices come from the `settings-page-spec/admin-notices-spec.php` test plugin in the core
 * plugin's e2e folder, which registers them when the `wpgraphql_e2e_admin_notices` query argument
 * is set.
 */

const noticesPluginFile = 'settings-page-spec/admin-notices-spec.php';

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

test.describe('Admin notices on the dedicated IDE page', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await setPluginActive(page, noticesPluginFile, true);
	});

	test.afterEach(async ({ page }) => {
		await setPluginActive(page, noticesPluginFile, false);
	});

	test('notices are shown above the IDE without covering it or scrolling the page', async ({
		page,
	}) => {
		await page.goto(
			`${wpAdminUrl}/admin.php?page=graphql-ide&wpgraphql_e2e_admin_notices=3`,
			{ waitUntil: 'domcontentloaded' }
		);

		const notices = page.locator('.wpgraphql-admin-notice');
		const ide = page.locator(selectors.ideRoot);

		await expect(notices).toHaveCount(3);
		await expect(ide).toBeVisible();

		const lastNotice = await notices.last().boundingBox();
		const ideBox = await ide.boundingBox();
		const viewportHeight = page.viewportSize().height;

		// The IDE starts below the last notice.
		expect(ideBox.y).toBeGreaterThanOrEqual(
			lastNotice.y + lastNotice.height
		);

		// The IDE fills the rest of the window and ends at its bottom, not below it.
		expect(Math.round(ideBox.y + ideBox.height)).toBe(viewportHeight);

		// The page doesn't scroll.
		const scrollHeight = await page.evaluate(
			() => document.documentElement.scrollHeight
		);
		expect(scrollHeight).toBeLessThanOrEqual(viewportHeight);
	});
});
