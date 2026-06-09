import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const countTabs = (page) => page.locator(selectors.tab).count();

test.describe('Tab management', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('closing a tab removes it from the strip and leaves the next tab active', async ({
		page,
	}) => {
		// Stamp each tab's content so we can assert which one stayed.
		const stampA = `a-${Date.now()}`;
		const stampB = `b-${Date.now()}`;
		await page.click(selectors.addTab);
		await typeQuery(
			page,
			`{ posts(where: {search: "${stampA}"}) { nodes { id } } }`
		);
		await page.click(selectors.addTab);
		await typeQuery(
			page,
			`{ posts(where: {search: "${stampB}"}) { nodes { id } } }`
		);

		const tabsBefore = await countTabs(page);
		expect(tabsBefore).toBeGreaterThanOrEqual(2);

		// Close the active tab — the close affordance is a `<span>` with
		// aria-label `Close <title>`. Title for an anonymous shorthand
		// query defaults to the first field (`posts`).
		const activeCloser = page
			.locator(`${selectors.tab}.is-active`)
			.getByLabel(/^Close /);
		await activeCloser.click();

		await expect(page.locator(selectors.tab)).toHaveCount(tabsBefore - 1);
		// Active tab is still some tab (didn't leave us with no active).
		await expect(page.locator(`${selectors.tab}.is-active`)).toHaveCount(1);
	});

	test('open tabs persist across a full page reload', async ({ page }) => {
		const stamp = Date.now();
		const body = `{ posts(where: {search: "persist-${stamp}"}) { nodes { id } } }`;
		await page.click(selectors.addTab);
		await typeQuery(page, body);

		const countBefore = await countTabs(page);
		await page.reload({ waitUntil: 'domcontentloaded' });
		await page.waitForSelector(selectors.tabRow, {
			state: 'visible',
			timeout: 10000,
		});

		// Same number of tabs after reload (unsaved-tabs storage hydrates
		// from localStorage on mount).
		await expect(page.locator(selectors.tab)).toHaveCount(countBefore);
		// The body we typed is still in some tab's content. Click each
		// tab until the editor shows our marker, then assert.
		const tabs = page.locator(selectors.tab);
		const total = await tabs.count();
		let found = false;
		for (let i = 0; i < total; i++) {
			await tabs.nth(i).click();
			const editor = page
				.locator('.wpgraphql-ide-graphql-editor .cm-content')
				.first();
			const text = await editor.innerText();
			if (text.includes(`persist-${stamp}`)) {
				found = true;
				break;
			}
		}
		expect(found).toBe(true);
	});
});
