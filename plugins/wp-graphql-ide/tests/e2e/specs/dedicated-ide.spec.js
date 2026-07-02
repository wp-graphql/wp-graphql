import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	readQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Dedicated IDE page', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('renders the IDE root, editor, and tab strip', async ({ page }) => {
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

	test('clicking Execute query fires a GraphQL request', async ({ page }) => {
		await typeQuery(page, '{ posts { nodes { id } } }');

		const requestPromise = page.waitForRequest(
			(req) => /graphql/i.test(req.url()) && req.method() === 'POST',
			{ timeout: 10000 }
		);
		await page.getByRole('button', { name: 'Execute query' }).click();
		const request = await requestPromise;
		expect(request.url()).toMatch(/graphql/);
	});

	test('Tab indents inside the editor instead of moving focus', async ({
		page,
	}) => {
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
		const initialCount = await page.locator(selectors.tab).count();
		await page.click(selectors.addTab);
		await expect(page.locator(selectors.tab)).toHaveCount(initialCount + 1);
	});

	test('typing a query without a name shows the first field as the tab title', async ({
		page,
	}) => {
		// Open a fresh tab so we don't collide with leftover state.
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		// Title derivation kicks in once the query has content. The
		// tab strip should reflect the first top-level field.
		await expect(
			page.locator(`${selectors.tab}.is-active .wpgraphql-ide-tab-label`)
		).toContainText('posts', { timeout: 5000 });
	});

	test('a temp tab is marked is-temp (italic title, no dirty bullet)', async ({
		page,
	}) => {
		await page.click(selectors.addTab);

		// Temp drafts are autopersisted to localStorage on every keystroke,
		// so we don't show the dirty bullet on them — it would always be
		// on, which makes it useless. Italic title (driven by `is-temp`)
		// signals "this hasn't been saved as a real draft yet" instead.
		const activeTab = page.locator(`${selectors.tab}.is-active`);
		await expect(activeTab).toHaveClass(/is-temp/);
		await expect(activeTab.locator('.wpgraphql-ide-tab-dirty')).toHaveCount(
			0
		);
	});
});
