import {
	describe,
	test,
	expect,
	beforeEach,
	afterEach,
} from '@playwright/test';
import {
	loginToWordPressAdmin,
	importAcfJson,
	deleteAllAcfFieldGroups,
	navigateToFieldGroupEdit,
	openFieldByKeyAndGraphQLTab,
	submitFieldGroupForm,
	graphqlRequestAsAdmin,
} from '../utils.js';

/**
 * The GraphQL settings on a field in the field group editor: Show in GraphQL, GraphQL
 * Description and GraphQL Field Name. Each one is saved with the field group, shown again
 * after a reload, and reflected in the schema.
 *
 * Every field type gets these three settings from the same code
 * (`AcfGraphQLFieldType::get_admin_field_settings()`), so one text field covers the editor.
 * Each field type's GraphQL output is covered by the WPUnit tests in tests/wpunit/FieldTypes/.
 *
 * "Foo Name" in acf-export-2023-01-26.json (GraphQL type "FooGraphql") has one text field,
 * "foo_label", with Show in GraphQL on and no GraphQL Field Name saved. Opening the field
 * fills it in as "fooLabel".
 */
const TEST_JSON = 'acf-export-2023-01-26.json';
const FIELD_GROUP_TITLE = 'Foo Name';
const FIELD_KEY = 'field_63d2bb765f5af';

const FIELD_GROUP_TYPE_QUERY = `
  query {
    __type(name: "FooGraphql") {
      fields { name description }
    }
  }
`;

const field = (page) =>
	page.locator(`.acf-field-object[data-key="${FIELD_KEY}"]`).first();
const showInGraphqlCheckbox = (page) =>
	field(page).locator('[data-name="show_in_graphql"] input[type="checkbox"]');
const descriptionInput = (page) =>
	field(page).locator('[data-name="graphql_description"] input[type="text"]');
const fieldNameInput = (page) =>
	field(page).locator('[data-name="graphql_field_name"] input[type="text"]');

/**
 * Types into a setting and moves focus away, so the browser fires `change` there and then, as
 * it does for a person, rather than when clicking Save blurs the input. `fill()` alone only
 * fires `input`, and ACF only saves the settings of a field it has seen change.
 *
 * @param {import('@playwright/test').Locator} input The setting's input.
 * @param {string}                             value The value to type.
 */
async function setSetting(input, value) {
	await input.fill(value);
	await input.press('Tab');
}

/**
 * Saves the field group, reloads the editor, and opens the field's GraphQL tab again, so the
 * assertions that follow read what was saved rather than what is still in the form.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function saveAndReopen(page) {
	await submitFieldGroupForm(page);
	await expect(page.locator('.notice-error')).toHaveCount(0);
	await page.reload();
	await openFieldByKeyAndGraphQLTab(page, FIELD_KEY);
}

/**
 * The fields on the field group's GraphQL type. The type stays in the schema when its only
 * field is hidden (it keeps `fieldGroupName`), so a missing type fails here rather than
 * passing a "field is not in the schema" check.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<Array<{name: string, description: string}>>} The type's fields.
 */
async function getSchemaFields(page) {
	const res = await graphqlRequestAsAdmin(page, FIELD_GROUP_TYPE_QUERY);
	expect(res.errors).toBeUndefined();
	expect(res.data.__type).not.toBeNull();
	return res.data.__type.fields;
}

describe('GraphQL settings on a field in the field group editor', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, TEST_JSON);
		await navigateToFieldGroupEdit(page, FIELD_GROUP_TITLE);
		await openFieldByKeyAndGraphQLTab(page, FIELD_KEY);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('saves the GraphQL Description and GraphQL Field Name, and the schema uses them', async ({
		page,
	}) => {
		await expect(showInGraphqlCheckbox(page)).toBeChecked();
		await expect(fieldNameInput(page)).toHaveValue('fooLabel');

		await setSetting(descriptionInput(page), 'The label shown to readers.');
		await setSetting(fieldNameInput(page), 'readerLabel');
		await saveAndReopen(page);

		await expect(descriptionInput(page)).toHaveValue(
			'The label shown to readers.'
		);
		await expect(fieldNameInput(page)).toHaveValue('readerLabel');

		const fields = await getSchemaFields(page);
		expect(fields.map(({ name }) => name)).not.toContain('fooLabel');
		expect(fields).toContainEqual({
			name: 'readerLabel',
			description: 'The label shown to readers.',
		});
	});

	test('saves Show in GraphQL turned off, and the field leaves the schema', async ({
		page,
	}) => {
		expect((await getSchemaFields(page)).map(({ name }) => name)).toContain(
			'fooLabel'
		);

		// The setting is an ACF switch, which hides the checkbox behind it.
		await field(page)
			.locator('[data-name="show_in_graphql"] .acf-switch')
			.click();
		await expect(showInGraphqlCheckbox(page)).not.toBeChecked();
		await saveAndReopen(page);

		await expect(showInGraphqlCheckbox(page)).not.toBeChecked();
		expect(
			(await getSchemaFields(page)).map(({ name }) => name)
		).not.toContain('fooLabel');
	});
});
