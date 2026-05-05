import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const PANELS = [
	{ name: 'saved-queries', label: 'Saved Queries' },
	{ name: 'docs-explorer', label: 'Docs Explorer' },
	{ name: 'history', label: 'History' },
];

test.describe('Activity bar panels', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
	});

	test('activity bar renders all default panels', async ({ page }) => {
		await expect(page.locator(selectors.activityBar)).toBeVisible();

		for (const panel of PANELS) {
			await expect(
				page.getByRole('button', { name: panel.label })
			).toBeVisible();
		}
	});

	for (const panel of PANELS) {
		test(`clicking the ${panel.label} button opens the panel`, async ({
			page,
		}) => {
			const button = page.getByRole('button', { name: panel.label });
			await button.click();

			// Each panel renders its own content; check aria-pressed flips.
			await expect(button).toHaveAttribute('aria-pressed', 'true');
		});
	}

	test('clicking the same panel button twice toggles it closed', async ({
		page,
	}) => {
		const button = page.getByRole('button', { name: 'Saved Queries' });
		await button.click();
		await expect(button).toHaveAttribute('aria-pressed', 'true');

		await button.click();
		await expect(button).toHaveAttribute('aria-pressed', 'false');
	});
});
