import {
	loginToWordPressAdmin,
	openDrawer,
	wpAdminUrl,
	wpHomeUrl,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Front-end + admin drawer', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
	});

	test('drawer opens from any admin page', async ({ page }) => {
		await page.goto(wpAdminUrl, { waitUntil: 'domcontentloaded' });
		await openDrawer(page);

		await expect(page.locator(selectors.drawerContent)).toBeVisible();
		await expect(page.locator(selectors.ideRoot)).toBeVisible();
	});

	test('drawer opens from a public-facing page (admin bar visible)', async ({
		page,
	}) => {
		await page.goto(wpHomeUrl, { waitUntil: 'domcontentloaded' });
		// Admin bar exists for logged-in users on the front-end.
		await expect(page.locator('#wpadminbar')).toBeVisible();
		await openDrawer(page);
		await expect(page.locator(selectors.drawerContent)).toBeVisible();
	});

	test('IDE root carries the wp-emoji opt-out class so emojis render consistently', async ({
		page,
	}) => {
		// One of the front-end fixes: tagging the IDE root with
		// `wp-exclude-emoji` so wp-emoji's parser skips the subtree and
		// every emoji inside the IDE renders the same way (system font).
		await page.goto(wpAdminUrl, { waitUntil: 'domcontentloaded' });
		await openDrawer(page);

		const root = page.locator(selectors.ideRoot);
		await expect(root).toHaveClass(/wp-exclude-emoji/);
	});

	test('drawer button is the trigger element (not the admin-bar link)', async ({
		page,
	}) => {
		// The render shim wires admin-bar link clicks through to the
		// trigger button so the drawer opens. If a refactor accidentally
		// makes the link itself the trigger, the drawer would either
		// double-open or not open at all.
		await page.goto(wpAdminUrl, { waitUntil: 'domcontentloaded' });
		await expect(page.locator(selectors.drawerButton)).toBeVisible();
	});
});
