import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	resetIdeClientState,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const PANELS = [
	{ name: 'saved-queries', label: 'Saved Queries' },
	{ name: 'docs-explorer', label: 'Docs' },
	{ name: 'history', label: 'History' },
];

// Activity-bar buttons share their label text with kebab/close buttons
// inside the panel header. Restrict to the activity bar specifically.
const activityBarButton = (page, label) =>
	page
		.locator(selectors.activityBar)
		.getByRole('button', { name: label, exact: true });

test.describe('Activity bar panels', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await resetIdeClientState(page);
		await ensureDocumentOpen(page);
	});

	test('activity bar renders all default panels', async ({ page }) => {
		await expect(page.locator(selectors.activityBar)).toBeVisible();

		for (const panel of PANELS) {
			await expect(activityBarButton(page, panel.label)).toBeVisible();
		}
	});

	for (const panel of PANELS) {
		test(`clicking the ${panel.label} button activates the panel`, async ({
			page,
		}) => {
			const btn = activityBarButton(page, panel.label);
			const initiallyPressed =
				(await btn.getAttribute('aria-pressed')) === 'true';
			// Drive into a known closed state first so the click is the
			// thing that flips the panel open.
			if (initiallyPressed) {
				await btn.click();
				await expect(btn).toHaveAttribute('aria-pressed', 'false');
			}
			await btn.click();
			await expect(btn).toHaveAttribute('aria-pressed', 'true');
		});
	}

	test('clicking the same panel button twice toggles it closed', async ({
		page,
	}) => {
		const btn = activityBarButton(page, 'Saved Queries');

		// Force closed.
		if ((await btn.getAttribute('aria-pressed')) === 'true') {
			await btn.click();
		}
		await expect(btn).toHaveAttribute('aria-pressed', 'false');

		await btn.click();
		await expect(btn).toHaveAttribute('aria-pressed', 'true');

		await btn.click();
		await expect(btn).toHaveAttribute('aria-pressed', 'false');
	});
});
