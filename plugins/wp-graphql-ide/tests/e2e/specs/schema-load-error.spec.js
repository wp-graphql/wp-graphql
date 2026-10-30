import { execSync } from 'node:child_process';
import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	runQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8889';
// The public IDE renders at the GraphQL endpoint URL in endpoint mode.
const ENDPOINT_URL = `${BASE_URL}/graphql`;

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
const SIGNED_OUT_NOTICE =
	"The schema couldn't be loaded. If you think this is a mistake, contact the site administrator.";

/**
 * Add the invalid type to every introspection response and leave all
 * other requests alone. A response without a schema, like the error a
 * signed-out visitor gets with public introspection off, passes through
 * as it is.
 *
 * @param page
 */
async function breakSchema(page) {
	await page.route(/graphql/, async (route) => {
		const request = route.request();
		const sent = request.postData() || request.url();
		if (!sent.includes('IntrospectionQuery')) {
			await route.continue();
			return;
		}
		const response = await route.fetch();
		const json = await response.json();
		json.data?.__schema?.types.push(INVALID_TYPE);
		await route.fulfill({ response, json });
	});
}

/**
 * Whether a response answers the IDE's schema introspection request.
 *
 * @param response
 */
function isIntrospection(response) {
	const request = response.request();
	const sent = request.postData() || request.url();
	return sent.includes('IntrospectionQuery');
}

/**
 * Run the query in the editor, which retries the schema load while no
 * schema is loaded, and wait until the load and the query have finished.
 *
 * @param page
 */
async function runQueryAndWaitForLoad(page) {
	const loaded = page.waitForResponse(isIntrospection);
	await runQuery(page);
	await loaded;
	const execute = page.getByRole('button', { name: 'Execute query' });
	await expect(execute).toBeEnabled();
}

/**
 * Turn the public IDE at the GraphQL endpoint URL on or off, the same
 * way endpoint-mode.spec.js does.
 *
 * @param {'on' | 'off'} value
 */
function setEndpointMode(value) {
	const payload = JSON.stringify({ graphql_ide_public_endpoint: value });
	execSync(
		`npm run --prefix ../.. wp-env run tests-cli -- wp option update graphql_ide_settings --format=json '${payload}'`,
		{ stdio: 'pipe' }
	);
}

test.describe('Schema load errors', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await breakSchema(page);
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

	test('keeps a single error notice when queries retry the load', async ({
		page,
	}) => {
		await visitDedicatedIde(page);
		const notices = page.locator('.components-snackbar.is-error');
		const notice = notices.filter({ hasText: FAILURE_NOTICE });
		await expect(notice).toBeVisible({ timeout: 10000 });
		await notice.click();
		await expect(notice).toBeHidden();

		// Each run retries the load, and each retry fails the same way.
		await ensureDocumentOpen(page);
		await typeQuery(page, '{ __typename }');
		await runQueryAndWaitForLoad(page);
		await runQueryAndWaitForLoad(page);
		// Check right away: snackbars time out after a few seconds.
		await expect(notices).toHaveCount(1, { timeout: 1000 });
		await expect(notice).toBeVisible();
	});

	test('dismisses the error notice when a later load succeeds', async ({
		page,
	}) => {
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
		await typeQuery(page, '{ __typename }');
		const notice = page
			.locator('.components-snackbar.is-error')
			.filter({ hasText: FAILURE_NOTICE });
		await expect(notice).toBeVisible({ timeout: 10000 });

		// Let the retry through unchanged, so it loads the real schema.
		await page.unroute(/graphql/);
		await runQueryAndWaitForLoad(page);
		await expect(notice).toBeHidden({ timeout: 1000 });
	});
});

test.describe('Schema load errors for signed-out visitors', () => {
	test.use({ storageState: { cookies: [], origins: [] } });

	// Signed-out visitors can only open the public IDE at the endpoint URL.
	test.beforeAll(() => {
		setEndpointMode('on');
	});

	test.afterAll(() => {
		setEndpointMode('off');
	});

	test('shows a general error notice to signed-out visitors', async ({
		page,
	}) => {
		await breakSchema(page);
		await page.goto(ENDPOINT_URL, { waitUntil: 'domcontentloaded' });
		await page.waitForSelector(selectors.ideRoot, {
			state: 'visible',
			timeout: 15000,
		});
		const notices = page.locator('.components-snackbar.is-error');
		const notice = notices.filter({ hasText: SIGNED_OUT_NOTICE });
		await expect(notice).toBeVisible({ timeout: 10000 });
		const raw = notices.filter({ hasText: 'Failed to load schema' });
		await expect(raw).toHaveCount(0);
	});
});
