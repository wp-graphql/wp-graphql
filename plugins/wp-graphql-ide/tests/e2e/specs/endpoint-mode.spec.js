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
 * single serialized option, so `wp option patch update` is the
 * narrowest knob — won't disturb other IDE settings the suite assumes.
 *
 * @param {'on' | 'off'} value
 */
function setEndpointMode(value) {
	execSync(
		`npm run --prefix ../.. wp-env run tests-cli -- wp option patch update graphql_ide_settings graphql_ide_public_endpoint ${value}`,
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

	test('renders the IDE shell instead of returning JSON', async ({ page }) => {
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
		// same z-index rule (`.cm-tooltip { z-index: 1000000 }`) applies
		// to keep this assertion useful as a general visibility regression
		// guard across all three modes (standalone / drawer / endpoint).
		await typeQuery(page, '{ posts { nodes { id title a');

		const popup = page.locator('.cm-tooltip-autocomplete').first();
		await expect(popup).toBeVisible({ timeout: 5000 });

		const isHitTestOnTop = await popup.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			const cx = Math.floor(rect.left + rect.width / 2);
			const cy = Math.floor(rect.top + rect.height / 2);
			const hit = document.elementFromPoint(cx, cy);
			return hit !== null && (el === hit || el.contains(hit));
		});

		expect(
			isHitTestOnTop,
			'autocomplete popup is occluded at its center — visibility regression in endpoint mode'
		).toBe(true);
	});
});
