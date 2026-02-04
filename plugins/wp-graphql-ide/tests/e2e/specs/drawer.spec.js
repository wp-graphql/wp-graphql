import {
	loginToWordPressAdmin,
	openDrawer,
	pasteVariables,
	simulateHeavyJSLoad,
	typeQuery,
	typeVariables,
	visitAdminFacingPage,
	visitPublicFacingPage,
	wpAdminUrl,
} from '../utils.js';

import { getHashedQueryParams } from '../../../src/registry/editor-toolbar-buttons/share-button.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

export const selectors = {
	graphiqlContainer: '.graphiql-container',
	graphiqlResponse: '.graphiql-response',
	appDrawerButton: '.AppDrawerButton',
	appDrawerCloseButton: '.AppDrawerCloseButton',
	executeQueryButton: '.graphiql-execute-button',
	queryInput: '[aria-label="Query Editor"] .CodeMirror',
	variablesInput: '[aria-label="Variables"] .CodeMirror',
};

// Login to WordPress before each test
test.beforeEach(async ({ page }) => {
	await loginToWordPressAdmin(page);
});

test('should open and close successfully', async ({ page }) => {
	await page.click(selectors.appDrawerButton);
	await expect(page.locator(selectors.graphiqlContainer)).toBeVisible();
	await page.click(selectors.appDrawerCloseButton);
	await expect(page.locator(selectors.graphiqlContainer)).not.toBeVisible();
});

test('should open on JS-heavy admin page with CPU Throttling', async ({
	page,
}) => {
	await visitAdminFacingPage(page);

	// Start a new CDP Session to control the browser
	const context = page.context();
	const session = await context.newCDPSession(page);

	// Set CPU Throttling
	await session.send('Emulation.setCPUThrottlingRate', { rate: 4 }); // Throttles CPU to 1/4th its speed

	// Now simulate heavy JavaScript load
	await simulateHeavyJSLoad(page);

	// Check if the drawer is initially hidden
	await expect(page.locator('.graphiql-container')).toBeHidden();

	// Open the drawer and ensure it still functions under throttled conditions
	await openDrawer(page);
	await expect(page.locator('.graphiql-container')).toBeVisible();

	// Optionally, reset CPU throttling rate after test
	await session.send('Emulation.setCPUThrottlingRate', { rate: 1 });
});

test('should execute a GraphQL query successfully', async ({ page }) => {
	await page.click(selectors.appDrawerButton);
	await expect(page.locator(selectors.graphiqlContainer)).toBeVisible();

	// Type and execute a GraphQL query
	const query = '{posts{nodes{databaseId}}}';
	await typeQuery(page, query);
	await page.click(selectors.executeQueryButton);
	await page.waitForSelector('.graphiql-spinner', { state: 'hidden' }); // Wait for query execution

	// Check for expected response
	// The expected response is a JSON object with a "posts" property.
	const expectedResponseText = `"posts"`;
	const response = await page.locator(selectors.graphiqlResponse);

	// The valid query executes without errors
	await expect(response).not.toContainText('errors');

	// The query returns a payload containing the "posts" payload
	await expect(response).toContainText(expectedResponseText);
});

test('should show errors for an invalid query', async ({ page }) => {
	await page.click(selectors.appDrawerButton);
	await expect(page.locator(selectors.graphiqlContainer)).toBeVisible();

	// Type and execute an invalid GraphQL query
	const query = '{invalidQuery}';
	await typeQuery(page, query);
	await page.click(selectors.executeQueryButton);
	await page.waitForSelector('.graphiql-spinner', { state: 'hidden' }); // Wait for query execution

	// Check for expected response
	// The invalid query returns an error
	const response = await page.locator(selectors.graphiqlResponse);
	await expect(response).toContainText('errors');
});

test('drawer opens on an admin page', async ({ page }) => {
	await visitAdminFacingPage(page);
	await expect(page.locator('.graphiql-container')).toBeHidden();
	await openDrawer(page);
	await expect(page.locator('.graphiql-container')).toBeVisible();
});

test('drawer opens on a public page', async ({ page }) => {
	await visitPublicFacingPage(page);
	const overlay = await page.$('[vaul-overlay]');
	if (overlay) {
		await overlay.click();
	}
	await expect(page.locator('.graphiql-container')).toBeHidden();
	await openDrawer(page);
	const isVisible = await page.locator('.graphiql-container').isVisible();
	if (!isVisible) {
		await openDrawer(page);
	}
	await expect(page.locator('.graphiql-container')).toBeVisible();
});

test('loads with the documentation explorer closed', async ({ page }) => {
	await visitAdminFacingPage(page);
	await expect(page.locator('.graphiql-container')).toBeHidden();
	await openDrawer(page);
	await expect(page.locator('.graphiql-container')).toBeVisible();
	await expect(page.locator('.graphiql-doc-explorer')).toBeHidden();
});

test('documentation explorer can be toggled open and closed', async ({
	page,
}) => {
	await visitAdminFacingPage(page);
	await expect(page.locator('.graphiql-container')).toBeHidden();
	await openDrawer(page);
	await expect(page.locator('.graphiql-container')).toBeVisible();
	await page.click('[aria-label="Show Documentation Explorer"]');
	await expect(page.locator('.graphiql-doc-explorer')).toBeVisible();
	await page.click('[aria-label="Hide Documentation Explorer"]');
	await expect(page.locator('.graphiql-doc-explorer')).toBeHidden();
});

test('executes query on public facing page', async ({ page }) => {
	await visitPublicFacingPage(page);
	await openDrawer(page);
	await typeQuery(page, `{posts{nodes{id}}}`);
	await typeVariables(page, { first: 10 });
	await page.click(selectors.executeQueryButton);
	await page.waitForSelector('.graphiql-spinner', { state: 'hidden' }); // Wait for query execution
	const response = await page.locator(selectors.graphiqlResponse);
	await expect(response).toContainText('posts');
	await expect(response).toContainText('nodes');
});

test.skip('expect error if invalid json is submitted for variables', async ({
	page,
}) => {});

test.skip('expect error if invalid query is submitted', async ({ page }) => {});

test.describe('query params', () => {
	test.skip('loads with fetcher in authenticated state if query param ?wpgql_is_authenticated=true', async ({
		page,
	}) => {});

	test.skip('loads with history pane open if ?wpgql_active_plugin=history', async ({
		page,
	}) => {});
	test.skip('loads with docs explorer pane open if ?wpgql_active_plugin=docs', async ({
		page,
	}) => {});
	test.skip('loads with no visible plugin pane open if ?wpgql_active_plugin is not set or does not have a valid plugin name set', async ({
		page,
	}) => {});

	test.skip('loads with variables pane populated if ?wpgql_variables is not set or does not have a valid plugin name set', async ({
		page,
	}) => {});

	test('loads with drawer open if ?wpgraphql_ide exists as a query param', async ({
		page,
	}) => {
		const hashedQueryParams = getHashedQueryParams({
			query: 'query TestQuery { posts { nodes { id } } }',
		});

		await page.goto(
			`${wpAdminUrl}/index.php?wpgraphql_ide=${hashedQueryParams}`,
			{ waitUntil: 'networkidle' }
		);
		await expect(page.locator('.graphiql-container')).toBeVisible();
	});

	test('query editor is populated with the query passed in from the ?wpgraphql_ide query param', async ({
		page,
	}) => {
		const hashedQueryParams = getHashedQueryParams({
			query: 'query TestQuery { posts { nodes { id } } }',
		});

		await page.goto(
			`${wpAdminUrl}/index.php?wpgraphql_ide=${hashedQueryParams}`,
			{ waitUntil: 'networkidle' }
		);

		const queryInput = await page.locator(selectors.queryInput);
		await expect(queryInput).toContainText('TestQuery');
	});

	test.skip('loads with drawer open if ?wpgraphql_ide_hash exists as a query param', async ({
		page,
	}) => {});
	test.skip('query editor is populated with the (unhashed) query passed in from the ?wpgraphql_ide_hash query param', async ({
		page,
	}) => {});

	// This tests that the wpgraphql-ide query parameter will load graphiql in an opened state
	// It also tests that the query parameter will populate the query input
	// test.skip( 'graphiql loads with ?wpgraphql_ide populated from query parameter', async ({ page }) => {
	// 	const query = 'query TestQuery{posts{nodes{databaseId}}}';
	// 	const url = `http://localhost:8888/wp-admin?wpgraphql-ide=open&query=${query}`;
	// 	await page.goto(url, { waitUntil: 'networkidle' });
	// 	await expect(page.locator(selectors.graphiqlContainer)).toBeVisible();
	// 	await page.waitForSelector(selectors.queryInput);
	// 	const queryInput = await page.locator(selectors.queryInput);
	// 	await expect(queryInput).toContainText('TestQuery');
	// });
});
