/**
 * Utility functions for Playwright E2E tests (WordPress admin, ACF tools).
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
	await page.goto(`${wpAdminUrl}`, { waitUntil: 'networkidle' });

	const isLoggedIn = await page.$('#wpadminbar');
	if (isLoggedIn) {
		return;
	}

	await page.fill(selectors.loginUsername, 'admin');
	await page.fill(selectors.loginPassword, 'password');
	await page.click(selectors.submitButton);
	await page.waitForSelector('#wpadminbar');
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} path
 */
export async function visitAdminFacingPage(page, path = null) {
	await page.goto(path ?? wpAdminUrl, { waitUntil: 'networkidle' });
}

/**
 * Import an ACF field group JSON file via Tools → Import (file input).
 * Uses the field-group import file input only: #acf-admin-tool-import input#acf_import_file
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} filename - Basename of the JSON file in tests/_data/
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
	await page.waitForLoadState('networkidle');
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
			await rows.nth(i).locator('.check-column input[type="checkbox"]').check();
		}
		await page.locator('#bulk-action-selector-bottom').selectOption('trash');
		await page.locator('#doaction2').click();
		await page.waitForLoadState('networkidle');
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
			await rows.nth(i).locator('.check-column input[type="checkbox"]').check();
		}
		await page.locator('#bulk-action-selector-bottom').selectOption('delete');
		await page.locator('#doaction2').click();
		await page.waitForLoadState('networkidle');
		rows = trashTable.locator('tbody tr:not(.no-items)');
	}
}

/**
 * Execute a GraphQL request (POST /graphql). Use from specs that need to assert on schema or query results.
 *
 * @param {import('@playwright/test').APIRequestContext} request - Playwright request fixture (uses project baseURL).
 * @param {string} query - GraphQL query string.
 * @param {Record<string, unknown>} [variables] - Optional variables.
 * @returns {Promise<{ data?: unknown; errors?: unknown }>} Parsed JSON response body.
 */
export async function graphqlRequest(request, query, variables = null) {
	const body = variables ? { query, variables } : { query };
	const response = await request.post('/graphql', {
		data: body,
		headers: { 'Content-Type': 'application/json' },
	});
	const text = await response.text();
	if (!response.ok()) {
		const excerpt =
			text.length > 2000 ? `${text.slice(0, 2000)}...` : text;
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
 * @param {string} [fieldGroupTitle='Foo Name'] - Title of the field group row to click.
 */
export async function navigateToFieldGroupEdit(page, fieldGroupTitle = 'Foo Name') {
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group`
	);
	await page.waitForLoadState('networkidle');
	const row = page
		.locator('.wp-list-table tbody tr')
		.filter({ hasText: fieldGroupTitle })
		.first();
	await row.locator('a.row-title').click();
	await page.waitForLoadState('networkidle');
	await page.locator('.acf-page-title').waitFor({ state: 'visible' });
}

/**
 * On a field group edit page: open the field by key (click "Edit field") and optionally set field type, then open the GraphQL tab.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} fieldKey - ACF field key (e.g. field_63d2bb765f5af).
 * @param {string|null} [fieldType=null] - If set, select this type from the field type dropdown (e.g. 'checkbox').
 */
export async function openFieldByKeyAndGraphQLTab(page, fieldKey, fieldType = null) {
	const panel = page.locator(`div[data-key="${fieldKey}"]`).first();
	await panel.locator('a[title="Edit field"]').first().click();
	await page.waitForLoadState('networkidle');

	if (fieldType) {
		const typeSelect = panel.locator('select.field-type').first();
		if ((await typeSelect.count()) > 0) {
			await typeSelect.selectOption(fieldType);
			await page.waitForLoadState('networkidle');
		}
	}

	const graphqlTab = panel.locator('.acf-tab-button', { hasText: 'GraphQL' }).first();
	if ((await graphqlTab.count()) > 0) {
		await graphqlTab.click();
		await page.waitForLoadState('networkidle');
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
	await page.waitForLoadState('networkidle');
}
