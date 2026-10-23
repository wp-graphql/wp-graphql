import { describe, test, expect, beforeEach } from '@playwright/test';
import {
	loginToWordPressAdmin,
	visitAdminFacingPage,
	wpAdminUrl,
} from '../utils';

/**
 * @file extensions-page.spec.js
 * @description End-to-end tests for how the Extensions page (GraphQL > Extensions) reports a failed install or
 * activation. Every request to the plugins REST endpoints is intercepted and answered by the test, so nothing is
 * installed or activated from WordPress.org.
 */

// A WordPress.org extension that is not installed in the test environment, so its card offers "Install & Activate".
const extensionName = 'WPGraphQL Yoast SEO Addon';
const extensionSlug = 'add-wpgraphql-seo';
const extensionPlugin = `${extensionSlug}/wp-graphql-yoast-seo`;

/**
 * The response WordPress's fatal error handler sends for a REST request that hits a PHP fatal error.
 */
const fatalErrorResponse = {
	code: 'internal_server_error',
	message:
		'<p>There has been a critical error on this website.</p><p><a href="https://wordpress.org/documentation/article/faq-troubleshooting/">Learn more about troubleshooting WordPress.</a></p>',
	data: {
		status: 500,
		error: {
			type: 1,
			message:
				'Uncaught Error: array_map(): Argument #2 ($array) must be of type array, null given',
			file: '/var/www/html/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
			line: 383,
		},
	},
	additional_errors: [],
};

/**
 * Returns the REST route of a request, whether the site uses pretty permalinks (`/wp-json/...`) or
 * plain ones (`?rest_route=...`).
 *
 * @param {string} url The request URL.
 * @return {string} The REST route, or an empty string for non-REST requests.
 */
const getRestRoute = (url) => {
	const { pathname, searchParams } = new URL(url);

	if (searchParams.has('rest_route')) {
		return searchParams.get('rest_route');
	}

	const index = pathname.indexOf('/wp-json/');

	return -1 === index ? '' : pathname.slice(index + '/wp-json'.length);
};

/**
 * Answers the Extensions page's plugin install (POST) and activation (PUT) requests.
 *
 * Requests the test does not answer are aborted, so a test can never install or activate a real plugin.
 *
 * @param {import('@playwright/test').Page}       page     The Playwright page object.
 * @param {{install?: Object, activate?: Object}} handlers Responses (`route.fulfill()` options) by step.
 * @return {Promise<{install: number, activate: number}>} Counts of the intercepted requests, updated as they arrive.
 */
const mockPluginRequests = async (page, handlers) => {
	const calls = { install: 0, activate: 0 };

	await page.route(
		(url) => getRestRoute(url.toString()).startsWith('/wp/v2/plugins'),
		async (route) => {
			const request = route.request();
			// apiFetch sends PUT as a POST with an override header.
			const method = (
				request.headers()['x-http-method-override'] || request.method()
			).toUpperCase();
			const restRoute = getRestRoute(request.url());

			if ('POST' === method && '/wp/v2/plugins' === restRoute) {
				calls.install++;
				if (handlers.install) {
					return route.fulfill(handlers.install);
				}
			}

			if ('PUT' === method && restRoute.startsWith('/wp/v2/plugins/')) {
				calls.activate++;
				if (handlers.activate) {
					return route.fulfill(handlers.activate);
				}
			}

			return route.abort();
		}
	);

	return calls;
};

/**
 * Opens the Extensions page and returns the card of the test extension.
 *
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<import('@playwright/test').Locator>} The extension's card.
 */
const openExtensionCard = async (page) => {
	await visitAdminFacingPage(
		page,
		wpAdminUrl + '/admin.php?page=wpgraphql-extensions'
	);

	const card = page
		.locator('.plugin-card')
		.filter({ has: page.getByRole('heading', { name: extensionName }) });

	await expect(
		card.getByRole('button', { name: 'Install & Activate' })
	).toBeVisible();

	return card;
};

describe('Extensions Page', () => {
	beforeEach(async ({ page }) => {
		await loginToWordPressAdmin(page);
	});

	test('explains an install whose response is not valid JSON, and links to the Plugins screen', async ({
		page,
	}) => {
		// A PHP notice printed before the JSON makes the response unparseable.
		const calls = await mockPluginRequests(page, {
			install: {
				status: 200,
				contentType: 'text/html; charset=UTF-8',
				body: `<br />\n<b>Notice</b>: Undefined index: slug in <b>/var/www/html/wp-content/plugins/example/example.php</b> on line <b>12</b><br />\n{"plugin":"${extensionPlugin}","status":"inactive"}`,
			},
		});

		const card = await openExtensionCard(page);
		await card.getByRole('button', { name: 'Install & Activate' }).click();

		const notice = card.locator('.components-notice.is-error');
		await expect(notice).toBeVisible();

		await expect(
			notice.locator('.wpgraphql-extension-error__message')
		).toHaveText(
			'The installation could not be confirmed because the site did not return a valid JSON response. This usually means a PHP error or notice was printed during the request.'
		);

		const link = notice.getByRole('link', {
			name: 'Install it from the Plugins screen instead',
		});
		await expect(link).toHaveAttribute(
			'href',
			`${wpAdminUrl}/plugin-install.php?s=${extensionSlug}&tab=search&type=term`
		);

		// apiFetch's raw error is kept for debugging, but only inside the collapsed details.
		const details = notice.locator('details pre');
		await expect(details).toBeHidden();
		await notice.getByText('Error details').click();
		await expect(details).toBeVisible();
		await expect(details).toContainText(
			'The response is not a valid JSON response.'
		);
		await expect(details).toContainText('Error code: invalid_json');

		// Activation is never attempted after a failed install, and the card offers the install again.
		expect(calls).toEqual({ install: 1, activate: 0 });
		await expect(
			card.getByRole('button', { name: 'Install & Activate' })
		).toBeEnabled();
	});

	test('explains an install stopped by a PHP fatal error, without rendering the error as HTML', async ({
		page,
	}) => {
		await mockPluginRequests(page, {
			install: {
				status: 500,
				contentType: 'application/json; charset=UTF-8',
				body: JSON.stringify(fatalErrorResponse),
			},
		});

		const card = await openExtensionCard(page);
		await card.getByRole('button', { name: 'Install & Activate' }).click();

		const notice = card.locator('.components-notice.is-error');
		await expect(
			notice.locator('.wpgraphql-extension-error__message')
		).toHaveText('A PHP error on this site stopped the installation.');
		await expect(
			notice.getByRole('link', {
				name: 'Install it from the Plugins screen instead',
			})
		).toHaveAttribute(
			'href',
			/\/plugin-install\.php\?s=add-wpgraphql-seo&/
		);

		// The PHP error is only in the details, and the response's markup is shown as text, never rendered.
		await expect(
			notice.locator('.wpgraphql-extension-error__message')
		).not.toContainText('array_map');
		await expect(
			notice.locator('a[href*="faq-troubleshooting"]')
		).toHaveCount(0);

		await notice.getByText('Error details').click();
		const details = notice.locator('details pre');
		await expect(details).toContainText(
			'There has been a critical error on this website.'
		);
		await expect(details).toContainText(
			'Uncaught Error: array_map(): Argument #2 ($array) must be of type array, null given in /var/www/html/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php on line 383'
		);
		await expect(details).toContainText(
			'Error code: internal_server_error'
		);
	});

	test('keeps a readable WordPress.org error as the message', async ({
		page,
	}) => {
		await mockPluginRequests(page, {
			install: {
				status: 404,
				contentType: 'application/json; charset=UTF-8',
				body: JSON.stringify({
					code: 'plugins_api_failed',
					message: 'Plugin not found.',
					data: { status: 404 },
				}),
			},
		});

		const card = await openExtensionCard(page);
		await card.getByRole('button', { name: 'Install & Activate' }).click();

		const notice = card.locator('.components-notice.is-error');
		await expect(
			notice.locator('.wpgraphql-extension-error__message')
		).toHaveText('Installation failed: Plugin not found.');
		await expect(
			notice.getByRole('link', {
				name: 'Install it from the Plugins screen instead',
			})
		).toBeVisible();
	});

	test('reports a failed activation after a successful install, and links to the Plugins screen', async ({
		page,
	}) => {
		const calls = await mockPluginRequests(page, {
			install: {
				status: 201,
				contentType: 'application/json; charset=UTF-8',
				body: JSON.stringify({
					plugin: extensionPlugin,
					status: 'inactive',
				}),
			},
			// The shape of the error response of WPGraphQL's activation route.
			activate: {
				status: 500,
				contentType: 'application/json; charset=UTF-8',
				body: JSON.stringify({
					status: 'error',
					message: 'Plugin file does not exist.',
				}),
			},
		});

		const card = await openExtensionCard(page);
		await card.getByRole('button', { name: 'Install & Activate' }).click();

		const notice = card.locator('.components-notice.is-error');
		await expect(
			notice.locator('.wpgraphql-extension-error__message')
		).toHaveText('Activation failed: Plugin file does not exist.');
		await expect(
			notice.getByRole('link', {
				name: 'Activate it from the Plugins screen instead',
			})
		).toHaveAttribute('href', `${wpAdminUrl}/plugins.php`);

		// The plugin was installed, so the card now offers to activate it rather than to install it again.
		expect(calls).toEqual({ install: 1, activate: 1 });
		await expect(
			card.getByRole('button', { name: 'Activate', exact: true })
		).toBeEnabled();
	});
});
