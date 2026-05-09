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

		await expect(page.locator('.wpgraphql-ide-save-dialog')).toBeVisible({
			timeout: 5000,
		});
		await expect(page.getByLabel('Document name')).toBeVisible();
	});

	test('SaveDialog closes on Escape without saving', async ({ page }) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');
		await page.getByRole('button', { name: 'Save draft' }).click();
		await expect(page.locator('.wpgraphql-ide-save-dialog')).toBeVisible();

		await page.keyboard.press('Escape');
		await expect(page.locator('.wpgraphql-ide-save-dialog')).toBeHidden();

		// Tab should still be the temp tab — not promoted to a saved
		// doc. Temp tabs are marked with `is-temp` (italic title);
		// promotion to a real draft removes that class.
		await expect(page.locator(`${selectors.tab}.is-active`)).toHaveClass(
			/is-temp/
		);
	});

	test('Save draft button stays enabled on a brand-new temp tab', async ({
		page,
	}) => {
		// Temp drafts are autopersisted, so `activeDocDirty` is false
		// even right after `addTab`. The Save button must stay enabled
		// anyway — clicking it is how the user promotes a temp into a
		// real draft via the SaveDialog.
		await page.click(selectors.addTab);
		await expect(
			page.getByRole('button', { name: 'Save draft' })
		).toBeEnabled();
	});
});
