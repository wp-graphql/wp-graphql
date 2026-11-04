import { describe, test, expect, beforeEach } from '@playwright/test';
import {
	loginToWordPressAdmin,
	visitAdminFacingPage,
	wpAdminUrl,
} from '../utils.js';
import { acfPro, acfExtended, acfExtendedPro } from '../env.js';

/**
 * The GraphQL tab in the field group editor, for every field type an admin can pick.
 *
 * When an admin picks a field type, ACF's editor asks the server for that type's settings
 * (`acf/field_group/render_field_settings`) and puts each returned section into its tab. The
 * first test makes that same request for every type in the field type dropdown and checks
 * the `graphql` section, which is quick and doesn't depend on the editor's timing. The second
 * test picks a type in the editor itself, to show the editor puts that section in the tab.
 *
 * Saving these settings is covered by field-graphql-settings.spec.js, and each type's schema
 * output by the WPUnit tests in tests/wpunit/FieldTypes/.
 */

/** Field types in ACF Free that the plugin supports. */
const ACF_FREE_TYPES = [
	'text',
	'textarea',
	'number',
	'range',
	'email',
	'url',
	'password',
	'image',
	'file',
	'wysiwyg',
	'oembed',
	'select',
	'checkbox',
	'radio',
	'button_group',
	'true_false',
	'link',
	'post_object',
	'page_link',
	'relationship',
	'taxonomy',
	'user',
	'google_map',
	'date_picker',
	'date_time_picker',
	'time_picker',
	'color_picker',
	'group',
];

/**
 * Field types only ACF PRO has, which the plugin supports. ACF PRO's Clone field is supported
 * too, but its GraphQL tab explains that a clone takes its settings from the cloned fields, so
 * it is checked on its own.
 */
const ACF_PRO_TYPES = ['repeater', 'flexible_content', 'gallery'];

/** Field types in ACF Extended (Free and Pro) that the plugin supports. */
const ACF_EXTENDED_TYPES = [
	'acfe_advanced_link',
	'acfe_code_editor',
	'acfe_taxonomies',
	'acfe_taxonomy_terms',
	'acfe_user_roles',
];

/** Field types only ACF Extended Pro has, which the plugin supports. */
const ACF_EXTENDED_PRO_TYPES = [
	'acfe_countries',
	'acfe_currencies',
	'acfe_date_range_picker',
	'acfe_image_selector',
	'acfe_image_sizes',
	'acfe_languages',
	'acfe_menu_locations',
	'acfe_menus',
	'acfe_phone_number',
	'acfe_post_formats',
];

/** The supported field types the installed plugins should offer. */
const SUPPORTED_TYPES = [
	...ACF_FREE_TYPES,
	...(acfPro ? ACF_PRO_TYPES : []),
	...(acfExtended ? ACF_EXTENDED_TYPES : []),
	...(acfExtendedPro ? ACF_EXTENDED_PRO_TYPES : []),
];

const GRAPHQL_SETTINGS = [
	'show_in_graphql',
	'graphql_description',
	'graphql_field_name',
];

const NOT_SUPPORTED_MESSAGE = 'Not supported in the GraphQL Schema';
const CLONE_MESSAGE = 'GraphQL Settings for Clone Fields';

/**
 * Opens a new field group. ACF starts it with one empty field, open for editing.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function openNewFieldGroup(page) {
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/post-new.php?post_type=acf-field-group`
	);
	await page
		.locator('.acf-field-object select.field-type')
		.first()
		.waitFor({ state: 'attached' });
}

/**
 * For every type in the new field's type dropdown, requests the type's settings the way the
 * editor does when an admin picks it, and reports what its GraphQL section contains: which of
 * the GraphQL settings it has, and its text.
 *
 * @param {import('@playwright/test').Page} page The page, on a new field group.
 * @return {Promise<Object<string, {settings: string[], text: string}>>} By field type.
 */
async function getGraphqlSectionByType(page) {
	return page.evaluate(async (settingNames) => {
		const acf = globalThis.acf;
		const $field = jQuery('.acf-field-object').first();
		const field = acf.getFieldObject($field);
		const types = $field
			.find('select.field-type option')
			.map((i, option) => option.value)
			.get()
			.filter(Boolean);

		const sections = {};
		for (const type of types) {
			const response = await jQuery.ajax({
				url: acf.get('ajaxurl'),
				type: 'post',
				dataType: 'json',
				data: acf.prepareForAjax({
					action: 'acf/field_group/render_field_settings',
					field: { ...field.serialize(), type },
					prefix: field.getInputName(),
				}),
			});
			const html = response?.success ? response.data.graphql : '';
			const section = new globalThis.DOMParser().parseFromString(
				html ?? '',
				'text/html'
			);
			sections[type] = {
				settings: settingNames.filter((name) =>
					section.querySelector(`[data-name="${name}"]`)
				),
				text: section.body.textContent,
			};
		}
		return sections;
	}, GRAPHQL_SETTINGS);
}

describe('The GraphQL tab for each field type in the field group editor', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await openNewFieldGroup(page);
	});

	test('shows the GraphQL settings for every supported field type, and says when one is not supported', async ({
		page,
	}) => {
		const sections = await getGraphqlSectionByType(page);

		// Every type the plugin supports is offered and shows the three settings. Listing them
		// means a type that went missing from the dropdown fails here instead of being skipped.
		for (const type of SUPPORTED_TYPES) {
			expect(sections[type], `"${type}" in the dropdown`).toBeDefined();
			expect(sections[type].settings, `"${type}" settings`).toEqual(
				GRAPHQL_SETTINGS
			);
		}

		if (acfPro) {
			expect(sections.clone, '"clone" in the dropdown').toBeDefined();
			expect(sections.clone.text).toContain(CLONE_MESSAGE);
		}

		// Any other type, such as a layout field or one a new ACF release adds, still gets a
		// working tab: the settings if the plugin supports it, or the not-supported message.
		for (const [type, section] of Object.entries(sections)) {
			if (SUPPORTED_TYPES.includes(type) || type === 'clone') {
				continue;
			}
			const showsSettings =
				section.settings.length === GRAPHQL_SETTINGS.length;
			expect(
				showsSettings || section.text.includes(NOT_SUPPORTED_MESSAGE),
				`"${type}" GraphQL tab`
			).toBe(true);
		}
	});

	test('shows the GraphQL settings after picking a field type in the editor', async ({
		page,
	}) => {
		const field = page.locator('.acf-field-object').first();

		// Pick it the way ACF's own dropdown does (Select2 when ACF enhances it), then wait
		// until ACF has switched the field over, so the tab is the new type's.
		await field.locator('select.field-type').evaluate((select) => {
			jQuery(select).val('number').trigger('change');
		});
		// ACF sets data-type straight away and swaps the settings in once the server replies, so
		// also wait for a setting only Number has (Minimum Value, on the Validation tab).
		await expect(field).toHaveAttribute('data-type', 'number');
		await expect(field.locator('[data-name="min"]')).toBeAttached();

		// ACF builds the visible tab bar from a hidden Tab field that has its own copy of each
		// tab link, so pick the tab by its role, which only matches the visible one.
		await field.getByRole('link', { name: 'GraphQL', exact: true }).click();
		for (const name of GRAPHQL_SETTINGS) {
			await expect(
				field.locator(`[data-name="${name}"]`).first()
			).toBeVisible();
		}
	});
});
