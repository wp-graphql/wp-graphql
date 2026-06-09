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

	test('submitting the SaveDialog promotes the temp tab into a saved draft', async ({
		page,
	}) => {
		// Use a query body unique to this test so Smart Cache's
		// content-addressed alias doesn't collide with whatever else
		// is in storage from prior runs.
		const stamp = Date.now();
		const title = `Saved fixture ${stamp}`;
		await page.click(selectors.addTab);
		await typeQuery(
			page,
			`{ posts(where: {search: "save-${stamp}"}) { nodes { id title } } }`
		);
		await page.getByRole('button', { name: 'Save draft' }).click();

		const dialog = page.locator('.wpgraphql-ide-save-dialog');
		await expect(dialog).toBeVisible();
		await dialog.getByLabel('Document name').fill(title);
		await dialog.getByRole('button', { name: 'Save', exact: true }).click();

		// Dialog closes once the create mutation resolves.
		await expect(dialog).toBeHidden({ timeout: 10000 });

		// Active tab is no longer temp.
		await expect(
			page.locator(`${selectors.tab}.is-active`)
		).not.toHaveClass(/is-temp/);

		// New doc shows up in the Saved Queries panel.
		await page
			.locator(selectors.activityBar)
			.getByRole('button', { name: 'Saved Queries', exact: true })
			.click();
		await expect(
			page.getByRole('button', { name: title }).first()
		).toBeVisible({ timeout: 5000 });
	});

	test('saving a duplicate-content draft surfaces the friendly "Open existing" notice', async ({
		page,
	}) => {
		// Save the same content twice. Smart Cache rejects the second
		// save with `This query has already been associated with…`;
		// the IDE's SaveDialog catch path converts that into a snackbar
		// with an "Open existing" action.
		const stamp = Date.now();
		const body = `{ posts(where: {search: "dup-${stamp}"}) { nodes { id } } }`;

		const saveAs = async (name) => {
			// Wait for any pending snackbar to fade — sibling tests in
			// this describe leave a "Document saved" toast over the
			// editor toolbar that intercepts pointer events on the `+`
			// button. The snackbar auto-dismisses; waiting it out is
			// more honest than forcing the click through.
			await page
				.locator('.components-snackbar')
				.first()
				.waitFor({ state: 'hidden', timeout: 8000 })
				.catch(() => {});
			await page.locator(selectors.addTab).click();
			await page.waitForSelector(selectors.graphqlEditorContent, {
				state: 'visible',
				timeout: 5000,
			});
			await typeQuery(page, body);
			await page.getByRole('button', { name: 'Save draft' }).click();
			const dialog = page.locator('.wpgraphql-ide-save-dialog');
			await expect(dialog).toBeVisible({ timeout: 10000 });
			await dialog.getByLabel('Document name').fill(name);
			await dialog
				.getByRole('button', { name: 'Save', exact: true })
				.click();
			return dialog;
		};

		const firstName = `First-${stamp}`;
		const secondName = `Second-${stamp}`;

		const firstDialog = await saveAs(firstName);
		await expect(firstDialog).toBeHidden({ timeout: 10000 });

		// Second save attempt with the same content body.
		const secondDialog = await saveAs(secondName);

		// Notice surfaces with the first doc's name, and an Open
		// existing action. The notice closes the dialog so the user
		// can act on the offer.
		await expect(secondDialog).toBeHidden({ timeout: 10000 });
		const notice = page
			.locator('.components-snackbar')
			.filter({ hasText: firstName });
		await expect(notice).toBeVisible({ timeout: 5000 });
		await expect(
			notice.getByRole('button', { name: 'Open existing' })
		).toBeVisible();
	});
});
