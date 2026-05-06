import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Document Settings (left-panel toggle)', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		// Empty-state click-through — without it `.wpgraphql-ide-tab-add`
		// isn't on the page since the IDE shows "No open documents" until
		// a tab exists.
		await ensureDocumentOpen(page);
	});

	test('Composer and Settings toggles share the editor toolbar header', async ({
		page,
	}) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		await expect(
			page.locator('.wpgraphql-ide-toolbar-composer-btn')
		).toBeVisible();
		await expect(
			page.locator('.wpgraphql-ide-toolbar-doc-settings-btn')
		).toBeVisible();
	});

	test('Settings panel is mutually exclusive with the Query Composer', async ({
		page,
	}) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		// Open the composer.
		await page.locator('.wpgraphql-ide-toolbar-composer-btn').click();
		await expect(
			page.locator('.wpgraphql-ide-query-composer-inline')
		).toBeVisible();

		// Opening Settings closes the composer (mutually exclusive).
		await page.locator('.wpgraphql-ide-toolbar-doc-settings-btn').click();
		await expect(
			page.locator('.wpgraphql-ide-doc-settings-inline')
		).toBeVisible();
		await expect(
			page.locator('.wpgraphql-ide-query-composer-inline')
		).toBeHidden();

		// And vice-versa: Composer reclaims the slot.
		await page.locator('.wpgraphql-ide-toolbar-composer-btn').click();
		await expect(
			page.locator('.wpgraphql-ide-query-composer-inline')
		).toBeVisible();
		await expect(
			page.locator('.wpgraphql-ide-doc-settings-inline')
		).toBeHidden();
	});

	test('Settings panel is editable on an unsaved doc (in-memory + localStorage)', async ({
		page,
	}) => {
		// The drawer documents that "Temp/unsaved documents are fine to
		// edit too: values live in memory + localStorage and ride along
		// when the doc is first saved." Verify that contract: clicking
		// the toggle on a temp tab renders editable fields, not a gated
		// "save first" message.
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		await page.locator('.wpgraphql-ide-toolbar-doc-settings-btn').click();

		await expect(
			page.locator('.wpgraphql-ide-doc-settings-inline')
		).toBeVisible();
		await expect(page.getByLabel('Description')).toBeEditable();
	});

	test('settings persist on a saved document', async ({ page }) => {
		// Unique per run — aliases and saved-doc names are unique-constrained
		// in the DB, so reusing literals would fail when the test re-runs
		// against an environment that wasn't reset between runs.
		const suffix = Date.now().toString(36);
		const docName = `SettingsSpecDoc-${suffix}`;
		const alias = `home-feed-${suffix}`;

		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		// Save flow goes through the toolbar's "Save draft" button —
		// Cmd+S was deliberately removed earlier in the rebuild.
		await page.getByRole('button', { name: 'Save draft' }).click();

		const dialog = page.locator('.wpgraphql-ide-save-dialog');
		await expect(dialog).toBeVisible({ timeout: 5000 });
		await dialog.getByLabel('Document name').fill(docName);
		await dialog.getByRole('button', { name: /^save/i }).first().click();
		await expect(dialog).toBeHidden({ timeout: 5000 });

		await page.locator('.wpgraphql-ide-toolbar-doc-settings-btn').click();

		await page.getByLabel('Description').fill('Used by the homepage feed');

		const aliasInput = page.getByLabel('Alias Names');
		await aliasInput.fill(alias);
		await aliasInput.press('Enter');
		await expect(page.getByText(alias)).toBeVisible();

		await page.waitForTimeout(2500);
		await page.reload();
		await page.waitForSelector(selectors.ideRoot, { state: 'visible' });

		// Settings panel state persists across reloads.
		await expect(page.getByLabel('Description')).toHaveValue(
			'Used by the homepage feed'
		);
		await expect(page.getByText(alias)).toBeVisible();
	});
});
