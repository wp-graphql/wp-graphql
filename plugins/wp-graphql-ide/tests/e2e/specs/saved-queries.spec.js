import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const openSavedQueriesPanel = async (page) => {
	const btn = page
		.locator(selectors.activityBar)
		.getByRole('button', { name: 'Saved Queries', exact: true });
	if ((await btn.getAttribute('aria-pressed')) !== 'true') {
		await btn.click();
	}
	await expect(btn).toHaveAttribute('aria-pressed', 'true');
};

async function saveDraft(page, body, title) {
	await page.click(selectors.addTab);
	await typeQuery(page, body);
	await page.getByRole('button', { name: 'Save draft' }).click();
	const dialog = page.locator('.wpgraphql-ide-save-dialog');
	await expect(dialog).toBeVisible();
	await dialog.getByLabel('Document name').fill(title);
	await dialog.getByRole('button', { name: 'Save', exact: true }).click();
	await expect(dialog).toBeHidden({ timeout: 10000 });
}

test.describe('Saved Queries panel', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('clicking a saved doc opens it in a tab', async ({ page }) => {
		// Save a doc, close the tab, then open it back from the panel.
		const stamp = Date.now();
		const title = `Reopen me ${stamp}`;
		const body = `{ posts(where: {search: "open-${stamp}"}) { nodes { id } } }`;

		await saveDraft(page, body, title);

		// Close the active tab via the `×` affordance. Close button is
		// a `<span>` with `aria-label="Close <title>"`.
		await page.getByLabel(`Close ${title}`).click();

		// Open Saved Queries panel and click the row.
		await openSavedQueriesPanel(page);
		await page.getByRole('button', { name: title }).first().click();

		// Active tab title now contains the doc title.
		await expect(page.locator(`${selectors.tab}.is-active`)).toContainText(
			title
		);
	});

	test('importing a JSON file lands the documents in the panel', async ({
		page,
	}) => {
		const stamp = Date.now();
		const collectionName = `ImportColl ${stamp}`;
		const docTitle = `ImportedDoc ${stamp}`;
		const payload = JSON.stringify({
			version: 1,
			collections: [
				{
					name: collectionName,
					documents: [
						{
							title: docTitle,
							query: `{ posts(where: {search: "import-${stamp}"}) { nodes { id } } }`,
						},
					],
				},
			],
		});

		await openSavedQueriesPanel(page);

		// The file input is hidden but `setInputFiles` works on it
		// directly — bypassing the kebab → menu-item → ref-click chain
		// keeps the test focused on the upload + handler behavior.
		await page.locator('input[type="file"][accept*="json"]').setInputFiles({
			name: `import-${stamp}.json`,
			mimeType: 'application/json',
			buffer: Buffer.from(payload),
		});

		// Success toast surfaces with "Imported N quer{y,ies}".
		await expect(
			page
				.locator('.components-snackbar')
				.filter({ hasText: /Imported \d+ quer/i })
		).toBeVisible({ timeout: 10000 });

		// New doc shows in the panel.
		await expect(
			page.getByRole('button', { name: docTitle }).first()
		).toBeVisible({ timeout: 5000 });
	});

	test('opening the Export option surfaces the ExportDialog', async ({
		page,
	}) => {
		// We need at least one saved doc for Export to be enabled.
		const stamp = Date.now();
		await saveDraft(
			page,
			`{ posts(where: {search: "export-${stamp}"}) { nodes { id } } }`,
			`ExportTrigger ${stamp}`
		);

		await openSavedQueriesPanel(page);

		// Open the panel kebab and pick Export queries.
		await page.locator('.wpgraphql-ide-panel-kebab').first().click();
		await page.getByRole('menuitem', { name: /Export queries/i }).click();

		// The dialog mounts. We don't trigger the download itself —
		// asserting the dialog rendered is enough to catch wiring
		// drift between the kebab menu, the panel action hook, and
		// the dialog mount.
		await expect(
			page.locator('.wpgraphql-ide-dialog').filter({ hasText: /Export/i })
		).toBeVisible({ timeout: 5000 });
	});
});
