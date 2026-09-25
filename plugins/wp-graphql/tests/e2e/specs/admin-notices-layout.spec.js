import { test, expect } from '@playwright/test';
import { loginToWordPressAdmin, wpAdminUrl } from '../utils.js';

/**
 * @file admin-notices-layout.spec.js
 * @description Checks that WPGraphQL admin notices on the GraphiQL IDE page are shown above the IDE,
 * push it down however tall they are, and never make the page scroll. Notices come from the
 * `settings-page-spec/admin-notices-spec.php` test plugin, which registers them when the
 * `wpgraphql_e2e_admin_notices` query argument is set.
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

test.describe('Admin notices on the GraphiQL IDE page', () => {
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
			`${wpAdminUrl}/admin.php?page=graphiql-ide&wpgraphql_e2e_admin_notices=3`,
			{ waitUntil: 'networkidle' }
		);

		const notices = page.locator('.wpgraphql-admin-notice');
		const ide = page.locator('[data-testid="wp-graphiql-wrapper"]');

		await expect(notices).toHaveCount(3);
		await expect(ide).toBeVisible();

		const lastNotice = await notices.last().boundingBox();
		const ideBox = await ide.boundingBox();
		const viewportHeight = page.viewportSize().height;

		// The IDE starts below the last notice.
		expect(ideBox.y).toBeGreaterThanOrEqual(
			lastNotice.y + lastNotice.height
		);

		// The IDE ends at the bottom of the window, not below it.
		expect(Math.round(ideBox.y + ideBox.height)).toBeLessThanOrEqual(
			viewportHeight
		);

		// The page doesn't scroll.
		const scrollHeight = await page.evaluate(
			() => document.documentElement.scrollHeight
		);
		expect(scrollHeight).toBeLessThanOrEqual(viewportHeight);
	});
});
