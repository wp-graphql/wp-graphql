import { loginToWordPressAdmin, typeQuery, loadGraphiQL } from '../utils.js';
import { test, expect } from '@playwright/test';

const selectors = {
	graphiqlContainer: '[data-testid="wp-graphiql-wrapper"]',
	// Result JSON lives under .resultWrap (graphiql@1.x); inner section is .result-window.
	graphiqlResponse: '.resultWrap',
	executeQueryButton: '.execute-button',
	queryInput: 'section[aria-label="Query Editor"] .CodeMirror',
	variablesInput: '[aria-label="Variables"] .CodeMirror',
};

const responseTimeout = 30_000;

// Login to WordPress before each test
test.beforeEach(async ({ page }) => {
	await loginToWordPressAdmin(page);
	await page.evaluate(() => localStorage.clear());
});

test.describe('GraphiQL', () => {
	test('it executes query', async ({ page }) => {
		await loadGraphiQL(page);
		await typeQuery(page, `{posts{nodes{id}}}`);
		await page.click(selectors.executeQueryButton);
		const response = page.locator(selectors.graphiqlResponse);
		await expect(response).toContainText('posts', { timeout: responseTimeout });
		await expect(response).not.toContainText('errors');
	});

	test('it renders errors when errors are expected', async ({ page }) => {
		await loadGraphiQL(page);
		await typeQuery(page, `{nonExistentFieldThatShouldError}`);
		await page.click(selectors.executeQueryButton);
		const response = page.locator(selectors.graphiqlResponse);
		await expect(response).toContainText('errors', { timeout: responseTimeout });
	});

	test('it loads with custom query from url query params', async ({
		page,
	}) => {
		// Generate a unique alias to use in the query param
		// and test that it is present in the query editor
		// when it loads
		const alias = 'alias' + Math.random().toString(36).substring(7);

		await loadGraphiQL(page, {
			query: `query TestFromUri { posts { nodes { id ${alias}:title } } }`,
		});
		const editor = page.locator(selectors.graphiqlContainer);
		await expect(editor).toContainText('TestFromUri');
		await expect(editor).toContainText('posts');
		await expect(editor).toContainText('nodes');
		await expect(editor).toContainText('id');
		await expect(editor).toContainText(alias);
	});

	test('it loads with the query composer hidden by default', async ({
		page,
	}) => {
		await loadGraphiQL(page, { query: `{posts{nodes{id}}}` });
		const queryComposer = await page.locator('.query-composer-wrap');
		await expect(queryComposer).not.toBeVisible();
	});

	test.skip('it loads with query composer open if queryParam says to', async ({
		page,
	}) => {
		await loadGraphiQL(page, {
			query: `{posts{nodes{id}}}`,
			isQueryComposerOpen: 'true',
		});
		const queryComposer = await page.locator('.query-composer-wrap');
		await expect(queryComposer).toBeVisible();
	});

	test('opens query composer on click', async ({ page }) => {
		await loadGraphiQL(page);
		const button = page.locator(
			"xpath=//button[contains(text(), 'Query Composer')]"
		);
		const queryComposer = await page.locator('.query-composer-wrap');

		// composer should be closed by default
		await expect(queryComposer).not.toBeVisible();

		// clicking the button should open it
		await button.click();
		await page.waitForTimeout(1000);
		await expect(queryComposer).toBeVisible();

		// clicking again should close it
		await button.click();
		await page.waitForTimeout(1000);
		await expect(queryComposer).not.toBeVisible();
	});

	test('documentation explorer can be toggled open and closed', async ({
		page,
	}) => {
		await loadGraphiQL(page);
		// The explorer <section> stays in the DOM when the pane is collapsed; visibility
		// is driven by the layout, not display:none. Assert via the toolbar controls instead.
		const openDocs = page.getByRole('button', {
			name: 'Open Documentation Explorer',
		});
		const closeDocs = page.getByRole('button', {
			name: 'Close Documentation Explorer',
		});

		await expect(openDocs).toBeVisible();

		await openDocs.click();
		await expect(closeDocs).toBeVisible({ timeout: 10_000 });

		await closeDocs.click();
		await expect(openDocs).toBeVisible({ timeout: 10_000 });
	});

	test('prettify button formats the query', async ({ page }) => {
		await loadGraphiQL(page);

		// Type an unformatted query (all on one line, no spacing)
		const unformattedQuery = '{posts{nodes{id title date}}}';
		await typeQuery(page, unformattedQuery);

		// Click the Prettify button
		const prettifyButton = page.locator(
			"xpath=//button[contains(text(), 'Prettify')]"
		);
		await prettifyButton.click();
		await page.waitForTimeout(500);

		// Get the query after prettifying
		const queryAfter = await page.evaluate(() => {
			const el = document.querySelector(
				'section[aria-label="Query Editor"] .cm-s-graphiql'
			);
			if (!el || !el.CodeMirror) {
				throw new Error('Query editor CodeMirror instance not found');
			}
			return el.CodeMirror.getValue();
		});

		// The prettified query should have newlines (be multi-line)
		expect(queryAfter).toContain('\n');

		// The prettified query should have proper indentation (spaces)
		expect(queryAfter).toMatch(/\s{2,}/); // At least 2 spaces for indentation

		// The prettified query should still contain the same fields
		expect(queryAfter).toContain('posts');
		expect(queryAfter).toContain('nodes');
		expect(queryAfter).toContain('id');
		expect(queryAfter).toContain('title');
		expect(queryAfter).toContain('date');
	});

	test('history button opens the history panel', async ({ page }) => {
		await loadGraphiQL(page);

		// First, execute a query so there's something in history
		await typeQuery(page, `{posts{nodes{id}}}`);
		await page.click(selectors.executeQueryButton);
		await expect(page.locator(selectors.graphiqlResponse)).toContainText(
			'posts',
			{ timeout: responseTimeout }
		);

		// Click the History button
		const historyButton = page.locator(
			"xpath=//button[contains(text(), 'History')]"
		);
		const historySection = page.locator('section[aria-label="History"]');

		await historyButton.click();
		await expect(historySection).toBeVisible({ timeout: 10_000 });
		await expect(historySection.locator('.history-title')).toBeVisible();

		// Click History again to toggle it closed
		await historyButton.click();
		await expect(historySection).not.toBeVisible({ timeout: 10_000 });
	});
});
