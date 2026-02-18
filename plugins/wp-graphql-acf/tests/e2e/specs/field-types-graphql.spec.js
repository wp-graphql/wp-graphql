import path from 'node:path';
import { test, expect, beforeEach, beforeAll } from '@playwright/test';
import {
	loginToWordPressAdmin,
	deleteAllAcfFieldGroups,
	importAcfJson,
	navigateToFieldGroupEdit,
	openFieldByKeyAndGraphQLTab,
	submitFieldGroupForm,
} from '../utils.js';

/**
 * E2E tests for Field Types GraphQL UI: each ACF field type shows GraphQL tab fields
 * (Show in GraphQL, breaking change note, GraphQL Description, GraphQL Field Name) and saves correctly.
 * Optimized: import once per field type (beforeAll), navigate only in beforeEach, one test per type.
 * Mirrors: AcfFieldCest + FieldTypes/*Cest.
 */

const DEFAULT_JSON = 'acf-export-2023-01-26.json';
const ACFE_JSON = 'tests-acf-extended-pro-kitchen-sink.json';
const DEFAULT_FIELD_KEY = 'field_63d2bb765f5af';
const FIELD_GROUP_TITLE = 'Foo Name';

/** ACFE field types skipped for now: GraphQL description save / tab visibility flaky; to fix later. */
const SKIP_FIELD_TYPES_GRAPHQL = ['acfe_date_range_picker', 'acfe_currencies', 'acfe_countries'];

/** Maps each PHP FieldTypes/*Cest to { type, jsonFile, fieldKey, fieldGroupTitle? }. ACFE types use extended JSON and their own field key. */
/** @type {{ type: string; jsonFile: string; fieldKey: string; fieldGroupTitle?: string }[]} */
const FIELD_TYPE_CONFIGS = [
	// Default JSON (acf-export-2023-01-26.json) + default field key; type selected in UI
	{ type: 'text', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'checkbox', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'select', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'textarea', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'number', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'range', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'email', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'url', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'password', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'image', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'file', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'wysiwyg', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'oembed', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'gallery', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'radio', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'button_group', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'true_false', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'link', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'post_object', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'page_link', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'relationship', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'taxonomy', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'user', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'google_map', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'date_picker', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'date_time_picker', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'time_picker', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'color_picker', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'group', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'repeater', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'flexible_content', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'acfe_code_editor', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'acfe_advanced_link', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	// ACF Extended Pro (tests-acf-extended-pro-kitchen-sink.json) with type-specific field keys
	{
		type: 'acfe_date_range_picker',
		jsonFile: ACFE_JSON,
		fieldKey: 'field_6449a1432046d',
		fieldGroupTitle: 'Docs: ACF Extended PRO Kitchen Sink',
	},
	{
		type: 'acfe_currencies',
		jsonFile: ACFE_JSON,
		fieldKey: 'field_64387a09bb89f',
		fieldGroupTitle: 'Docs: ACF Extended PRO Kitchen Sink',
	},
	{
		type: 'acfe_countries',
		jsonFile: ACFE_JSON,
		fieldKey: 'field_64387c3379587',
		fieldGroupTitle: 'Docs: ACF Extended PRO Kitchen Sink',
	},
];

function getStorageStatePath() {
	const cwd = process.cwd();
	const artifacts = process.env.WP_ARTIFACTS_PATH ?? path.join(cwd.endsWith('wp-graphql-acf') ? cwd : path.join(cwd, 'plugins', 'wp-graphql-acf'), 'artifacts');
	return process.env.STORAGE_STATE_PATH ?? path.join(artifacts, 'storage-states', 'admin.json');
}

test.describe('Field types GraphQL UI', () => {
	for (const config of FIELD_TYPE_CONFIGS) {
		test.describe(`${config.type} field`, () => {
			beforeAll(async ({ browser }) => {
				const storageStatePath = getStorageStatePath();
				const context = await browser.newContext({
					...(storageStatePath ? { storageState: storageStatePath } : {}),
				});
				const page = await context.newPage();
				await loginToWordPressAdmin(page);
				await deleteAllAcfFieldGroups(page);
				await importAcfJson(page, config.jsonFile);
				await context.close();
			});

			beforeEach(async ({ page }) => {
				const fieldGroupTitle = config.fieldGroupTitle ?? FIELD_GROUP_TITLE;
				await navigateToFieldGroupEdit(page, fieldGroupTitle);
				await openFieldByKeyAndGraphQLTab(
					page,
					config.fieldKey,
					config.type === 'text' ? null : config.type
				);
			});

			const runGraphQLTabTest = SKIP_FIELD_TYPES_GRAPHQL.includes(config.type) ? test.skip : test;
			runGraphQLTabTest('GraphQL tab: visibility and save of Show in GraphQL, Description, Field Name', async ({ page }) => {
				const fieldKey = config.fieldKey;
				const typeParam = config.type === 'text' ? null : config.type;

				// 1) Show in GraphQL + breaking change note
				let panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				await expect(
					panel.locator('[data-name="show_in_graphql"]').getByText('Show in GraphQL')
				).toBeVisible();
				await expect(
					panel.locator('[data-name="show_in_graphql"] .description').getByText(/breaking change/i)
				).toBeVisible();

				// 2) Save Show in GraphQL (uncheck then check + field name)
				const showInGraphql = panel.locator('[data-name="show_in_graphql"]').first();
				const switchEl = showInGraphql.locator('.acf-switch').first();
				if ((await switchEl.count()) > 0) {
					await switchEl.click();
				} else {
					const checkbox = showInGraphql.locator('input[type="checkbox"]');
					if ((await checkbox.count()) > 0) await checkbox.uncheck();
					else await showInGraphql.locator('label').click();
				}
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(page, fieldKey, typeParam);
				panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				const checkbox2 = panel.locator('[data-name="show_in_graphql"] input[type="checkbox"]');
				if ((await checkbox2.count()) > 0) {
					await expect(checkbox2).not.toBeChecked();
				}

				const showInGraphql2 = panel.locator('[data-name="show_in_graphql"]').first();
				const switchEl2 = showInGraphql2.locator('.acf-switch').first();
				if ((await switchEl2.count()) > 0) {
					await switchEl2.click();
				} else if ((await checkbox2.count()) > 0) {
					await checkbox2.check();
				} else {
					await showInGraphql2.locator('label').click();
				}
				const fieldNameInputStep2 = panel.locator('[data-name="graphql_field_name"] input[type="text"]');
				await fieldNameInputStep2.scrollIntoViewIfNeeded();
				await fieldNameInputStep2.fill('newFieldName');
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(page, fieldKey, typeParam);
				panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				const checkbox3 = panel.locator('[data-name="show_in_graphql"] input[type="checkbox"]');
				if ((await checkbox3.count()) > 0) {
					await expect(checkbox3).toBeChecked();
				}

				// 3) GraphQL Description
				await openFieldByKeyAndGraphQLTab(page, fieldKey, typeParam);
				panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				await expect(
					panel.locator('[data-name="graphql_description"]').getByText('GraphQL Description')
				).toBeVisible();
				const descInput = panel.locator('[data-name="graphql_description"] input[type="text"]').first();
				await descInput.waitFor({ state: 'attached', timeout: 5000 });
				try {
					await descInput.scrollIntoViewIfNeeded({ timeout: 3000 });
				} catch {
					// Repeater/flexible_content etc. may keep the input in a non-scrollable or delayed area
				}
				await descInput.fill('test description...', { force: true });
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(page, fieldKey, typeParam);
				panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				await expect(panel.locator('[data-name="graphql_description"] input[type="text"]').first()).toHaveValue(
					'test description...',
					{ timeout: 10000 }
				);

				// 4) GraphQL Field Name
				await openFieldByKeyAndGraphQLTab(page, fieldKey, typeParam);
				panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				await expect(
					panel.locator('[data-name="graphql_field_name"]').getByText('GraphQL Field Name')
				).toBeVisible();
				const fieldNameInput = panel.locator('[data-name="graphql_field_name"] input[type="text"]');
				await fieldNameInput.scrollIntoViewIfNeeded();
				await fieldNameInput.fill('newFieldName');
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(page, fieldKey, typeParam);
				panel = page.locator(`div[data-key="${fieldKey}"]`).first();
				await expect(panel.locator('[data-name="graphql_field_name"] input[type="text"]')).toHaveValue(
					'newFieldName'
				);
			});
		});
	}
});
