import { loginToWordPressAdmin, typeQuery, loadGraphiQL } from '../utils.js';
import { test, expect } from '@playwright/test';

const selectors = {
	graphiqlContainer: '.graphiql-container',
	graphiqlResponse: '.resultWrap',
	executeQueryButton: '.execute-button',
	queryInput: '[aria-label="Query Editor"] .CodeMirror',
	variablesInput: '[aria-label="Variables"] .CodeMirror',
};

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
		await page.waitForLoadState('networkidle');
		const response = await page.locator(selectors.graphiqlResponse);
		await expect(response).not.toContainText('errors');
		await expect(response).toContainText('posts');
	});

	test('it renders errors when errors are expected', async ({ page }) => {
		await loadGraphiQL(page);
		await typeQuery(page, `{nonExistentFieldThatShouldError}`);
		await page.click(selectors.executeQueryButton);
		await page.waitForLoadState('networkidle');
		const response = await page.locator(selectors.graphiqlResponse);
		await expect(response).toContainText('errors');
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
		await page.waitForLoadState('networkidle');
		const editor = await page.locator(selectors.graphiqlContainer);
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
		const openButton = page.locator('button.docExplorerShow');

		// Check if the wrapper is hidden by its parent's styles
		const isInitiallyHidden = await page.evaluate(() => {
			const wrapper =
				document.querySelector('.docExplorerWrap')?.parentElement;
			return wrapper
				? window.getComputedStyle(wrapper).opacity === '0'
				: true;
		});
		expect(isInitiallyHidden).toBeTruthy();

		// clicking the openButton should open it
		await openButton.click();
		await page.waitForTimeout(1000);

		const isVisible = await page.evaluate(() => {
			const wrapper =
				document.querySelector('.docExplorerWrap')?.parentElement;
			return wrapper
				? window.getComputedStyle(wrapper).opacity === '1'
				: false;
		});
		expect(isVisible).toBeTruthy();

		// clicking the close button should close it
		const closeButton = page.locator('button.docExplorerHide');
		await closeButton.click();
		await page.waitForTimeout(1000);

		const isHiddenAgain = await page.evaluate(() => {
			const wrapper =
				document.querySelector('.docExplorerWrap')?.parentElement;
			return wrapper
				? window.getComputedStyle(wrapper).opacity === '0'
				: true;
		});
		expect(isHiddenAgain).toBeTruthy();
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
			const editor = document.querySelector(
				'.query-editor .cm-s-graphiql'
			).CodeMirror;
			return editor.getValue();
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
		await page.waitForLoadState('networkidle');

		// Click the History button
		const historyButton = page.locator(
			"xpath=//button[contains(text(), 'History')]"
		);
		await historyButton.click();
		await page.waitForTimeout(500);

		// Verify the history panel is now visible
		const isHistoryVisible = await page.evaluate(() => {
			const wrapper = document.querySelector('.historyPaneWrap');
			if (!wrapper) {
				return false;
			}
			const style = window.getComputedStyle(wrapper);
			return style.display !== 'none' && style.visibility !== 'hidden';
		});
		expect(isHistoryVisible).toBeTruthy();

		// The history panel should contain history-related elements
		const historyTitle = page.locator('.history-title');
		await expect(historyTitle).toBeVisible();

		// Click History again to toggle it closed
		await historyButton.click();
		await page.waitForTimeout(500);

		// Verify the history panel is hidden again
		const isHistoryHiddenAgain = await page.evaluate(() => {
			const wrapper = document.querySelector('.historyPaneWrap');
			if (!wrapper) {
				return true;
			} // If not found, consider it hidden
			const style = window.getComputedStyle(wrapper);
			return (
				style.display === 'none' ||
				style.visibility === 'hidden' ||
				style.width === '0px'
			);
		});
		expect(isHistoryHiddenAgain).toBeTruthy();
	});
});
