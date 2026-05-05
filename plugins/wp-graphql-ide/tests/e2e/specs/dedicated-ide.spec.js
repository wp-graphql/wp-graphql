import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	typeQuery,
	readQuery,
	runQuery,
	waitForGraphQLResponse,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Dedicated IDE page', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
	});

	test('renders the IDE root, editor, and tab strip', async ({ page }) => {
		await visitDedicatedIde(page);

		await expect(page.locator(selectors.ideRoot)).toBeVisible();
		await expect(page.locator(selectors.graphqlEditor).first()).toBeVisible();
		await expect(page.locator(selectors.tabRow)).toBeVisible();
	});

	test('typing into the editor updates the editor content', async ({
		page,
	}) => {
		await visitDedicatedIde(page);
		await typeQuery(page, '{ posts { nodes { id } } }');
		const value = await readQuery(page);
		expect(value).toContain('posts');
	});

	test('Cmd/Ctrl+Enter executes the query', async ({ page }) => {
		await visitDedicatedIde(page);

		await typeQuery(page, '{ posts { nodes { id } } }');

		const responsePromise = waitForGraphQLResponse(page);
		await runQuery(page);
		const response = await responsePromise;

		expect(response.status()).toBeLessThan(500);
	});

	test('Tab indents inside the editor instead of moving focus', async ({
		page,
	}) => {
		await visitDedicatedIde(page);

		const editor = page.locator(selectors.graphqlEditorContent).first();
		await editor.click();
		await page.keyboard.type('{');
		await page.keyboard.press('Enter');
		await page.keyboard.press('Tab');
		await page.keyboard.type('posts');

		const value = await readQuery(page);
		// Tab should produce indentation (a tab character or 2+ spaces)
		// rather than moving focus out of the editor.
		expect(value).toMatch(/[\t ]+posts/);
	});

	test('clicking the new-tab button opens an additional tab', async ({
		page,
	}) => {
		await visitDedicatedIde(page);

		const initialCount = await page.locator(selectors.tab).count();
		await page.click(selectors.addTab);
		await expect(page.locator(selectors.tab)).toHaveCount(initialCount + 1);
	});

	test('typing a query without a name shows the first field as the tab title', async ({
		page,
	}) => {
		await visitDedicatedIde(page);

		// Open a fresh tab so we don't collide with leftover state.
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		// Title derivation kicks in once the query has content. The
		// tab strip should reflect the first top-level field.
		await expect(
			page.locator(`${selectors.tab}.is-active .wpgraphql-ide-tab-label`)
		).toContainText('posts', { timeout: 5000 });
	});

	test('a temp tab shows the unsaved-dirty indicator', async ({ page }) => {
		await visitDedicatedIde(page);

		await page.click(selectors.addTab);

		await expect(
			page
				.locator(`${selectors.tab}.is-active`)
				.locator('.wpgraphql-ide-tab-dirty')
		).toBeVisible();
	});
});
