import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

// Activity-bar buttons share their label text with kebab/close buttons
// inside the panel header. Restrict to the activity bar specifically.
const activityBarButton = (page, label) =>
	page
		.locator(selectors.activityBar)
		.getByRole('button', { name: label, exact: true });

// Type rows carry a composed accessible name so the kind reaches screen
// readers too ("Page, Object"). Match the type name wherever it sits in that
// name, without matching a longer type that merely contains it.
const typeRow = (name) => new RegExp(`(^|: )${name}(,|$)`);

const docsPanel = (page) => page.locator('.wpgraphql-ide-docs-panel').first();

const docsSection = (page, title) =>
	docsPanel(page)
		.locator('.wpgraphql-ide-docs-section')
		.filter({
			has: page.locator('.wpgraphql-ide-docs-section-title', {
				hasText: title,
			}),
		});

/**
 * Open the Docs panel and wait for the schema-backed search input —
 * its presence means introspection has completed and the panel is
 * browsable.
 * @param page
 */
async function openDocsPanel(page) {
	const btn = activityBarButton(page, 'Docs');
	if ((await btn.getAttribute('aria-pressed')) !== 'true') {
		await btn.click();
	}
	await expect(page.locator('.wpgraphql-ide-docs-search')).toBeVisible({
		timeout: 15000,
	});
}

/**
 * Navigate the Docs panel to a type's detail view via search.
 * @param page
 * @param typeName
 */
async function openType(page, typeName) {
	const searchInput = page.locator('.wpgraphql-ide-docs-search');
	await searchInput.fill(typeName);
	await docsPanel(page)
		.locator('.wpgraphql-ide-docs-search-group')
		.filter({ hasText: 'Types' })
		.getByRole('button', { name: typeRow(typeName) })
		.click();
	await expect(
		docsPanel(page).locator('.wpgraphql-ide-docs-type-name')
	).toHaveText(typeName);
}

test.describe('Docs explorer interface relationships', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
		await openDocsPanel(page);
	});

	test('object type shows the interfaces it implements', async ({ page }) => {
		// `DefaultTemplate` (from #4028) is also a unique search match —
		// broader names like `Page` fall outside the capped result list.
		await openType(page, 'DefaultTemplate');

		const implementsSection = docsSection(page, 'Implements');
		await expect(implementsSection).toBeVisible();
		await expect(
			implementsSection.getByRole('button', {
				name: typeRow('ContentTemplate'),
			})
		).toBeVisible();
	});

	test('interface type shows its implementations and navigates between them', async ({
		page,
	}) => {
		await openType(page, 'ContentNode');

		// ContentNode itself implements Node.
		await expect(
			docsSection(page, 'Implements').getByRole('button', {
				name: 'Node',
				exact: true,
			})
		).toBeVisible();

		// And built-in content types implement ContentNode.
		const implementations = docsSection(page, 'Implementations');
		await expect(implementations).toBeVisible();
		await expect(
			implementations.getByRole('button', { name: typeRow('Page') })
		).toBeVisible();
		await expect(
			implementations.getByRole('button', { name: typeRow('Post') })
		).toBeVisible();

		// Clicking an implementation navigates to that type's view…
		await implementations
			.getByRole('button', { name: typeRow('Page') })
			.click();
		await expect(
			docsPanel(page).locator('.wpgraphql-ide-docs-type-name')
		).toHaveText('Page');

		// …and its Implements link navigates back to the interface.
		await docsSection(page, 'Implements')
			.getByRole('button', { name: typeRow('ContentNode') })
			.click();
		await expect(
			docsPanel(page).locator('.wpgraphql-ide-docs-type-name')
		).toHaveText('ContentNode');
	});

	test('types without interface relationships show neither section', async ({
		page,
	}) => {
		await openType(page, 'RootQuery');

		await expect(docsSection(page, 'Implements')).toHaveCount(0);
		await expect(docsSection(page, 'Implementations')).toHaveCount(0);
	});

	test('sections collapse and expand', async ({ page }) => {
		await openType(page, 'ContentNode');

		const implementations = docsSection(page, 'Implementations');
		const toggle = implementations.locator(
			'.wpgraphql-ide-docs-section-title'
		);
		const pageLink = implementations.getByRole('button', {
			name: typeRow('Page'),
		});

		await expect(toggle).toHaveAttribute('aria-expanded', 'true');
		await expect(pageLink).toBeVisible();

		await toggle.click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		await expect(pageLink).toHaveCount(0);

		await toggle.click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'true');
		await expect(pageLink).toBeVisible();

		// Fields collapses too.
		const fieldsToggle = docsSection(page, 'Fields').locator(
			'.wpgraphql-ide-docs-section-title'
		);
		await fieldsToggle.click();
		await expect(fieldsToggle).toHaveAttribute('aria-expanded', 'false');
	});

	test('section headings stay pinned below the type header while scrolling', async ({
		page,
	}) => {
		// ContentNode has a long field list — plenty of scroll room.
		await openType(page, 'ContentNode');

		// Scroll deep into the field list (`toBeVisible()` can't catch an
		// un-pinned sticky element — it ignores scroll containers — so we
		// assert on bounding boxes relative to the scrollport instead).
		const body = page.locator('.wpgraphql-ide-docs-body');
		await body.evaluate((el) => {
			el.scrollTop = el.scrollHeight * 0.6;
		});

		const bodyBox = await body.boundingBox();

		// The slim type header is pinned at the top of the scrollport…
		const headerBox = await page
			.locator('.wpgraphql-ide-docs-type-header')
			.boundingBox();
		expect(headerBox.y).toBeGreaterThanOrEqual(bodyBox.y - 20);
		expect(headerBox.y - bodyBox.y).toBeLessThan(20);

		// …and the Fields heading is pinned directly below it, not 60% of
		// the list off-screen.
		const titleBox = await docsSection(page, 'Fields')
			.locator('.wpgraphql-ide-docs-section-title')
			.boundingBox();
		expect(titleBox.y).toBeGreaterThanOrEqual(headerBox.y);
		expect(titleBox.y - bodyBox.y).toBeLessThan(120);
	});

	// Hover feedback has to match what actually responds to a click: every
	// row that lights up must navigate, and every row that doesn't navigate
	// must not light up.
	test('the whole type entry row is clickable, not just the type name', async ({
		page,
	}) => {
		await openType(page, 'ContentNode');

		const row = docsSection(page, 'Implementations').getByRole('button', {
			name: typeRow('Page'),
		});

		// The button has to BE the row rather than a smaller element inside
		// it. The row is what takes the hover highlight, so the row is what
		// must respond to a click.
		await expect(row).toHaveClass(/wpgraphql-ide-docs-type-entry/);

		// Click the far right of the row — empty space well past the type
		// name, which used to sit outside the click target even though the
		// hover highlight covered it. Positional click (not page.mouse) so
		// Playwright scrolls the row into view first.
		const box = await row.boundingBox();
		await row.click({ position: { x: box.width - 8, y: box.height / 2 } });

		await expect(
			docsPanel(page).locator('.wpgraphql-ide-docs-type-name')
		).toHaveText('Page');
	});

	// The panel is narrow and scrolls vertically. Anything that overflows it
	// horizontally (a full-width row whose padding isn't inside its width,
	// say) puts a sideways scrollbar under a long field list.
	test('the panel does not scroll horizontally', async ({ page }) => {
		const body = page.locator('.wpgraphql-ide-docs-body');

		// Root view, then a type with long rows and every section on show.
		for (const open of [null, 'ContentNode']) {
			if (open) {
				await openType(page, open);
			}
			const overflow = await body.evaluate(
				(el) => el.scrollWidth - el.clientWidth
			);
			expect(overflow).toBeLessThanOrEqual(0);
		}
	});

	test('field rows do not take a hover highlight', async ({ page }) => {
		await openType(page, 'ContentNode');

		const field = docsPanel(page)
			.locator('.wpgraphql-ide-docs-field')
			.first();
		const background = () =>
			field.evaluate((el) => window.getComputedStyle(el).backgroundColor);

		const before = await background();
		await field.hover();
		expect(await background()).toBe(before);
	});
});
