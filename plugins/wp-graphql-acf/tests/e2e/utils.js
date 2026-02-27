/**
 * Utility functions for Playwright E2E tests (WordPress admin, ACF tools).
 * jQuery is used inside page.evaluate() (browser context).
 *
 * @module utils
 */

import path from 'node:path';
import fs from 'node:fs';

const selectors = {
	loginUsername: '#user_login',
	loginPassword: '#user_pass',
	submitButton: '#wp-submit',
};

export const wpHomeUrl = 'http://localhost:8889';
export const wpAdminUrl = 'http://localhost:8889/wp-admin';

/**
 * Path to the plugin's tests _data directory (JSON fixtures).
 * Resolves from package root when run via npm -w from repo root.
 */
function getDataPath() {
	const cwd = process.cwd();
	if (cwd.endsWith('wp-graphql-acf')) {
		return path.join(cwd, 'tests', '_data');
	}
	return path.join(cwd, 'plugins', 'wp-graphql-acf', 'tests', '_data');
}

/**
 * Log in to the WordPress admin dashboard.
 * @param {import('@playwright/test').Page} page
 */
export async function loginToWordPressAdmin(page) {
	await page.goto(`${wpAdminUrl}`, { waitUntil: 'domcontentloaded' });

	const isLoggedIn = await page.$('#wpadminbar');
	if (isLoggedIn) {
		return;
	}

	await page.fill(selectors.loginUsername, 'admin');
	await page.fill(selectors.loginPassword, 'password');
	await page.click(selectors.submitButton);
	await page.waitForSelector('#wpadminbar', { state: 'visible' });
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string|null}                     urlPath - Full URL or null for wpAdminUrl
 */
export async function visitAdminFacingPage(page, urlPath = null) {
	await page.goto(urlPath ?? wpAdminUrl, { waitUntil: 'domcontentloaded' });
}

/**
 * Import an ACF field group JSON file via Tools → Import (file input).
 * Uses the field-group import file input only: #acf-admin-tool-import input#acf_import_file
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          filename - Basename of the JSON file in tests/_data/
 */
export async function importAcfJson(page, filename) {
	const dataDir = getDataPath();
	const filePath = path.join(dataDir, filename);
	if (!fs.existsSync(filePath)) {
		throw new Error(`ACF JSON fixture not found: ${filePath}`);
	}
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group&page=acf-tools`
	);
	await page.waitForSelector('#acf-admin-tool-import', { state: 'visible' });
	const fileInput = page.locator(
		'#acf-admin-tool-import input#acf_import_file'
	);
	await fileInput.setInputFiles(filePath);
	await page
		.locator('#acf-admin-tool-import')
		.getByRole('button', { name: 'Import JSON' })
		.click();
	await page
		.locator('.notice-success, .wp-list-table tbody tr')
		.first()
		.waitFor({ state: 'visible', timeout: 15000 });
}

/**
 * Delete all ACF field groups: bulk Move to Trash, then bulk Delete Permanently from Trash.
 * Use in afterEach so each test suite leaves no field groups behind and trash does not grow.
 *
 * @param {import('@playwright/test').Page} page
 */
const BATCH_SIZE = 50;

export async function deleteAllAcfFieldGroups(page) {
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group`
	);

	const table = page.locator('.wp-list-table').first();
	let rows = table.locator('tbody tr:not(.no-items)');

	while ((await rows.count()) > 0) {
		const count = await rows.count();
		const batchCount = Math.min(BATCH_SIZE, count);
		for (let i = 0; i < batchCount; i++) {
			await rows
				.nth(i)
				.locator('.check-column input[type="checkbox"]')
				.check();
		}
		await page
			.locator('#bulk-action-selector-bottom')
			.selectOption('trash');
		await page.locator('#doaction2').click();
		await page.waitForLoadState('load');
		rows = table.locator('tbody tr:not(.no-items)');
	}

	// Empty trash so trashed field groups don't accumulate (bulk Delete Permanently).
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group&post_status=trash`
	);
	const trashTable = page.locator('.wp-list-table').first();
	rows = trashTable.locator('tbody tr:not(.no-items)');
	while ((await rows.count()) > 0) {
		const count = await rows.count();
		const batchCount = Math.min(BATCH_SIZE, count);
		for (let i = 0; i < batchCount; i++) {
			await rows
				.nth(i)
				.locator('.check-column input[type="checkbox"]')
				.check();
		}
		await page
			.locator('#bulk-action-selector-bottom')
			.selectOption('delete');
		await page.locator('#doaction2').click();
		await page.waitForLoadState('load');
		rows = trashTable.locator('tbody tr:not(.no-items)');
	}
}

/**
 * Execute a GraphQL request (POST /graphql). Use from specs that need to assert on schema or query results.
 *
 * @param {import('@playwright/test').APIRequestContext} request     - Playwright request fixture (uses project baseURL).
 * @param {string}                                       query       - GraphQL query string.
 * @param {Record<string, unknown>}                      [variables] - Optional variables.
 * @return {Promise<{ data?: unknown; errors?: unknown }>} Parsed JSON response body.
 */
export async function graphqlRequest(request, query, variables = null) {
	const body = variables ? { query, variables } : { query };
	const response = await request.post('/graphql', {
		data: body,
		headers: { 'Content-Type': 'application/json' },
	});
	const text = await response.text();
	if (!response.ok()) {
		const excerpt = text.length > 2000 ? `${text.slice(0, 2000)}...` : text;
		throw new Error(
			`GraphQL request failed: ${response.status()} ${excerpt || '(empty body)'}`
		);
	}
	return JSON.parse(text);
}

// --- Field group / field edit helpers (Suite 3 – Field types GraphQL UI) ---

/**
 * Navigate to a field group edit screen by title (from the list).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          [fieldGroupTitle='Foo Name'] - Title of the field group row to click.
 */
export async function navigateToFieldGroupEdit(
	page,
	fieldGroupTitle = 'Foo Name'
) {
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group`
	);
	await page
		.locator('.wp-list-table tbody tr')
		.first()
		.waitFor({ state: 'visible', timeout: 10000 });
	const row = page
		.locator('.wp-list-table tbody tr')
		.filter({ hasText: fieldGroupTitle })
		.first();
	await row.locator('a.row-title').click();
	await page.locator('.acf-page-title').waitFor({ state: 'visible' });
}

/**
 * On a field group edit page: open the field by key (click "Edit field") and optionally set field type, then open the GraphQL tab.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          fieldKey         - ACF field key (e.g. field_63d2bb765f5af).
 * @param {string|null}                     [fieldType=null] - If set, select this type from the field type dropdown (e.g. 'checkbox').
 */
export async function openFieldByKeyAndGraphQLTab(
	page,
	fieldKey,
	fieldType = null
) {
	const panel = page.locator(`div[data-key="${fieldKey}"]`).first();
	await panel.locator('a[title="Edit field"]').first().click();
	// Wait for the tab bar to load (first tab e.g. General is visible); then we click GraphQL (ACFE can show GraphQL tab as hidden until selected).
	await panel
		.locator('.acf-tab-button')
		.first()
		.waitFor({ state: 'visible', timeout: 10000 });

	if (fieldType) {
		const typeSelect = panel.locator('select.field-type').first();
		if ((await typeSelect.count()) > 0) {
			const isSelect2 = await typeSelect.evaluate((el) =>
				el.classList.contains('select2-hidden-accessible')
			);
			if (isSelect2) {
				// Set value via Select2/jQuery so we don't depend on option label (e.g. "Text Area", "True / False", ACFE names).
				await typeSelect.evaluate((el, value) => {
					if (
						typeof jQuery !== 'undefined' &&
						jQuery(el).data('select2')
					) {
						jQuery(el).val(value).trigger('change');
					} else {
						el.value = value;
						el.dispatchEvent(
							new Event('change', { bubbles: true })
						);
					}
				}, fieldType);
			} else {
				await typeSelect.selectOption(fieldType);
			}
			await panel
				.locator('.acf-tab-button')
				.first()
				.waitFor({ state: 'visible', timeout: 5000 });
		}
	}

	const graphqlTab = panel
		.locator('.acf-tab-button', { hasText: 'GraphQL' })
		.first();
	if ((await graphqlTab.count()) > 0) {
		await graphqlTab.click();
		const tabReadyTimeout = process.env.CI ? 20000 : 10000;
		await panel
			.locator('[data-name="show_in_graphql"]')
			.first()
			.waitFor({ state: 'visible', timeout: tabReadyTimeout });
		// Ensure tab content is rendered (CI can be slow to paint).
		await panel
			.locator('[data-name="show_in_graphql"]')
			.getByText('Show in GraphQL')
			.first()
			.waitFor({ state: 'visible', timeout: tabReadyTimeout });
	}
}

/**
 * Submit the field group form (Save). Caller should assert #message.notice-success and no .notice-error.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function submitFieldGroupForm(page) {
	await page
		.locator('#submitpost')
		.locator('#save, button[type="submit"]')
		.first()
		.click();
	const saveTimeout = process.env.CI ? 15000 : 10000;
	await page
		.locator('#message.notice-success')
		.waitFor({ state: 'visible', timeout: saveTimeout });
}
