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

const openPanel = async (page, label) => {
	const btn = page
		.locator(selectors.activityBar)
		.getByRole('button', { name: label, exact: true });
	if ((await btn.getAttribute('aria-pressed')) !== 'true') {
		await btn.click();
	}
	await expect(btn).toHaveAttribute('aria-pressed', 'true');
};

async function runQueryAndAwait(page) {
	await runQuery(page);
	await waitForGraphQLResponse(page);
}

async function clearHistory(page) {
	await openPanel(page, 'History');
	const clearBtn = page
		.locator('.wpgraphql-ide-history-panel')
		.getByRole('button', { name: /Clear all/i });
	if (await clearBtn.isVisible().catch(() => false)) {
		await clearBtn.click();
		// Confirm in the dialog.
		await page
			.getByRole('dialog')
			.getByRole('button', { name: /Clear all/i })
			.click();
	}
}

test.describe('History panel', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
		await clearHistory(page);
	});

	test('running the same query multiple times collapses into one operation row', async ({
		page,
	}) => {
		// History dedups by `operationHash` — running `{ __typename }`
		// three times must surface as ONE row with "3 runs".
		await page.click(selectors.addTab);
		await typeQuery(page, '{ __typename }');
		await runQueryAndAwait(page);
		await runQueryAndAwait(page);
		await runQueryAndAwait(page);

		await openPanel(page, 'History');

		const rows = page.locator('.wpgraphql-ide-history-entry--operation');
		await expect(rows).toHaveCount(1);
		await expect(rows.first()).toContainText(/3 runs/);
	});

	test('clicking an operation row switches to an already-open tab matching the hash', async ({
		page,
	}) => {
		// switch-or-spawn: if a tab is already open whose live content
		// hashes to the same identity, the click switches focus instead
		// of opening a duplicate draft.
		const body = `{ posts(where: {search: "history-switch-${Date.now()}"}) { nodes { id } } }`;
		await page.click(selectors.addTab);
		await typeQuery(page, body);
		await runQueryAndAwait(page);

		// Open a second tab so the first isn't the active one. We need
		// the FIRST tab's identity to remain the click target.
		await page.click(selectors.addTab);
		await typeQuery(page, '{ __typename }');

		// Two open tabs, second is active. Now click the history row —
		// it should switch back to the first tab (whose live content
		// matches the row's hash).
		await openPanel(page, 'History');
		await page
			.locator('.wpgraphql-ide-history-entry--operation')
			.filter({ hasText: 'posts' })
			.first()
			.click();

		await expect(
			page.locator(`${selectors.tab}.is-active`)
		).toContainText(/posts/);
	});

	test('Request history response-pane tab is hidden for draft docs', async ({
		page,
	}) => {
		// The predicate gates the tab on `activeDocument?.status === 'publish'`.
		// On a draft tab, the tab must be absent from the response strip
		// regardless of whether the response surface has rendered. The
		// positive case (published doc → tab visible) is exercised at the
		// unit level — re-asserting it from the browser depends on the
		// per-doc response-binding that the temp → server-id promotion
		// reshuffles, and the resulting setup churn makes the e2e brittle
		// without adding signal the unit test doesn't already provide.
		const body = `{ posts(where: {search: "rh-${Date.now()}"}) { nodes { id } } }`;
		await page.click(selectors.addTab);
		await typeQuery(page, body);
		await runQueryAndAwait(page);
		await expect(
			page.locator(selectors.responseStatus)
		).toBeVisible({ timeout: 10000 });
		await expect(
			page.getByRole('tab', { name: /Request history/i })
		).toBeHidden();
	});
});
