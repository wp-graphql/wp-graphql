import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	readQuery,
	runQuery,
	pressMod,
	openSettingsTab,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

// Single carve-out for every chord binding the IDE ships with. When a
// binding is added or removed, this suite is the place to record it.
// Render-mode coverage (dedicated/drawer/endpoint) lives elsewhere —
// keymaps are component-local to the CM6 editor, so exercising them in
// dedicated mode is sufficient.
test.describe('Keyboard shortcuts', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	// The Execute button stays disabled while the schema is still
	// fetching. The Mod-Enter keymap handler short-circuits in the same
	// `isSchemaLoading` window, so chord tests need the same wait that
	// Playwright's `.click()` actionability check gives the button tests.
	async function waitForSchemaReady(page) {
		await expect(
			page.getByRole('button', { name: 'Execute query' })
		).toBeEnabled({ timeout: 15000 });
	}

	test('Mod+Enter executes the query', async ({ page }) => {
		await typeQuery(page, '{ posts { nodes { id } } }');
		await waitForSchemaReady(page);
		await page.locator(selectors.graphqlEditorContent).first().click();
		await runQuery(page);

		// Response pane mounts a CodeMirror viewer with the JSON result
		// when execution completes. We assert on rendered content rather
		// than network events because the latter race with autosave +
		// introspection POSTs on the same endpoint.
		await expect(
			page.locator('.wpgraphql-ide-response-pane .cm-content').first()
		).toContainText('posts', { timeout: 15000 });
	});

	test('Mod+Enter runs the operation under the cursor in a multi-op doc', async ({
		page,
	}) => {
		const doc =
			'query OpOne { posts { nodes { id } } }\n' +
			'query OpTwo { pages { nodes { id } } }\n';
		await typeQuery(page, doc);
		await waitForSchemaReady(page);

		// Place the cursor inside OpTwo by clicking near its name.
		await page
			.locator(selectors.graphqlEditorContent)
			.first()
			.getByText('OpTwo')
			.click();
		await runQuery(page);

		// OpTwo queries `pages`, OpOne queries `posts`. If the keymap
		// routed to OpTwo (cursor-aware), the response carries `pages`
		// and never `posts`.
		const response = page
			.locator('.wpgraphql-ide-response-pane .cm-content')
			.first();
		await expect(response).toContainText('pages', { timeout: 15000 });
		await expect(response).not.toContainText('posts');
	});

	test('Mod+Shift+P prettifies the query', async ({ page }) => {
		await typeQuery(page, '{posts{nodes{id title}}}');
		await pressMod(page, 'Shift+P');

		// `print()` formats on multiple lines with indentation. We don't
		// pin the exact whitespace (that's a `graphql-js` concern) — we
		// just require it stop being a single dense line.
		const value = await readQuery(page);
		expect(value.split('\n').length).toBeGreaterThan(1);
		expect(value).toMatch(/posts/);
	});

	test('Mod+Shift+P is a no-op on unparseable input', async ({ page }) => {
		// The store action wraps `print(parse())` in try/catch and passes
		// the input through unchanged on parse failure (warns to console).
		const broken = '{ posts { ';
		await typeQuery(page, broken);
		await pressMod(page, 'Shift+P');

		const value = await readQuery(page);
		expect(value).toContain('posts');
		expect(value.split('\n').length).toBe(1);
	});

	test('Mod+Shift+M merges fragments into the query', async ({ page }) => {
		const doc =
			'query GetPosts { posts { nodes { ...PostFields } } }\n' +
			'fragment PostFields on Post { id title }\n';
		await typeQuery(page, doc);
		await pressMod(page, 'Shift+M');

		const value = await readQuery(page);
		// Spread and fragment definition collapse into the inlined fields.
		expect(value).not.toMatch(/\.\.\.PostFields/);
		expect(value).not.toMatch(/^fragment\b/m);
		expect(value).toMatch(/title/);
	});

	test('Ctrl+Space surfaces the autocomplete tooltip', async ({ page }) => {
		const editor = page.locator(selectors.graphqlEditorContent).first();
		await editor.click();
		// Clear, then type a partial identifier that the schema can
		// disambiguate (`posts`, `pages`, `postFormats`, etc. all start
		// with `p`).
		await pressMod(page, 'a');
		await page.keyboard.press('Backspace');
		await page.keyboard.type('{ p');
		await page.keyboard.press('Control+Space');

		await expect(
			page.locator('.cm-tooltip-autocomplete').first()
		).toBeVisible({ timeout: 5000 });
	});

	test('Tab indents and Shift+Tab outdents', async ({ page }) => {
		const editor = page.locator(selectors.graphqlEditorContent).first();
		await editor.click();
		await pressMod(page, 'a');
		await page.keyboard.press('Backspace');

		await page.keyboard.type('{');
		await page.keyboard.press('Enter');
		await page.keyboard.press('Tab');
		await page.keyboard.type('posts');

		const indented = await readQuery(page);
		const indentedLine = indented
			.split('\n')
			.find((l) => l.includes('posts'));
		const indentWidthBefore = indentedLine.match(/^\s*/)[0].length;
		expect(indentWidthBefore).toBeGreaterThan(0);

		// Shift+Tab outdents the current line. We assert the leading
		// whitespace shrinks rather than pinning a specific column —
		// CM6's smart-indent picks the width based on bracket context.
		await page.keyboard.press('Home');
		await page.keyboard.press('Shift+Tab');

		const outdented = await readQuery(page);
		const outdentedLine = outdented
			.split('\n')
			.find((l) => l.includes('posts'));
		const indentWidthAfter = outdentedLine.match(/^\s*/)[0].length;
		expect(indentWidthAfter).toBeLessThan(indentWidthBefore);
	});

	test('ArrowLeft / ArrowRight switch tabs', async ({ page }) => {
		const tabs = page.locator(selectors.tab);

		// Capture the starting tab count — prior tests or persisted
		// drafts may already have tabs open, so the new tab's index is
		// relative to whatever was there.
		const before = await tabs.count();
		await page.click(selectors.addTab);
		await expect(tabs).toHaveCount(before + 1);

		// Both blank tabs auto-title to "Untitled", so identify by index
		// in the tab strip rather than by visible text.
		const activeIndex = async () =>
			page.evaluate((sel) => {
				const all = Array.from(document.querySelectorAll(sel));
				return all.findIndex((el) =>
					el.classList.contains('is-active')
				);
			}, selectors.tab);

		const newTabIndex = before;
		const leftNeighborIndex = newTabIndex - 1;
		expect(await activeIndex()).toBe(newTabIndex);

		await page.locator(`${selectors.tab}.is-active`).first().focus();

		await page.keyboard.press('ArrowLeft');
		expect(await activeIndex()).toBe(leftNeighborIndex);

		await page.keyboard.press('ArrowRight');
		expect(await activeIndex()).toBe(newTabIndex);
	});

	test('Mod+S saves the Settings workspace tab', async ({ page }) => {
		await openSettingsTab(page);

		// Settings shape varies per build (registered fields may be
		// checkboxes, radios, or text inputs). Flip the first
		// interactable control we find — any change is enough to dirty
		// the form.
		const pane = page.locator('.wpgraphql-ide-settings-tab');
		const checkbox = pane
			.locator('input[type="checkbox"]:not([disabled])')
			.first();
		const uncheckedRadio = pane
			.locator('input[type="radio"]:not([disabled]):not(:checked)')
			.first();
		const textInput = pane
			.locator(
				'input[type="text"]:not([disabled]), input[type="url"]:not([disabled]), textarea:not([disabled])'
			)
			.first();

		if (await checkbox.count()) {
			await checkbox.click();
		} else if (await uncheckedRadio.count()) {
			await uncheckedRadio.click();
		} else if (await textInput.count()) {
			await textInput.fill('e2e-shortcut-probe');
		} else {
			throw new Error(
				'No interactable settings field found to dirty the form.'
			);
		}

		const saveButton = page.getByRole('button', { name: 'Save changes' });
		await expect(saveButton).toBeEnabled();

		await pressMod(page, 's');

		// Successful save re-disables the button (`!isDirty || isSaving`).
		await expect(saveButton).toBeDisabled({ timeout: 10000 });
	});
});
