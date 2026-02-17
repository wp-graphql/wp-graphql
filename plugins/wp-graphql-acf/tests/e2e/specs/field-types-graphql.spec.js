import { test, expect, beforeEach } from '@playwright/test';
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
 * Mirrors: AcfFieldCest + FieldTypes/*Cest (seeShowInGraphqlField, seeShowInGraphqlWarns...,
 * testSavingShowInGraphqlField, seeGraphqlDescriptionField, testSavingGraphqlDescriptionField,
 * seeGraphqlFieldNameField, testSavingGraphqlFieldNameField).
 */

const DEFAULT_JSON = 'acf-export-2023-01-26.json';
const DEFAULT_FIELD_KEY = 'field_63d2bb765f5af';
const FIELD_GROUP_TITLE = 'Foo Name';

/** @type {{ type: string; jsonFile: string; fieldKey: string }[]} */
const FIELD_TYPE_CONFIGS = [
	{ type: 'text', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
	{ type: 'checkbox', jsonFile: DEFAULT_JSON, fieldKey: DEFAULT_FIELD_KEY },
];

test.describe('Field types GraphQL UI', () => {
	for (const config of FIELD_TYPE_CONFIGS) {
		test.describe(`${config.type} field`, () => {
			beforeEach(async ({ page }) => {
				await loginToWordPressAdmin(page);
				await deleteAllAcfFieldGroups(page);
				await importAcfJson(page, config.jsonFile);
				await navigateToFieldGroupEdit(page, FIELD_GROUP_TITLE);
				await openFieldByKeyAndGraphQLTab(
					page,
					config.fieldKey,
					config.type === 'text' ? null : config.type
				);
			});

			test('shows Show in GraphQL and breaking change note', async ({ page }) => {
				const panel = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				await expect(
					panel.locator('[data-name="show_in_graphql"]').getByText('Show in GraphQL')
				).toBeVisible();
				await expect(
					panel.locator('[data-name="show_in_graphql"] .description').getByText(/breaking change/i)
				).toBeVisible();
			});

			test('saves Show in GraphQL (uncheck then check + field name)', async ({ page }) => {
				const panel = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				const showInGraphql = panel.locator('[data-name="show_in_graphql"]').first();
				// ACF uses a .acf-switch overlay; click the visible switch (or label), not the hidden checkbox
				const switchEl = showInGraphql.locator('.acf-switch').first();
				if ((await switchEl.count()) > 0) {
					await switchEl.click();
				} else {
					const checkbox = showInGraphql.locator('input[type="checkbox"]');
					if ((await checkbox.count()) > 0) {
						await checkbox.uncheck();
					} else {
						await showInGraphql.locator('label').click();
					}
				}
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(
					page,
					config.fieldKey,
					config.type === 'text' ? null : config.type
				);
				const panel2 = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				const checkbox2 = panel2.locator('[data-name="show_in_graphql"] input[type="checkbox"]');
				if ((await checkbox2.count()) > 0) {
					await expect(checkbox2).not.toBeChecked();
				}

				const showInGraphql2 = panel2.locator('[data-name="show_in_graphql"]').first();
				const switchEl2 = showInGraphql2.locator('.acf-switch').first();
				if ((await switchEl2.count()) > 0) {
					await switchEl2.click();
				} else if ((await checkbox2.count()) > 0) {
					await checkbox2.check();
				} else {
					await showInGraphql2.locator('label').click();
				}
				await panel2.locator('[data-name="graphql_field_name"] input[type="text"]').fill('newFieldName');
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(
					page,
					config.fieldKey,
					config.type === 'text' ? null : config.type
				);
				const panel3 = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				const checkbox3 = panel3.locator('[data-name="show_in_graphql"] input[type="checkbox"]');
				if ((await checkbox3.count()) > 0) {
					await expect(checkbox3).toBeChecked();
				}
			});

			test('shows and saves GraphQL Description', async ({ page }) => {
				const panel = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				await expect(
					panel.locator('[data-name="graphql_description"]').getByText('GraphQL Description')
				).toBeVisible();
				const input = panel.locator('[data-name="graphql_description"] input[type="text"]');
				await input.fill('test description...');
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(
					page,
					config.fieldKey,
					config.type === 'text' ? null : config.type
				);
				const panel2 = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				await expect(panel2.locator('[data-name="graphql_description"] input[type="text"]')).toHaveValue(
					'test description...'
				);
			});

			test('shows and saves GraphQL Field Name', async ({ page }) => {
				const panel = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				await expect(
					panel.locator('[data-name="graphql_field_name"]').getByText('GraphQL Field Name')
				).toBeVisible();
				const input = panel.locator('[data-name="graphql_field_name"] input[type="text"]');
				await input.fill('newFieldName');
				await submitFieldGroupForm(page);
				await expect(page.locator('#message.notice-error')).not.toBeVisible();
				await expect(page.locator('#message.notice-success')).toBeVisible();

				await openFieldByKeyAndGraphQLTab(
					page,
					config.fieldKey,
					config.type === 'text' ? null : config.type
				);
				const panel2 = page.locator(`div[data-key="${config.fieldKey}"]`).first();
				await expect(panel2.locator('[data-name="graphql_field_name"] input[type="text"]')).toHaveValue(
					'newFieldName'
				);
			});
		});
	}
});
