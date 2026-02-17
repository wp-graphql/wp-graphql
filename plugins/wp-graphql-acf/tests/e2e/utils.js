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
 * Delete all ACF field groups via the admin list screen (bulk Move to Trash).
 * Use in afterEach so each test suite leaves no field groups behind.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function deleteAllAcfFieldGroups(page) {
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group`
	);

	const table = page.locator('.wp-list-table').first();
	// Rows that are real items (exclude the "No items" placeholder row)
	let rows = table.locator('tbody tr:not(.no-items)');

	while ((await rows.count()) > 0) {
		await table.locator('thead .check-column input[type="checkbox"]').check();
		await page.locator('#bulk-action-selector-bottom').selectOption('trash');
		await page.locator('#doaction2').click();
		await page.waitForLoadState('networkidle');
		rows = table.locator('tbody tr:not(.no-items)');
	}
}
