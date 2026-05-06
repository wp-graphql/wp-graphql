import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Document Settings (left-panel toggle)', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
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

	test('Settings panel shows empty state for an unsaved doc', async ({
		page,
	}) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		await page.locator('.wpgraphql-ide-toolbar-doc-settings-btn').click();
		await expect(
			page.getByText('Save the document to edit its settings.')
		).toBeVisible();
	});

	test('settings persist on a saved document', async ({ page }) => {
		await page.click(selectors.addTab);
		await typeQuery(page, '{ posts { nodes { id } } }');

		const modKey = process.platform === 'darwin' ? 'Meta' : 'Control';
		await page.keyboard.press(`${modKey}+s`);

		const dialog = page.locator('.wpgraphql-ide-save-dialog');
		await expect(dialog).toBeVisible({ timeout: 5000 });
		await dialog.getByLabel('Document name').fill('SettingsSpecDoc');
		await dialog.getByRole('button', { name: /^save/i }).first().click();
		await expect(dialog).toBeHidden({ timeout: 5000 });

		await page.locator('.wpgraphql-ide-toolbar-doc-settings-btn').click();

		await page.getByLabel('Description').fill('Used by the homepage feed');

		const aliasInput = page.getByLabel('Alias Names');
		await aliasInput.fill('home-feed');
		await aliasInput.press('Enter');
		await expect(page.getByText('home-feed')).toBeVisible();

		await page.waitForTimeout(2500);
		await page.reload();
		await page.waitForSelector(selectors.ideRoot, { state: 'visible' });

		// Settings panel state persists across reloads.
		await expect(page.getByLabel('Description')).toHaveValue(
			'Used by the homepage feed'
		);
		await expect(page.getByText('home-feed')).toBeVisible();
	});
});
