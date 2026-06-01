/**
 * Playwright utilities for the rebuilt WPGraphQL IDE.
 *
 * Selectors target the new IDE markup (`#wpgraphql-ide-app`,
 * `.wpgraphql-ide-graphql-editor`, CodeMirror 6 `.cm-editor`,
 * `.AppDrawerButton`). The legacy `.graphiql-container` markup is gone.
 *
 * Override `WP_BASE_URL` to run against a non-default port (e.g. when
 * `.wp-env.override.json` moves the test site to 8899).
 */

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8889';

export const wpHomeUrl = BASE_URL;
export const wpAdminUrl = `${BASE_URL}/wp-admin`;
export const wpDedicatedIdeUrl = `${wpAdminUrl}/admin.php?page=graphql-ide`;

const SELECTORS = {
	loginUsername: '#user_login',
	loginPassword: '#user_pass',
	submitButton: '#wp-submit',

	// Top-level
	ideRoot: '#wpgraphql-ide-app',

	// Drawer
	drawerButton: '.AppDrawerButton',
	drawerContent: '[data-vaul-drawer]',

	// Editors (CodeMirror 6)
	graphqlEditor: '.wpgraphql-ide-graphql-editor .cm-editor',
	graphqlEditorContent: '.wpgraphql-ide-graphql-editor .cm-content',
	jsonEditorContent: '.wpgraphql-ide-json-editor .cm-content',

	// Tabs / toolbar
	tabRow: '.wpgraphql-ide-tab-row',
	tab: '.wpgraphql-ide-tab',
	addTab: '.wpgraphql-ide-tab-add',

	// Activity bar
	activityBar: '.wpgraphql-ide-activity-bar',

	// Response
	responseStatus: '.wpgraphql-ide-response-status',
};

export const selectors = SELECTORS;

/**
 * Log into wp-admin if not already logged in.
 * @param page
 */
export async function loginToWordPressAdmin(page) {
	await page.goto(wpAdminUrl, { waitUntil: 'domcontentloaded' });

	if (await page.$('#wpadminbar')) {
		return;
	}

	await page.waitForSelector(SELECTORS.loginUsername, {
		state: 'visible',
		timeout: 15000,
	});
	await page.fill(SELECTORS.loginUsername, 'admin');
	await page.fill(SELECTORS.loginPassword, 'password');
	await page.click(SELECTORS.submitButton);
	await page.waitForSelector('#wpadminbar', { state: 'visible' });
}

/**
 * Navigate to the dedicated IDE page (no drawer).
 * @param page
 */
export async function visitDedicatedIde(page) {
	await page.goto(wpDedicatedIdeUrl, { waitUntil: 'domcontentloaded' });
	await page.waitForSelector(SELECTORS.ideRoot, {
		state: 'visible',
		timeout: 15000,
	});
}

/**
 * Ensure there's at least one document tab open.
 *
 * On a fresh session the IDE renders the "No queries open" surface
 * instead of an editor — the persistent tab strip still shows the `+`
 * button, so we click that to mount a fresh tab.
 * @param page
 */
export async function ensureDocumentOpen(page) {
	await page.waitForSelector(SELECTORS.tabRow, {
		state: 'visible',
		timeout: 10000,
	});
	const empty = page.locator('.wpgraphql-ide-workspace-empty');
	if (await empty.isVisible().catch(() => false)) {
		// Bypass Playwright's actionability layer with a JS-side click.
		// `locator.click()` re-checks the element is "stable" mid-action
		// and retries on detach — which races the new DOM since the
		// click itself causes the detach. `evaluate(el.click())` fires
		// once and returns; we then wait for the new DOM state.
		await page.locator(SELECTORS.addTab).evaluate((el) => el.click());
		await page.waitForSelector(SELECTORS.graphqlEditor, {
			state: 'visible',
			timeout: 10000,
		});
	}
}

/**
 * Open the drawer from any admin page. Idempotent.
 * @param page
 */
export async function openDrawer(page) {
	await page.waitForSelector(SELECTORS.drawerButton, {
		state: 'visible',
		timeout: 10000,
	});

	if (await page.locator(SELECTORS.drawerContent).isVisible()) {
		return;
	}

	await page.click(SELECTORS.drawerButton);
	await page.waitForSelector(SELECTORS.drawerContent, {
		state: 'visible',
		timeout: 10000,
	});
}

/**
 * Type into the active GraphQL editor. Replaces any existing content.
 * Uses CodeMirror 6's contentEditable surface — Playwright can target it
 * directly via `.cm-content`.
 * @param page
 * @param query
 */
export async function typeQuery(page, query) {
	const editor = page.locator(SELECTORS.graphqlEditorContent).first();
	await editor.click();
	await selectAllAndDelete(page);
	await page.keyboard.type(query);
}

/**
 * Read the contents of the GraphQL editor.
 * @param page
 */
export async function readQuery(page) {
	return await page
		.locator(SELECTORS.graphqlEditorContent)
		.first()
		.evaluate((el) => el.innerText);
}

/**
 * The Mod key, resolved to `Meta` on macOS and `Control` elsewhere — matches
 * CodeMirror's `Mod-…` keymap convention so tests stay portable across
 * dev and CI runners.
 */
export const MOD_KEY = process.platform === 'darwin' ? 'Meta' : 'Control';

/**
 * Press a Mod chord (e.g. `Enter`, `Shift+P`, `a`).
 * @param page
 * @param suffix
 */
export async function pressMod(page, suffix) {
	await page.keyboard.press(`${MOD_KEY}+${suffix}`);
}

/**
 * Press Cmd/Ctrl+Enter to execute the query.
 * @param page
 */
export async function runQuery(page) {
	await pressMod(page, 'Enter');
}

async function selectAllAndDelete(page) {
	await pressMod(page, 'a');
	await page.keyboard.press('Backspace');
}

/**
 * Wait until a GraphQL request initiated by the IDE has settled.
 * @param page
 */
export async function waitForGraphQLResponse(page) {
	await page.waitForResponse(
		(response) =>
			/index\.php\?graphql/.test(response.url()) &&
			response.status() < 500,
		{ timeout: 10000 }
	);
}

/**
 * Open the Settings workspace tab via the topbar action. Idempotent —
 * a no-op when the Settings tab is already mounted.
 * @param page
 */
export async function openSettingsTab(page) {
	if (
		await page
			.locator('.wpgraphql-ide-settings-tab')
			.isVisible()
			.catch(() => false)
	) {
		return;
	}
	await page.getByRole('button', { name: 'WPGraphQL Settings' }).click();
	await page.waitForSelector('.wpgraphql-ide-settings-tab', {
		state: 'visible',
		timeout: 10000,
	});
}
