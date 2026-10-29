import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	runQuery,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

// A type whose name starts with a space, like the one a field group with
// a non-Latin name produced in #4546. The server returns it without
// errors, and `buildClientSchema` throws on it in the browser.
const INVALID_TYPE = {
	kind: 'OBJECT',
	name: ' _Fields',
	description: null,
	fields: [],
	inputFields: null,
	interfaces: [],
	enumValues: null,
	possibleTypes: null,
};

const FAILURE_NOTICE = /Failed to load schema: .*_Fields/;

test.describe('Schema load errors', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		// Add the invalid type to every introspection response and leave
		// all other requests alone.
		await page.route(/graphql/, async (route) => {
			const request = route.request();
			const sent = request.postData() || request.url();
			if (!sent.includes('IntrospectionQuery')) {
				await route.continue();
				return;
			}
			const response = await route.fetch();
			const json = await response.json();
			json.data.__schema.types.push(INVALID_TYPE);
			await route.fulfill({ response, json });
		});
	});

	test('shows an error notice when the first schema load fails', async ({
		page,
	}) => {
		await visitDedicatedIde(page);
		await expect(
			page
				.locator('.components-snackbar.is-error')
				.filter({ hasText: FAILURE_NOTICE })
		).toBeVisible({ timeout: 10000 });
	});

	test('shows the error notice again when a query runs without a schema', async ({
		page,
	}) => {
		await visitDedicatedIde(page);
		const notice = page
			.locator('.components-snackbar.is-error')
			.filter({ hasText: FAILURE_NOTICE });
		await expect(notice).toBeVisible({ timeout: 10000 });
		await notice.click();
		await expect(notice).toBeHidden();

		// Running a query with no schema loaded retries the load.
		await ensureDocumentOpen(page);
		await typeQuery(page, '{ __typename }');
		await runQuery(page);
		await expect(notice).toBeVisible({ timeout: 10000 });
	});
});
