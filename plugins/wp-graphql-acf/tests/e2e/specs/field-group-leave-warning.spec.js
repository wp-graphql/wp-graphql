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

/**
 * Regression test for wp-graphql/wp-graphql#4560.
 *
 * When a field group has "Show in GraphQL" on and its GraphQL Types come from the location rules,
 * the admin script fetches those types on load and syncs the (disabled) GraphQL Types checkboxes,
 * triggering a change event on each. ACF treated those as unsaved edits, so leaving the screen
 * without changing anything showed a "Leave site?" prompt.
 *
 * "Foo Name" in acf-export-2023-01-26.json has "Show in GraphQL" on and its GraphQL Types mapped
 * from its location rules (Post Type is equal to Post).
 */
const TEST_JSON = 'acf-export-2023-01-26.json';
const FIELD_GROUP_TITLE = 'Foo Name';

/**
 * Opens the field group's edit screen and waits for the GraphQL Types sync to finish.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openFieldGroupAndWaitForTypeSync(page) {
	await visitAdminFacingPage(
		page,
		`${wpAdminUrl}/edit.php?post_type=acf-field-group`
	);

	const syncResponse = page.waitForResponse(
		(response) =>
			response.url().includes('admin-ajax.php') &&
			(response.request().postData() || '').includes(
				'get_acf_field_group_graphql_types'
			)
	);

	// Navigate to the edit link directly: the row title link isn't reliably clickable in ACF's list.
	const editUrl = await page
		.locator('.wp-list-table tbody tr')
		.filter({ hasText: FIELD_GROUP_TITLE })
		.first()
		.locator('a.row-title')
		.getAttribute('href');
	await page.goto(editUrl);

	await syncResponse;

	// The response handler clears this attribute once the checkboxes are synced.
	await expect(page.locator('#post')).not.toHaveAttribute(
		'data-request-pending',
		'pending'
	);
}

describe('Field group edit screen leave warning', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, TEST_JSON);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('opening a field group without editing it does not arm the leave warning', async ({
		page,
	}) => {
		await openFieldGroupAndWaitForTypeSync(page);

		// The sync did set the GraphQL Types from the location rules...
		await expect(
			page.locator(
				'.acf-field[data-name="graphql_types"] input[type="checkbox"][value="Post"]'
			)
		).toBeChecked();

		// ...without counting as an unsaved change.
		expect(await page.evaluate(() => globalThis.acf.unload.changed)).toBe(
			false
		);
	});

	test('a real edit still arms the leave warning', async ({ page }) => {
		await openFieldGroupAndWaitForTypeSync(page);

		// Edit the field group's GraphQL Type Name the way a user's edit reaches ACF: a change event
		// on an input inside an ACF field. (The setting can sit in a collapsed tab, so set it directly.)
		await page.evaluate(() => {
			globalThis
				.jQuery('#acf_field_group-graphql_field_name')
				.val('EditedTypeName')
				.trigger('change');
		});

		expect(await page.evaluate(() => globalThis.acf.unload.changed)).toBe(
			true
		);
	});
});
