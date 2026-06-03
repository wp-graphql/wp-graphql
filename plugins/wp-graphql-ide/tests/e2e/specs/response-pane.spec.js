import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	runQuery,
	waitForGraphQLResponse,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

async function runQueryAndAwait(page) {
	await runQuery(page);
	await waitForGraphQLResponse(page);
}

test.describe('Response pane', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('Errors tab carries the error-tone marker regardless of strip position', async ({
		page,
	}) => {
		// A query that returns a GraphQL-level error (selecting a
		// non-existent field) lights up the Errors tab. The red marker
		// must follow the tab by identity, not by position — the test
		// here is the reverse of that: even without reordering, the
		// `[data-tab-name="ext:errors"]` attribute must be present so
		// the identity-based CSS rule has something to target.
		await page.click(selectors.addTab);
		await typeQuery(page, '{ thisFieldDoesNotExist }');
		await runQueryAndAwait(page);

		await expect(page.locator(selectors.responseStatus)).toBeVisible({
			timeout: 10000,
		});

		// Errors tab must exist in the DOM with its data-tab-name marker.
		// This is the contract the red-error CSS targets — losing the
		// attribute breaks the visual indicator silently.
		await expect(page.locator('[data-tab-name="ext:errors"]')).toHaveCount(
			1
		);
	});

	test('every bottom-strip tab carries a stable data-tab-name attribute', async ({
		page,
	}) => {
		// Pin the contract that the OverflowTabs render adds
		// `data-tab-name` to every tab button. CSS rules (red Errors
		// indicator, future per-tab styling) depend on this attribute.
		// Without it, position-based selectors creep back in and
		// reorder breaks the visual cues.
		await page.click(selectors.addTab);
		await typeQuery(page, '{ __typename }');
		await runQueryAndAwait(page);
		await expect(page.locator(selectors.responseStatus)).toBeVisible({
			timeout: 10000,
		});

		const tabsWithName = page.locator(
			'.components-tab-panel__tabs-item[data-tab-name]'
		);
		const total = page.locator('.components-tab-panel__tabs-item');
		const tabsWithNameCount = await tabsWithName.count();
		const totalCount = await total.count();
		expect(tabsWithNameCount).toBeGreaterThan(0);
		expect(tabsWithNameCount).toBe(totalCount);
	});
});
