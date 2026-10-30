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
} from '../utils.js';

/**
 * Regression test for wp-graphql/wp-graphql#4528.
 *
 * Duplicating a field in the field group editor copies all of its settings, including its
 * GraphQL Field Name. ACF gives the copy a new name, but the copy kept the original's GraphQL
 * Field Name, so the two fields shared one GraphQL field name and only one of them made it into
 * the schema.
 *
 * "Foo Name" in acf-export-2023-01-26.json (GraphQL type "FooGraphql") has one text field, "foo_label",
 * with no GraphQL Field Name saved. Opening the field fills it in as "fooLabel".
 */
const TEST_JSON = 'acf-export-2023-01-26.json';
const FIELD_GROUP_TITLE = 'Foo Name';
const FIELD_KEY = 'field_63d2bb765f5af';

const FIELD_GROUP_TYPE_QUERY = `
  query {
    __type(name: "FooGraphql") {
      fields { name }
    }
  }
`;

/**
 * Runs a GraphQL query as the signed-in admin, from the admin page, so introspection works
 * whatever the site's public introspection and debug settings are.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          query
 * @return {Promise<{ data?: unknown; errors?: unknown }>} The parsed response.
 */
async function graphqlRequestAsAdmin(page, query) {
	return page.evaluate(async (graphqlQuery) => {
		const nonce = await (
			await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
				credentials: 'same-origin',
			})
		).text();
		const response = await fetch('/graphql', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify({ query: graphqlQuery }),
		});
		return response.json();
	}, query);
}

describe('Duplicating a field in the field group editor', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, TEST_JSON);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('gives the copy a GraphQL Field Name from its new name, so both fields are in the schema', async ({
		page,
	}) => {
		await navigateToFieldGroupEdit(page, FIELD_GROUP_TITLE);
		await openFieldByKeyAndGraphQLTab(page, FIELD_KEY);

		const original = page
			.locator(`.acf-field-object[data-key="${FIELD_KEY}"]`)
			.first();
		const graphqlFieldNameInput = '[data-name="graphql_field_name"] input';

		await expect(
			original.locator(graphqlFieldNameInput).first()
		).toHaveValue('fooLabel');

		await original.hover();
		await original.locator('a.duplicate-field').first().click();

		// ACF inserts the copy right after the original.
		const copy = original.locator(
			'xpath=following-sibling::div[contains(concat(" ", normalize-space(@class), " "), " acf-field-object ")][1]'
		);
		await expect(copy).not.toHaveAttribute('data-key', FIELD_KEY);
		await expect(
			copy.locator('[data-name="name"] input').first()
		).toHaveValue('foo_label_copy');

		// The copy's GraphQL Field Name follows its new name, and the original keeps its own.
		await expect(copy.locator(graphqlFieldNameInput).first()).toHaveValue(
			'fooLabelCopy'
		);
		await expect(
			original.locator(graphqlFieldNameInput).first()
		).toHaveValue('fooLabel');

		await submitFieldGroupForm(page);
		await expect(page.locator('.notice-error')).toHaveCount(0);

		const res = await graphqlRequestAsAdmin(page, FIELD_GROUP_TYPE_QUERY);
		const fieldNames = (res?.data?.__type?.fields ?? []).map(
			(field) => field.name
		);

		expect(fieldNames).toEqual(
			expect.arrayContaining(['fooLabel', 'fooLabelCopy'])
		);
	});
});
