import { describe, test, expect, beforeEach, afterEach } from '@playwright/test';
import {
	loginToWordPressAdmin,
	visitAdminFacingPage,
	wpAdminUrl,
} from '../utils.js';
import { skipWhenNotAcfPro } from '../env.js';

/**
 * E2E tests for ACF UI Registration: Custom Post Types, Taxonomies, and Options Pages.
 * Flow: add new entity → enable advanced configuration → GraphQL tab → fill form → save
 * → Tools → Generate PHP → assert exported PHP contains show_in_graphql and graphql_* keys.
 * Requires ACF Pro 6.1+ (CPT/Taxonomy) or 6.2+ (Options Page). Skipped when ACF Free.
 * Mirrors: CustomPostTypeRegistrationCest, CustomTaxonomyRegistrationCest, OptionsPageUiRegistrationCest.
 */
const describeAcfUi = skipWhenNotAcfPro() ? describe.skip : describe;

const TOOLS_URL = `${wpAdminUrl}/edit.php?post_type=acf-field-group&page=acf-tools`;

/**
 * Delete one ACF UI entity (post type, taxonomy, or options page) by its list label.
 * Goes to list, finds row by label text, bulk trashes, then empties from trash.
 */
async function deleteAcfUiEntity(page, listUrl, rowLabel) {
	await visitAdminFacingPage(page, `${wpAdminUrl}/${listUrl}`);
	const table = page.locator('.wp-list-table').first();
	const row = table.locator('tbody tr:not(.no-items)').filter({ hasText: rowLabel }).first();
	if ((await row.count()) === 0) return;
	await row.locator('.check-column input[type="checkbox"]').check();
	await page.locator('#bulk-action-selector-bottom').selectOption('trash');
	await page.locator('#doaction2').click();
	await page.waitForLoadState('networkidle');
	await visitAdminFacingPage(page, `${wpAdminUrl}/${listUrl}&post_status=trash`);
	const trashTable = page.locator('.wp-list-table').first();
	const trashRow = trashTable.locator('tbody tr:not(.no-items)').filter({ hasText: rowLabel }).first();
	if ((await trashRow.count()) === 0) return;
	await trashRow.locator('.check-column input[type="checkbox"]').check();
	await page.locator('#bulk-action-selector-bottom').selectOption('delete');
	await page.locator('#doaction2').click();
	await page.waitForLoadState('networkidle');
}

describeAcfUi('ACF UI Registration – Custom Post Type', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAcfUiEntity(page, 'edit.php?post_type=acf-post-type', 'Test Types');
	});

	test('post type can be registered to show in GraphQL', async ({ page }) => {
		await visitAdminFacingPage(page, `${wpAdminUrl}/edit.php?post_type=acf-post-type`);
		await expect(page.getByText('Post Types', { exact: true }).first()).toBeVisible();
		await page.locator('.acf-headerbar').getByRole('link', { name: /add new/i }).first().click();
		await expect(page.getByRole('heading', { name: 'Add New Post Type' }).first()).toBeVisible();

		await page.locator('label[for="acf_post_type-advanced_configuration"]').click();
		await expect(page.locator('#acf-advanced-settings')).toBeVisible();
		await page.locator('#acf-advanced-settings').getByRole('link', { name: 'GraphQL' }).click();

		await expect(page.locator('#acf_post_type-show_in_graphql')).not.toBeChecked();
		await page.locator('input[name="acf_post_type[labels][singular_name]"]').fill('Test Type');
		await page.locator('input[name="acf_post_type[labels][name]"]').fill('Test Types');
		await page.locator('input[name="acf_post_type[post_type]"]').fill('test_type');
		await page.locator('label[for="acf_post_type-show_in_graphql"]').click();
		await page.getByLabel('GraphQL Single Name').fill('testSingleName');
		await page.getByLabel('GraphQL Plural Name').fill('testPluralName');

		await page.getByRole('button', { name: 'Save Changes' }).click();
		await page.waitForLoadState('networkidle');

		await expect(page.locator('input[name="acf_post_type[labels][singular_name]"]')).toHaveValue('Test Type');
		await expect(page.locator('input[name="acf_post_type[graphql_single_name]"]')).toHaveValue('testSingleName');
		await expect(page.locator('input[name="acf_post_type[graphql_plural_name]"]')).toHaveValue('testPluralName');

		await visitAdminFacingPage(page, TOOLS_URL);
		await expect(page.getByText('Select Post Types')).toBeVisible();
		await expect(page.locator('[data-name="post_type_keys"]').getByText('Test Types')).toBeVisible();
		await page.locator('[data-name="post_type_keys"]').getByRole('checkbox', { name: 'Test Types' }).check();
		await page.locator('#acf-admin-tool-export').getByRole('button', { name: 'Generate PHP' }).click();
		const textarea = page.locator('#acf-export-textarea');
		await expect(textarea).toBeVisible();
		const phpContent = await textarea.inputValue();
		expect(phpContent).toContain("'show_in_graphql' => true");
		expect(phpContent).toContain("'graphql_single_name' => 'testSingleName'");
		expect(phpContent).toContain("'graphql_plural_name' => 'testPluralName'");
	});
});

describeAcfUi('ACF UI Registration – Custom Taxonomy', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAcfUiEntity(page, 'edit.php?post_type=acf-taxonomy', 'Test Types');
	});

	test('taxonomy can be registered to show in GraphQL', async ({ page }) => {
		await visitAdminFacingPage(page, `${wpAdminUrl}/edit.php?post_type=acf-taxonomy`);
		await expect(page.getByText('Taxonomies', { exact: true }).first()).toBeVisible();
		await page.locator('.acf-headerbar').getByRole('link', { name: /add new/i }).first().click();
		await expect(page.getByRole('heading', { name: 'Add New Taxonomy' }).first()).toBeVisible();

		await page.locator('label[for="acf_taxonomy-advanced_configuration"]').click();
		await expect(page.locator('#acf-advanced-settings')).toBeVisible();
		await page.locator('#acf-advanced-settings').getByRole('link', { name: 'GraphQL' }).click();

		await expect(page.locator('#acf_taxonomy-show_in_graphql')).not.toBeChecked();
		await page.locator('input[name="acf_taxonomy[labels][singular_name]"]').fill('Test Type');
		await page.locator('input[name="acf_taxonomy[labels][name]"]').fill('Test Types');
		await page.locator('input[name="acf_taxonomy[taxonomy]"]').fill('test_type');
		await page.locator('label[for="acf_taxonomy-show_in_graphql"]').click();
		await page.getByLabel('GraphQL Single Name').fill('testSingleName');
		await page.getByLabel('GraphQL Plural Name').fill('testPluralName');

		await page.getByRole('button', { name: 'Save Changes' }).click();
		await page.waitForLoadState('networkidle');

		await expect(page.locator('input[name="acf_taxonomy[labels][singular_name]"]')).toHaveValue('Test Type');
		await expect(page.locator('input[name="acf_taxonomy[graphql_single_name]"]')).toHaveValue('testSingleName');
		await expect(page.locator('input[name="acf_taxonomy[graphql_plural_name]"]')).toHaveValue('testPluralName');

		await visitAdminFacingPage(page, TOOLS_URL);
		await expect(page.getByText('Select Taxonomies')).toBeVisible();
		await expect(page.locator('[data-name="taxonomy_keys"]').getByText('Test Types')).toBeVisible();
		await page.locator('[data-name="taxonomy_keys"]').getByRole('checkbox', { name: 'Test Types' }).check();
		await page.locator('#acf-admin-tool-export').getByRole('button', { name: 'Generate PHP' }).click();
		const textarea = page.locator('#acf-export-textarea');
		await expect(textarea).toBeVisible();
		const phpContent = await textarea.inputValue();
		expect(phpContent).toContain("'show_in_graphql' => true");
		expect(phpContent).toContain("'graphql_single_name' => 'testSingleName'");
		expect(phpContent).toContain("'graphql_plural_name' => 'testPluralName'");
	});
});

describeAcfUi('ACF UI Registration – Options Page', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAcfUiEntity(page, 'edit.php?post_type=acf-ui-options-page', 'Test Options Page');
	});

	test('options page can be registered to show in GraphQL', async ({ page }) => {
		await visitAdminFacingPage(page, `${wpAdminUrl}/edit.php?post_type=acf-ui-options-page`);
		await expect(page.getByText('Options Pages', { exact: true }).first()).toBeVisible();
		await page.locator('.acf-headerbar').getByRole('link', { name: /add new/i }).first().click();
		await expect(page.getByRole('heading', { name: 'Add New Options Page' }).first()).toBeVisible();

		await page.locator('label[for="acf_ui_options_page-advanced_configuration"]').click();
		await expect(page.locator('#acf-advanced-settings')).toBeVisible();
		await page.locator('#acf-advanced-settings').getByRole('link', { name: 'GraphQL' }).click();

		await expect(page.locator('#acf_ui_options_page-show_in_graphql')).not.toBeChecked();
		await page.locator('input[name="acf_ui_options_page[page_title]"]').fill('Test Options Page');
		await page.locator('input[name="acf_ui_options_page[menu_slug]"]').fill('test-options-page');
		await page.locator('select[name="acf_ui_options_page[parent_slug]"]').selectOption('none');
		await page.locator('label[for="acf_ui_options_page-show_in_graphql"]').click();
		await page.getByLabel('GraphQL Type Name').fill('TestOptionsPage');

		await page.getByRole('button', { name: 'Save Changes' }).click();
		await page.waitForLoadState('networkidle');

		await expect(page.locator('input[name="acf_ui_options_page[page_title]"]')).toHaveValue('Test Options Page');
		await expect(page.locator('input[name="acf_ui_options_page[graphql_type_name]"]')).toHaveValue('TestOptionsPage');

		await visitAdminFacingPage(page, TOOLS_URL);
		await expect(page.getByText('Select Options Pages')).toBeVisible();
		await expect(page.locator('[data-name="ui_options_page_keys"]').getByText('Test Options Page')).toBeVisible();
		await page.locator('[data-name="ui_options_page_keys"]').getByRole('checkbox', { name: 'Test Options Page' }).check();
		await page.locator('#acf-admin-tool-export').getByRole('button', { name: 'Generate PHP' }).click();
		const textarea = page.locator('#acf-export-textarea');
		await expect(textarea).toBeVisible();
		const phpContent = await textarea.inputValue();
		expect(phpContent).toMatch(/'show_in_graphql' => (true|1)/);
		expect(phpContent).toContain("'graphql_type_name' => 'TestOptionsPage'");
	});
});
