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
	deleteAcfUiEntity,
	graphqlRequest,
	graphqlRequestAsAdmin,
	navigateToFieldGroupEdit,
	openFieldByKeyAndGraphQLTab,
	submitFieldGroupForm,
	wpAdminUrl,
} from '../utils.js';
import { skipWhenNotAcfPro } from '../env.js';

/**
 * E2E tests for the "GraphQL Capability" setting on field groups, fields and options pages.
 *
 * Each test sets the capability in the admin UI, saves, and then queries GraphQL twice: as a
 * visitor (the `request` fixture sends no nonce, so WPGraphQL treats it as a logged-out user)
 * and as the signed-in admin. The visitor gets null where a capability is required, and the
 * admin gets the values.
 *
 * "Capability Test" in acf-graphql-capability.json (GraphQL field "capabilityTest", on posts)
 * has two text fields, "public_note" and "staff_note", with no capability set. It's shown in
 * REST so the test can create a post with values for both fields.
 */
const TEST_JSON = 'acf-graphql-capability.json';
const FIELD_GROUP_TITLE = 'Capability Test';
const STAFF_NOTE_KEY = 'field_6aec0a1f2c0e2';
const CAPABILITY_INPUT = '[data-name="graphql_capability"] input';
const GROUP_CAPABILITY_INPUT = 'input[name="acf_field_group[graphql_capability]"]';

const POST_QUERY = `
  query GetPost($id: ID!) {
    post(id: $id, idType: DATABASE_ID) {
      capabilityTest {
        publicNote
        staffNote
      }
    }
  }
`;

/**
 * Creates a published post with values for both fields, through the REST API as the admin.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<number>} The new post's ID.
 */
async function createPostWithNotes(page) {
	await visitAdminFacingPage(page, wpAdminUrl);
	const result = await page.evaluate(async () => {
		const nonce = await (
			await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
				credentials: 'same-origin',
			})
		).text();
		const response = await fetch('/?rest_route=/wp/v2/posts', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify({
				title: 'Capability Test Post',
				status: 'publish',
				acf: {
					public_note: 'public value',
					staff_note: 'staff value',
				},
			}),
		});
		return { status: response.status, body: await response.text() };
	});
	// 201 Created, with the post (and the ACF values) saved.
	expect(result.status, result.body).toBe(201);
	return JSON.parse(result.body).id;
}

/**
 * Queries the post as a visitor and as the admin.
 *
 * @param {import('@playwright/test').Page}              page
 * @param {import('@playwright/test').APIRequestContext} request
 * @param {number}                                       postId
 * @return {Promise<{ visitor: unknown; admin: unknown }>} Each response's `capabilityTest` field.
 */
async function queryAsVisitorAndAdmin(page, request, postId) {
	const visitor = await graphqlRequest(request, POST_QUERY, {
		id: String(postId),
	});
	const admin = await graphqlRequestAsAdmin(page, POST_QUERY, {
		id: String(postId),
	});
	expect(visitor.errors).toBeUndefined();
	expect(admin.errors).toBeUndefined();
	return {
		visitor: visitor.data.post.capabilityTest,
		admin: admin.data.post.capabilityTest,
	};
}

describe('GraphQL Capability on field groups and fields', () => {
	let postId;

	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, TEST_JSON);
		postId = await createPostWithNotes(page);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('a capability set on a field hides only that field from users without it', async ({
		page,
		request,
	}) => {
		const before = await queryAsVisitorAndAdmin(page, request, postId);
		expect(before.visitor).toEqual({
			publicNote: 'public value',
			staffNote: 'staff value',
		});

		await navigateToFieldGroupEdit(page, FIELD_GROUP_TITLE);
		await openFieldByKeyAndGraphQLTab(page, STAFF_NOTE_KEY);
		const field = page.locator(`div[data-key="${STAFF_NOTE_KEY}"]`).first();
		await field.locator(CAPABILITY_INPUT).first().fill('manage_options');
		await submitFieldGroupForm(page);
		await expect(page.locator('.notice-error')).toHaveCount(0);

		// The value is still there after the page reloads.
		await openFieldByKeyAndGraphQLTab(page, STAFF_NOTE_KEY);
		await expect(field.locator(CAPABILITY_INPUT).first()).toHaveValue(
			'manage_options'
		);

		const after = await queryAsVisitorAndAdmin(page, request, postId);
		expect(after.visitor).toEqual({
			publicNote: 'public value',
			staffNote: null,
		});
		expect(after.admin).toEqual({
			publicNote: 'public value',
			staffNote: 'staff value',
		});
	});

	test('a capability set on a field group hides the group, and clearing it makes it public again', async ({
		page,
		request,
	}) => {
		await navigateToFieldGroupEdit(page, FIELD_GROUP_TITLE);
		await page
			.locator('#acf-field-group-options .acf-tab-button', {
				hasText: 'GraphQL',
			})
			.first()
			.click();
		await page.locator(GROUP_CAPABILITY_INPUT).fill('manage_options');
		await submitFieldGroupForm(page);
		await expect(page.locator('.notice-error')).toHaveCount(0);
		await expect(page.locator(GROUP_CAPABILITY_INPUT)).toHaveValue(
			'manage_options'
		);

		const restricted = await queryAsVisitorAndAdmin(page, request, postId);
		expect(restricted.visitor).toBeNull();
		expect(restricted.admin).toEqual({
			publicNote: 'public value',
			staffNote: 'staff value',
		});

		await page
			.locator('#acf-field-group-options .acf-tab-button', {
				hasText: 'GraphQL',
			})
			.first()
			.click();
		await page.locator(GROUP_CAPABILITY_INPUT).fill('');
		await submitFieldGroupForm(page);
		await expect(page.locator(GROUP_CAPABILITY_INPUT)).toHaveValue('');

		const cleared = await queryAsVisitorAndAdmin(page, request, postId);
		expect(cleared.visitor).toEqual({
			publicNote: 'public value',
			staffNote: 'staff value',
		});
	});
});

/**
 * Options pages registered in the admin UI need ACF PRO 6.2+, so this suite is skipped on ACF Free.
 */
const describeOptionsPage = skipWhenNotAcfPro() ? describe.skip : describe;

const OPTIONS_PAGE_LIST = 'edit.php?post_type=acf-ui-options-page';
const OPTIONS_PAGE_TITLE = 'Capability Options';
const OPTIONS_PAGE_QUERY = `
  query {
    capabilityOptions {
      pageTitle
    }
  }
`;

describeOptionsPage('GraphQL Capability on options pages', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAcfUiEntity(page, OPTIONS_PAGE_LIST, OPTIONS_PAGE_TITLE);
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAcfUiEntity(page, OPTIONS_PAGE_LIST, OPTIONS_PAGE_TITLE);
	});

	test('a capability set on an options page hides it from users without it', async ({
		page,
		request,
	}) => {
		await visitAdminFacingPage(
			page,
			`${wpAdminUrl}/post-new.php?post_type=acf-ui-options-page`
		);
		await page
			.locator('input[name="acf_ui_options_page[page_title]"]')
			.fill(OPTIONS_PAGE_TITLE);
		await page
			.locator('input[name="acf_ui_options_page[menu_slug]"]')
			.fill('capability-options');
		await page
			.locator('select[name="acf_ui_options_page[parent_slug]"]')
			.selectOption('none');
		await page
			.locator('label[for="acf_ui_options_page-advanced_configuration"]')
			.click();
		await page
			.locator('#acf-advanced-settings')
			.getByRole('link', { name: 'GraphQL' })
			.click();
		await page
			.locator('label[for="acf_ui_options_page-show_in_graphql"]')
			.click();
		await page.getByLabel('GraphQL Type Name').fill('CapabilityOptions');
		await page
			.locator('input[name="acf_ui_options_page[graphql_capability]"]')
			.fill('edit_posts');

		await page.getByRole('button', { name: 'Save Changes' }).click();
		await page.waitForLoadState('networkidle');

		await expect(
			page.locator('input[name="acf_ui_options_page[graphql_capability]"]')
		).toHaveValue('edit_posts');

		const visitor = await graphqlRequest(request, OPTIONS_PAGE_QUERY);
		expect(visitor.errors).toBeUndefined();
		expect(visitor.data.capabilityOptions).toBeNull();

		const admin = await graphqlRequestAsAdmin(page, OPTIONS_PAGE_QUERY);
		expect(admin.errors).toBeUndefined();
		expect(admin.data.capabilityOptions).toEqual({
			pageTitle: OPTIONS_PAGE_TITLE,
		});
	});
});
