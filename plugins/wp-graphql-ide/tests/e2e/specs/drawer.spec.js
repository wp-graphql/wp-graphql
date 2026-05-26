import {
	loginToWordPressAdmin,
	openDrawer,
	ensureDocumentOpen,
	typeQuery,
	readQuery,
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

test.describe('Drawer mode — IDE functionality parity with the dedicated page', () => {
	// Mirror coverage from dedicated-ide.spec.js so anything that works on
	// /wp-admin/admin.php?page=graphql-ide also works through the drawer.
	// The drawer renders into a Vaul portal at document.body, so the same
	// editor + selectors apply — we just have to open the drawer first.
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await page.goto(wpAdminUrl, { waitUntil: 'domcontentloaded' });
		await openDrawer(page);
		await ensureDocumentOpen(page);
	});

	test('renders the IDE root, editor, and tab strip inside the drawer', async ({
		page,
	}) => {
		await expect(page.locator(selectors.drawerContent)).toBeVisible();
		await expect(page.locator(selectors.ideRoot)).toBeVisible();
		await expect(
			page.locator(selectors.graphqlEditor).first()
		).toBeVisible();
		await expect(page.locator(selectors.tabRow)).toBeVisible();
	});

	test('typing into the editor updates the editor content', async ({
		page,
	}) => {
		await typeQuery(page, '{ posts { nodes { id } } }');
		const value = await readQuery(page);
		expect(value).toContain('posts');
	});

	test('clicking Execute query fires a GraphQL request from inside the drawer', async ({
		page,
	}) => {
		await typeQuery(page, '{ posts { nodes { id } } }');

		const requestPromise = page.waitForRequest(
			(req) => /graphql/i.test(req.url()) && req.method() === 'POST',
			{ timeout: 10000 }
		);
		await page.getByRole('button', { name: 'Execute query' }).click();
		const request = await requestPromise;
		expect(request.url()).toMatch(/graphql/);
	});

	test('autocomplete dropdown renders above the drawer (regression guard for z-index)', async ({
		page,
	}) => {
		// Vaul gives [data-vaul-drawer] z-index: 999999. CodeMirror's
		// `tooltips({ parent: document.body })` portals autocomplete +
		// hover popups out of the editor, but they then have to clear the
		// drawer's stacking. Without an explicit bump, .cm-tooltip used
		// z-index: 100000 and the popup painted behind the drawer
		// content — so users typing inside the drawer saw nothing while
		// the same query on the dedicated page showed suggestions.
		// Asserts both (a) the popup mounts and (b) hit-testing at its
		// center lands inside the popup (not the drawer).
		await typeQuery(page, '{ posts { nodes { id title a');

		const popup = page.locator('.cm-tooltip-autocomplete').first();
		await expect(popup).toBeVisible({ timeout: 5000 });

		const isHitTestOnTop = await popup.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			const cx = Math.floor(rect.left + rect.width / 2);
			const cy = Math.floor(rect.top + rect.height / 2);
			const hit = document.elementFromPoint(cx, cy);
			return hit !== null && (el === hit || el.contains(hit));
		});

		expect(
			isHitTestOnTop,
			'autocomplete popup is occluded by the drawer at its visual center — z-index regression'
		).toBe(true);
	});
});
