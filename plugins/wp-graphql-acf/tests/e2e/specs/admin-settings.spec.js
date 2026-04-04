import {
	describe,
	test,
	expect,
	beforeEach,
	afterEach,
} from '@playwright/test';
import {
	loginToWordPressAdmin,
	visitAdminFacingPage,
	importAcfJson,
	deleteAllAcfFieldGroups,
	wpAdminUrl,
} from '../utils.js';

const TEST_JSON = 'acf-export-2023-01-26.json';

/**
 * E2E tests for WPGraphQL for ACF admin: field group list table shows
 * GraphQL columns and imported group data (name, GraphQL field name).
 *
 * Each test: setup imports JSON, assertions run, cleanup deletes all field groups.
 */
describe('Admin settings (ACF field group list)', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, TEST_JSON);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('Import JSON and see WPGraphQL column headers on field group list', async ({
		page,
	}) => {
		await visitAdminFacingPage(
			page,
			`${wpAdminUrl}/edit.php?post_type=acf-field-group`
		);

		const table = page.locator('.wp-list-table').first();
		await expect(
			table
				.locator('thead')
				.getByRole('columnheader', { name: /graphql type/i })
		).toBeVisible();
		await expect(
			table
				.locator('thead')
				.getByRole('columnheader', { name: /graphql interfaces/i })
		).toBeVisible();
		await expect(
			table
				.locator('thead')
				.getByRole('columnheader', { name: /graphql locations/i })
		).toBeVisible();
	});

	test('Import JSON and see group title and GraphQL field name in table', async ({
		page,
	}) => {
		await visitAdminFacingPage(
			page,
			`${wpAdminUrl}/edit.php?post_type=acf-field-group`
		);

		const table = page.locator('.wp-list-table').first();
		const row = table
			.locator('tbody tr')
			.filter({ hasText: 'Foo Name' })
			.filter({ hasText: 'FooGraphql' })
			.first();
		await expect(row).toBeVisible();
		await expect(row.locator('td.column-title a.row-title')).toBeVisible();
		await expect(
			row
				.locator('td.column-acf-wpgraphql-type')
				.getByText('FooGraphql', { exact: true })
		).toBeVisible();
	});
});
