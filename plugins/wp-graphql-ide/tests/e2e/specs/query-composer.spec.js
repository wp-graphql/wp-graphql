import {
	loginToWordPressAdmin,
	openDrawer,
	typeQuery,
	visitAdminFacingPage,
} from '../utils';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

// Login to WordPress before each test
test.beforeEach(async ({ page }) => {
	await loginToWordPressAdmin(page);
});

async function navigateToGraphiQLAndOpenQueryComposer({ page }) {
	await expect(page.locator('.graphiql-container')).toBeHidden();
	await openDrawer(page);
	await page.click('[aria-label="Show Query Composer"]');
	await expect(page.locator('.docExplorerWrap')).toBeVisible();
}

test.describe('GraphiQL Query Composer', () => {
	test('Clicking the Query Composer button opens and closes the Query Composer', async ({
		page,
	}) => {
		await visitAdminFacingPage(page);
		await expect(page.locator('.graphiql-container')).toBeHidden();
		await openDrawer(page);
		await expect(page.locator('.graphiql-container')).toBeVisible();

		// query composer should be hidden by default
		await expect(page.locator('.docExplorerWrap')).toBeHidden();

		// open query composer and check if it is visible
		await page.click('[aria-label="Show Query Composer"]');
		await expect(page.locator('.docExplorerWrap')).toBeVisible();

		// close query composer and check if it is hidden
		await page.click('[aria-label="Hide Query Composer"]');
		await expect(page.locator('.docExplorerWrap')).toBeHidden();
	});

	test.skip('Changing the name of an operation in the query composer updates the query editor', async ({
		page,
	}) => {
		await navigateToGraphiQLAndOpenQueryComposer({ page });

		const firstQueryOperationNameInput = await page.locator(
			'.graphiql-explorer-root>div>div:first-of-type input'
		);

		const queryEditor = await page.locator(
			'[aria-label="Query Editor"] .CodeMirror'
		);

		const query = 'query OldQueryName { __typename }';
		await typeQuery(page, query);
		await expect(queryEditor).toContainText('OldQueryName');
		await expect(queryEditor).not.toContainText('NewQueryName');

		// focus on the input field
		await firstQueryOperationNameInput.fill('NewQueryName');
		await expect(queryEditor).toContainText('NewQueryName');
		await expect(queryEditor).not.toContainText('OldQueryName');
	});

	test('Selecting a field in the query composer adds that field to the query', async ({
		page,
	}) => {
		await navigateToGraphiQLAndOpenQueryComposer({ page });

		const firstFieldSelector =
			'.graphiql-explorer-root>div>div>div.graphiql-explorer-node:nth-of-type(2) > span';
		const firstField = await page.locator(firstFieldSelector);
		await expect(firstField).toBeVisible();
		const fieldName = await page
			.locator(firstFieldSelector)
			.getAttribute('data-field-name');

		const queryEditor = await page.locator(
			'[aria-label="Query Editor"] .CodeMirror'
		);
		await expect(queryEditor).not.toContainText(fieldName);
		await firstField.click();
		await expect(queryEditor).toContainText(fieldName);
	});

	test('Selecting a field in the query composer that has arguments and filling in arguments updates the query', async ({
		page,
	}) => {
		await navigateToGraphiQLAndOpenQueryComposer({ page });

		const fieldSelector =
			'.graphiql-explorer-root>div>div>div.graphiql-explorer-contentNode';
		const field = await page.locator(`${fieldSelector}>span`);
		await expect(field).toBeVisible();
		const fieldName = await field.getAttribute('data-field-name');

		const queryEditor = await page.locator(
			'[aria-label="Query Editor"] .CodeMirror'
		);
		await expect(queryEditor).not.toContainText(fieldName);
		await expect(queryEditor).not.toContainText('id:');
		await expect(queryEditor).not.toContainText('123');
		await field.click();
		await expect(queryEditor).toContainText(fieldName);

		const idArgumentField = await page.locator(
			`${fieldSelector}>div.graphiql-explorer-contentNode div[data-arg-name="id"]`
		);
		await idArgumentField.click();
		const idArgumentFieldInput = await page.locator(
			`${fieldSelector}>div.graphiql-explorer-contentNode div[data-arg-name="id"] input`
		);
		await idArgumentFieldInput.fill('123');
		await expect(queryEditor).toContainText('id: "123"');
	});

	test('Deleting a query from the query composer removes it from the document', async ({
		page,
	}) => {
		await navigateToGraphiQLAndOpenQueryComposer({ page });

		const fieldSelector =
			'.graphiql-explorer-root>div>div>div.graphiql-explorer-contentNode';
		const field = await page.locator(`${fieldSelector}>span`);
		await expect(field).toBeVisible();
		const fieldName = await field.getAttribute('data-field-name');

		const queryEditor = await page.locator(
			'[aria-label="Query Editor"] .CodeMirror'
		);
		await expect(queryEditor).not.toContainText(fieldName);
		await field.click();
		await expect(queryEditor).toContainText(fieldName);

		const firstQueryOperationTitleBar = await page.locator(
			'.graphiql-explorer-root>div>div>div.graphiql-operation-title-bar'
		);
		await firstQueryOperationTitleBar.hover();
		const deleteButton = await page.locator(
			'.graphiql-explorer-root>div>div>div.graphiql-operation-title-bar button:first-of-type'
		);
		await deleteButton.click();
		await expect(queryEditor).not.toContainText(fieldName);
	});

	test('Copying a query from the query composer adds a copy to the document', async ({
		page,
	}) => {
		await navigateToGraphiQLAndOpenQueryComposer({ page });

		const fieldSelector =
			'.graphiql-explorer-root>div>div>div.graphiql-explorer-contentNode';
		const field = await page.locator(`${fieldSelector}>span`);
		await expect(field).toBeVisible();
		const fieldName = await field.getAttribute('data-field-name');

		const queryEditor = await page.locator(
			'[aria-label="Query Editor"] .CodeMirror'
		);
		await expect(queryEditor).not.toContainText(fieldName);
		await field.click();
		await expect(queryEditor).toContainText(fieldName);

		const firstQueryOperationTitleBar = await page.locator(
			'.graphiql-explorer-root>div>div>div.graphiql-operation-title-bar'
		);
		await firstQueryOperationTitleBar.hover();
		const copyButton = await page.locator(
			'.graphiql-explorer-root>div>div>div.graphiql-operation-title-bar button:nth-of-type(2)'
		);
		await copyButton.click();
		await expect(queryEditor).toContainText(`MyQuery`);
		await expect(queryEditor).toContainText(`MyQueryCopy`);
	});
});
