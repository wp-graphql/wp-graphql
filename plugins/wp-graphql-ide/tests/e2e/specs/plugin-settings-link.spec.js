import {
	loginToWordPressAdmin,
	visitPluginsPage,
	wpAdminUrl,
} from '../utils.js';
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

export const selectors = {
	pluginsMenuItem: '#menu-plugins a',
	pluginSettingsLink: 'a[href*="graphql-settings#graphql_ide_settings"]',
	ideSettingsTab: '#graphql_ide_settings-tab',
};

// Login to WordPress before each test
test.beforeEach(async ({ page }) => {
	await loginToWordPressAdmin(page);
});

test('should navigate to plugin settings and display IDE Settings tab', async ({
	page,
}) => {
	// Go to Plugins page
	await visitPluginsPage(page);

	// Click on the plugin settings link
	await page.click(selectors.pluginSettingsLink);

	// Correct the expected URL string
	const expectedUrl = `${wpAdminUrl}/admin.php?page=graphql-settings#graphql_ide_settings`;
	await expect(page).toHaveURL(expectedUrl);

	// Check that the IDE Settings tab is visible
	await expect(page.locator(selectors.ideSettingsTab)).toBeVisible();
});
