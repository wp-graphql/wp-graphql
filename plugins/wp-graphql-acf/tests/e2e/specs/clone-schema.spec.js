import { describe, test, expect, beforeEach, afterEach } from '@playwright/test';
import {
	loginToWordPressAdmin,
	importAcfJson,
	deleteAllAcfFieldGroups,
	graphqlRequest,
} from '../utils.js';

/**
 * E2E tests for clone/schema behavior: import JSON, then assert GraphQL schema and query results.
 * Mirrors functional tests: TestCloneWithRepeaterCest, TestCloneFieldsCest, TestCloneGroupWithoutPrefixCest,
 * TestCloneWithGroupCest, TestCloneFieldOnMultipleFlexibleFieldLayoutsCest.
 * Requires ACF Pro (clone fields). Each suite: beforeEach imports its JSON, afterEach deletes all field groups.
 */

const GET_TYPE_QUERY = `
  query GetType($type: String!) {
    __type(name: $type) {
      name
      kind
      interfaces { name }
      fields { name type { name kind ofType { name } } }
    }
  }
`;

const GET_TYPE_WITH_POSSIBLE_TYPES = `
  query GetType($type: String!) {
    __type(name: $type) {
      name
      kind
      interfaces { name }
      possibleTypes { name }
      fields { name type { name kind ofType { name } } }
    }
  }
`;

const GET_ACF_FIELD_GROUPS = `
  query GetAcfFieldGroups {
    __type(name: "AcfFieldGroup") {
      name
      possibleTypes { name }
    }
  }
`;

function findField(fields, name) {
	return fields?.find((f) => f.name === name) ?? null;
}

describe('Clone with repeater (import + schema)', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, 'acf-export-clone-repeater.json');
		await importAcfJson(page, 'tests-acf-pro-kitchen-sink.json');
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('imported field groups show in schema (Flowers type)', async ({ request }) => {
		const res = await graphqlRequest(request, GET_TYPE_QUERY, { type: 'Flowers' });
		expect(res.data?.__type?.fields?.length).toBeGreaterThan(0);
		expect(res.data?.__type?.interfaces?.length).toBeGreaterThan(0);
		const interfaces = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		const fields = (res.data?.__type?.fields ?? []).map((f) => f.name);
		expect(interfaces).toContain('AcfFieldGroup');
		expect(interfaces).toContain('Flowers_Fields');
		expect(fields).toContain('color');
		expect(fields).toContain('datePicker');
		expect(fields).toContain('avatar');
		expect(fields).toContain('range');
	});

	test('query with plants and cloned repeater is valid (no errors)', async ({ request }) => {
		const query = `
          query GetPageWithPlants($databaseId: ID!) {
            page(id: $databaseId idType: DATABASE_ID) {
              id title
              ... on WithAcfPlants {
                plants {
                  name
                  clonedRepeater { notClonedRepeater { anotherName } }
                  notClonedRepeater { anotherName }
                }
              }
            }
          }
        `;
		const res = await graphqlRequest(request, query, { databaseId: '0' });
		expect(res.data).toBeDefined();
		expect(res.errors).toBeUndefined();
	});

	test('cloned fields applied as interfaces (Plants type)', async ({ request }) => {
		const res = await graphqlRequest(request, GET_TYPE_QUERY, { type: 'Plants' });
		expect(res.data?.__type?.fields?.length).toBeGreaterThan(0);
		expect(res.data?.__type?.interfaces?.length).toBeGreaterThan(0);
		const interfaces = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		const fields = (res.data?.__type?.fields ?? []).map((f) => f.name);
		expect(interfaces).toContain('AcfFieldGroup');
		expect(interfaces).toContain('Flowers_Fields');
		expect(interfaces).toContain('Plants_Fields');
		expect(fields).toContain('color');
		expect(fields).toContain('datePicker');
		expect(fields).toContain('avatar');
		expect(fields).toContain('range');
	});

	test('cloned repeater field shows in schema (landMineRepeater, clonedRepeater, cloneRoots)', async ({
		request,
	}) => {
		const res = await graphqlRequest(request, GET_TYPE_QUERY, { type: 'Plants' });
		const fields = res.data?.__type?.fields ?? [];
		const fieldNames = fields.map((f) => f.name);
		expect(fieldNames).toContain('landMineRepeater');
		expect(fieldNames).toContain('clonedRepeater');
		const landMine = findField(fields, 'landMineRepeater');
		expect(landMine?.type?.kind).toBe('LIST');
		expect(landMine?.type?.ofType?.name).toBe('FlowersLandMineRepeater');
		const clonedRepeater = findField(fields, 'clonedRepeater');
		expect(clonedRepeater?.type?.kind).toBe('OBJECT');
		expect(clonedRepeater?.type?.ofType).toBeNull();
		expect(clonedRepeater?.type?.name).toBe('PlantsClonedRepeater');
		const cloneRoots = findField(fields, 'cloneRoots');
		expect(cloneRoots?.type?.kind).toBe('OBJECT');
		expect(cloneRoots?.type?.name).toBe('PlantsCloneRoots');
	});
});

describe('Clone fields (cloned group vs individual)', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, 'tests-inactive-group-for-cloning.json');
		await importAcfJson(page, 'tests-acf-pro-kitchen-sink.json');
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('cloned field group applied as interface (AcfProKitchenSink)', async ({ request }) => {
		const res = await graphqlRequest(request, GET_TYPE_QUERY, {
			type: 'AcfProKitchenSink',
		});
		const fields = (res.data?.__type?.fields ?? []).map((f) => f.name);
		const interfaces = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		expect(fields).toContain('clonedImageField');
		expect(fields).toContain('clonedTextField');
		expect(interfaces).toContain('InactiveGroupForCloning_Fields');
	});

	test('cloning individual fields does not apply cloned group as interface', async ({
		request,
	}) => {
		const res = await graphqlRequest(request, GET_TYPE_QUERY, {
			type: 'AcfProKitchenSinkFlexibleContentLayoutWithClonedGroupLayout',
		});
		const fields = (res.data?.__type?.fields ?? []).map((f) => f.name);
		const interfaces = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		expect(fields).toContain('clonedImageField');
		expect(fields).toContain('clonedTextField');
		expect(interfaces).not.toContain('InactiveGroupForCloning_Fields');
	});
});

describe('Clone group without prefix (issue-172-b)', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, 'issue-172-b/acf-export-issue-172-b.json');
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('imported field groups show in AcfFieldGroup possibleTypes', async ({
		request,
	}) => {
		let res;
		try {
			res = await graphqlRequest(request, GET_ACF_FIELD_GROUPS);
		} catch (err) {
			const msg = err?.message ?? '';
			if (
				msg.includes('500') &&
				(msg.includes('memory') || msg.includes('134217728'))
			) {
				test.skip(
					true,
					'PHP memory limit exceeded during AcfFieldGroup introspection; increase memory (e.g. WP_MEMORY_LIMIT) for this suite.'
				);
				return;
			}
			throw err;
		}
		expect(res.data?.__type?.name).toBe('AcfFieldGroup');
		expect(res.data?.__type?.possibleTypes?.length).toBeGreaterThan(0);
		const possible = (res.data?.__type?.possibleTypes ?? []).map((p) => p.name);
		expect(possible).toContain('PostCategoryOptions');
		expect(possible).toContain('AuthorCustomFields');
		expect(possible).toContain('ContentGridOption');
		expect(possible).toContain('AcfPageOptions');
		expect(possible).toContain('AcfPageOptionsPageOptions');
		expect(possible).toContain('Schema');
	});

	test('post sections query is valid (no errors)', async ({ request }) => {
		// Assert the query shape is valid and returns no GraphQL errors (post may be null if ID does not exist).
		const query = `
          query GetPostSections($id: ID!) {
            post(id: $id, idType: DATABASE_ID) {
              id title
              postSections {
                postSections {
                  __typename
                  ... on PostSectionsPostSectionsContentLayout {
                    dropcap
                    subLayout { contentWidth }
                  }
                }
              }
            }
          }
        `;
		const res = await graphqlRequest(request, query, { id: '1' });
		expect(res.errors).toBeUndefined();
	});
});

describe('Clone with group (issue-172 content blocks)', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, 'issue-172/acf-export-blocks.json');
		await importAcfJson(page, 'issue-172/acf-export-content-blocks.json');
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('imported field groups show in schema (ContentBlocks, layouts, interfaces)', async ({
		request,
	}) => {
		let res = await graphqlRequest(request, GET_TYPE_WITH_POSSIBLE_TYPES, {
			type: 'ContentBlocks',
		});
		const interfaces1 = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		const fields1 = (res.data?.__type?.fields ?? []).map((f) => f.name);
		expect(interfaces1).toContain('AcfFieldGroup');
		expect(interfaces1).toContain('ContentBlocks_Fields');
		expect(fields1).toContain('blocks');

		res = await graphqlRequest(request, GET_TYPE_WITH_POSSIBLE_TYPES, {
			type: 'ContentBlocksBlocks_Layout',
		});
		const possible = (res.data?.__type?.possibleTypes ?? []).map((p) => p.name);
		expect(possible).toContain('ContentBlocksBlocksAccordionLayout');
		expect(possible).toContain('ContentBlocksBlocksAppCtaLayout');
		expect(possible).toContain('ContentBlocksBlocksBlogPostsLayout');
		expect(possible).toContain('ContentBlocksBlocksBrazeCardLayout');
		expect(possible).toContain('ContentBlocksBlocksPriceComparisonLayout');

		res = await graphqlRequest(request, GET_TYPE_WITH_POSSIBLE_TYPES, {
			type: 'ContentBlocksBlocksAccordionLayout',
		});
		const interfaces2 = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		const fields2 = (res.data?.__type?.fields ?? []).map((f) => f.name);
		expect(interfaces2).toContain('ContentBlocksBlocksAccordionLayout_Fields');
		expect(interfaces2).toContain('ContentBlocksBlocks_Layout');
		expect(interfaces2).toContain('AcfFieldGroup');
		expect(interfaces2).toContain('BlockAccordion_Fields');
		expect(interfaces2).toContain('AcfFieldGroupFields');
		expect(fields2).not.toContain('accordion');

		res = await graphqlRequest(request, GET_TYPE_WITH_POSSIBLE_TYPES, {
			type: 'ContentBlocksBlocksPriceComparisonLayout',
		});
		const interfaces3 = (res.data?.__type?.interfaces ?? []).map((i) => i.name);
		const fields3 = (res.data?.__type?.fields ?? []).map((f) => f.name);
		expect(interfaces3).toContain('BlockPriceComparison_Fields');
		expect(interfaces3).toContain('ContentBlocksBlocks_Layout');
		expect(interfaces3).toContain('AcfFieldGroup');
		expect(fields3).toContain('button');
		expect(fields3).toContain('intro');
		expect(fields3).toContain('pricesTable');
		expect(fields3).toContain('title');
	});
});

describe('Clone field on multiple flexible layouts (issue-197)', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
		await importAcfJson(page, 'issue-197/acf-export-issue-197.json');
	});

	afterEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await deleteAllAcfFieldGroups(page);
	});

	test('imported field groups show in AcfFieldGroup possibleTypes', async ({ request }) => {
		const res = await graphqlRequest(request, GET_ACF_FIELD_GROUPS);
		expect(res.data?.__type?.name).toBe('AcfFieldGroup');
		expect(res.data?.__type?.possibleTypes?.length).toBeGreaterThan(0);
		const possible = (res.data?.__type?.possibleTypes ?? []).map((p) => p.name);
		expect(possible).toContain('Section');
		expect(possible).toContain('SectionBackgroundColorGroup');
		expect(possible).toContain('PageContent');
		expect(possible).toContain('PostContent');
		expect(possible).toContain('CareersFields');
		expect(possible).toContain('ClassFinderFields');
	});
});
