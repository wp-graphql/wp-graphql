import { execSync } from 'node:child_process';
import {
	loginToWordPressAdmin,
	ensureDocumentOpen,
	typeQuery,
	readQuery,
	selectors,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8889';
// Browser GET to the GraphQL endpoint URL. The IDE's public-endpoint
// module intercepts requests with `Accept: text/html` (Playwright sends
// that by default) and renders the IDE shell instead of the JSON API.
const ENDPOINT_URL = `${BASE_URL}/graphql`;

/**
 * Toggle the public-endpoint IDE setting on or off via wp-cli in the
 * tests-cli container. WPGraphQL stores `graphql_ide_settings` as a
 * single serialized option. `wp option patch update` would be the
 * narrowest knob but it requires the subkey to already exist (it
 * errors when the option is the empty array a fresh install starts
 * with), so we use `wp option patch insert` for the first toggle and
 * `update` for subsequent toggles via a shell `||` fallback.
 *
 * @param {'on' | 'off'} value
 */
function setEndpointMode(value) {
	execSync(
		`npm run --prefix ../.. wp-env run tests-cli -- bash -c "wp option patch update graphql_ide_settings graphql_ide_public_endpoint ${value} || wp option patch insert graphql_ide_settings graphql_ide_public_endpoint ${value}"`,
		{ stdio: 'pipe' }
	);
}

test.describe('Endpoint mode — IDE shell at the GraphQL endpoint URL', () => {
	// Endpoint mode is opt-in (default off so visiting `/graphql` keeps
	// returning JSON to API clients). The suite opts in here, runs the
	// parity tests, and turns it back off so other specs continue
	// against the default behavior.
	test.beforeAll(() => {
		setEndpointMode('on');
	});

	test.afterAll(() => {
		setEndpointMode('off');
	});

	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await page.goto(ENDPOINT_URL, { waitUntil: 'domcontentloaded' });
		await page.waitForSelector(selectors.ideRoot, {
			state: 'visible',
			timeout: 15000,
		});
		await ensureDocumentOpen(page);
	});

	test('renders the IDE shell instead of returning JSON', async ({
		page,
	}) => {
		// IDE root present + WPGraphQL response shape absent in the DOM
		// confirms `parse_request` correctly handed off to the IDE
		// renderer (not the JSON resolver).
		await expect(page.locator(selectors.ideRoot)).toBeVisible();
		await expect(
			page.locator(selectors.graphqlEditor).first()
		).toBeVisible();
	});

	test('typing into the editor updates the editor content', async ({
		page,
	}) => {
		await typeQuery(page, '{ posts { nodes { id } } }');
		const value = await readQuery(page);
		expect(value).toContain('posts');
	});

	test('clicking Execute query fires a GraphQL request', async ({ page }) => {
		await typeQuery(page, '{ posts { nodes { id } } }');

		const requestPromise = page.waitForRequest(
			(req) => /graphql/i.test(req.url()) && req.method() === 'POST',
			{ timeout: 10000 }
		);
		await page.getByRole('button', { name: 'Execute query' }).click();
		const request = await requestPromise;
		expect(request.url()).toMatch(/graphql/);
	});

	test('autocomplete dropdown renders above the page (mode-parity guard)', async ({
		page,
	}) => {
		// Endpoint mode doesn't have a drawer to compete with, but the
		// `.cm-tooltip { z-index: 1000000 !important }` rule applies to
		// keep this assertion useful as a general visibility regression
		// guard across all three modes (standalone / drawer / endpoint).
		// Same shape as the drawer test — see comment there for why we
		// check computed z-index rather than `elementFromPoint`.
		await typeQuery(page, '{ posts { nodes { id title a');

		const popup = page.locator('.cm-tooltip-autocomplete').first();
		await expect(popup).toBeVisible({ timeout: 5000 });

		const computedZ = await popup.evaluate(
			(el) => parseInt(getComputedStyle(el).zIndex, 10) || 0
		);

		expect(
			computedZ,
			`autocomplete popup z-index is ${computedZ}, expected > 999999 — likely the !important on .cm-tooltip was dropped`
		).toBeGreaterThan(999999);
	});
});
