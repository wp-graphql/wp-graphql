import {
	loginToWordPressAdmin,
	visitDedicatedIde,
	ensureDocumentOpen,
} from '../utils.js';

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Auth toggle', () => {
	test.beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitDedicatedIde(page);
		await ensureDocumentOpen(page);
	});

	test('clicking the auth avatar flips between authenticated and public', async ({
		page,
	}) => {
		// Logged-in admins start authenticated. The button's aria-label
		// embeds the current direction ("click to switch to public" /
		// "click to switch to authenticated"), so we use that as the
		// state probe — no class name reliance.
		const auth = page.getByRole('button', {
			name: /Send as authenticated user/i,
		});
		await expect(auth).toBeVisible();
		await auth.click();
		await expect(
			page.getByRole('button', {
				name: /Send as public visitor/i,
			})
		).toBeVisible();

		// Toggle back so the public-mode classes don't leak into
		// any later assertions.
		await page
			.getByRole('button', {
				name: /Send as public visitor/i,
			})
			.click();
		await expect(
			page.getByRole('button', {
				name: /Send as authenticated user/i,
			})
		).toBeVisible();
	});

	test('public mode strips the X-WP-Nonce header from outgoing requests', async ({
		page,
	}) => {
		// The fetcher injects `X-WP-Nonce` only when the auth toggle is on.
		// Capture the next /graphql request after switching to public and
		// assert the header is absent.
		await page
			.getByRole('button', {
				name: /Send as authenticated user/i,
			})
			.click();
		await expect(
			page.getByRole('button', {
				name: /Send as public visitor/i,
			})
		).toBeVisible();

		// Type a fresh query so the editor isn't dirty in a way that
		// pre-execute might bail.
		const editorContent = page
			.locator('.wpgraphql-ide-graphql-editor .cm-content')
			.first();
		await editorContent.click();
		await page.keyboard.press(
			process.platform === 'darwin' ? 'Meta+a' : 'Control+a'
		);
		await page.keyboard.press('Backspace');
		await page.keyboard.type('{ __typename }');

		const [request] = await Promise.all([
			page.waitForRequest((req) =>
				req.url().includes('graphql') && req.method() === 'POST'
			),
			page.keyboard.press(
				process.platform === 'darwin' ? 'Meta+Enter' : 'Control+Enter'
			),
		]);

		const headers = await request.allHeaders();
		expect(headers['x-wp-nonce']).toBeFalsy();
	});
});
