/**
 * External dependencies
 */
import { request } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 *
 * @param {import('@playwright/test').FullConfig} config
 * @return {Promise<void>}
 */
async function globalSetup(config) {
	const { storageState, baseURL } = config.projects[0].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext({
		baseURL,
	});

	const requestUtils = new RequestUtils(requestContext, {
		storageStatePath,
	});

	try {
		await requestUtils.setupRest();
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('🚧 Consider checking WordPress for PHP errors.');
		throw error;
	}

	// Reset the test environment before running the tests.
	await Promise.all([
		requestUtils.activateTheme('twentytwentyone'),
		requestUtils.deleteAllPosts(),
		requestUtils.deleteAllBlocks(),
		requestUtils.resetPreferences(),
	]);

	await requestContext.dispose();
}

export default globalSetup;
