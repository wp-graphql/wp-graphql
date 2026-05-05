import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Save flow', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('clicking Save draft on a brand-new doc opens the SaveDialog', async ({
		page,
	}) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		// Save draft button lives in the editor toolbar; enabled
		// once the doc is dirty (which it is after typing).
		await page.getByRole('button', { name: 'Save draft' }).click();

		await expect(
			page.locator('.wpgraphql-ide-save-dialog')
		).toBeVisible({ timeout: 5000 });
		await expect(page.getByLabel('Document name')).toBeVisible();
	});

	test('SaveDialog closes on Escape without saving', async ({ page }) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');
		await page.getByRole('button', { name: 'Save draft' }).click();
		await expect(page.locator('.wpgraphql-ide-save-dialog')).toBeVisible();

		await page.keyboard.press('Escape');
		await expect(page.locator('.wpgraphql-ide-save-dialog')).toBeHidden();

		// Tab should still be the temp tab — not promoted to a saved doc.
		await expect(
			page
				.locator(`${selectors.tab}.is-active`)
				.locator('.wpgraphql-ide-tab-dirty')
		).toBeVisible();
	});

	test('Save draft button is disabled when the doc has no unsaved changes', async ({
		page,
	}) => {
		// Right after `ensureDocumentOpen` the doc is fresh (and empty).
		// `is-dirty` is true while there's a temp id with no content
		// per the existing UI, so dirty == "user can save". Verify the
		// button reflects the underlying contract: typing dirties it,
		// clearing should leave it dirty until saved or closed.
		await page.click(selectors.addTab);
		const saveBtn = page.getByRole('button', { name: 'Save draft' });
		// Empty doc — `activeDocDirty` is false even though it's a temp
		// tab (temp dirty is purely visual). Save button must be disabled.
		await expect(saveBtn).toBeDisabled();
	});
});
