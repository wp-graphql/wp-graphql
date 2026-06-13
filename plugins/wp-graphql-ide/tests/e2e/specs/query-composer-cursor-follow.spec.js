import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
	typeQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

// CodeMirror 6 renders each syntactic token as a text node inside `.cm-line`.
// Clicking the literal token text places the cursor inside it, which is the
// same surface a user would interact with — preferable to driving the
// EditorView via private APIs.
const clickTokenInEditor = async (page, token) =>
	page
		.locator(`${selectors.graphqlEditorContent} .cm-line`)
		.getByText(token, { exact: true })
		.first()
		.click();

const composerPanel = (page) =>
	page.locator('.wpgraphql-ide-query-composer-inline');

const fieldRow = (page, path) =>
	composerPanel(page).locator(`[data-field-path="${path}"]`);

const openQueryComposer = async (page) => {
	const btn = page
		.locator(selectors.activityBar)
		.getByRole('button', { name: 'Query Composer', exact: true });
	if ((await btn.getAttribute('aria-pressed')) !== 'true') {
		await btn.click();
	}
	await expect(btn).toHaveAttribute('aria-pressed', 'true');
};

test.describe('Query Composer follows the editor cursor', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
		await openQueryComposer(page);
	});

	test('reveals the row matching the cursor position at field granularity', async ({
		page,
	}) => {
		await typeQuery(
			page,
			'query MyQuery { posts { nodes { title excerpt } } }'
		);

		// Composer rendered the rows we're going to navigate to.
		await expect(
			fieldRow(page, 'query:MyQuery|posts|nodes|title')
		).toBeVisible();
		await expect(
			fieldRow(page, 'query:MyQuery|posts|nodes|excerpt')
		).toBeVisible();

		// Cursor onto `title` → that row gets the cursor-target class.
		await clickTokenInEditor(page, 'title');
		await expect(
			fieldRow(page, 'query:MyQuery|posts|nodes|title')
		).toHaveClass(/graphiql-explorer-row--cursor-target/);

		// Cursor onto `excerpt` → highlight moves, title's class clears.
		await clickTokenInEditor(page, 'excerpt');
		await expect(
			fieldRow(page, 'query:MyQuery|posts|nodes|excerpt')
		).toHaveClass(/graphiql-explorer-row--cursor-target/);
		await expect(
			fieldRow(page, 'query:MyQuery|posts|nodes|title')
		).not.toHaveClass(/graphiql-explorer-row--cursor-target/);
	});

	test('cursor on a parent field reveals the parent row, not a child', async ({
		page,
	}) => {
		await typeQuery(page, 'query MyQuery { posts { nodes { title } } }');

		await expect(fieldRow(page, 'query:MyQuery|posts')).toBeVisible();

		await clickTokenInEditor(page, 'posts');
		await expect(fieldRow(page, 'query:MyQuery|posts')).toHaveClass(
			/graphiql-explorer-row--cursor-target/
		);
	});

	test('cursor on a name that is not a schema field falls back to the nearest ancestor row', async ({
		page,
	}) => {
		// `fakeSlugXYZ` is not a field on Post — the cursor still resolves
		// to a parsed Field AST node, but no row exists for it in the
		// explorer. The reveal should fall back to the deepest ancestor
		// that does have a row (here: `nodes`).
		await typeQuery(
			page,
			'query MyQuery { posts { nodes { fakeSlugXYZ } } }'
		);

		await clickTokenInEditor(page, 'fakeSlugXYZ');
		await expect(fieldRow(page, 'query:MyQuery|posts|nodes')).toHaveClass(
			/graphiql-explorer-row--cursor-target/
		);
	});
});
