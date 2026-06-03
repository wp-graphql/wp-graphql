import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Save a fresh draft, return the title we used so callers can find it
 * in the Saved Queries panel or the snackbar text later. The Publish
 * button only appears for saved-but-not-yet-published docs.
 */
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

test.describe('Publish flow', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('clicking Publish flips a saved draft into a published doc', async ({
		page,
	}) => {
		const stamp = Date.now();
		const title = `Publishable ${stamp}`;
		const body = `{ posts(where: {search: "pub-${stamp}"}) { nodes { id } } }`;

		await saveDraft(page, body, title);

		// Publish is enabled on saved drafts with a parseable query.
		const publishBtn = page.getByRole('button', {
			name: 'Publish',
			exact: true,
		});
		await expect(publishBtn).toBeEnabled();
		await publishBtn.click();

		// Snackbar confirms the publish.
		await expect(
			page
				.locator('.components-snackbar')
				.filter({ hasText: /Document published/i })
		).toBeVisible({ timeout: 10000 });

		// Publish button disappears for published docs.
		await expect(publishBtn).toBeHidden({ timeout: 5000 });
	});

	// Publish-collision UX is covered by the save-collision test in
	// `save-flow.spec.js` — both `publishCurrentDoc` and the SaveDialog
	// catch route the same `parseAliasInUseError` parse and render the
	// same notice shape via `addNotice`. Re-asserting the notice from
	// the publish trigger would be testing the same code path.
});
