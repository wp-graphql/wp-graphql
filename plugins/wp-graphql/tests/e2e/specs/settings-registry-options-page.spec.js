import { describe, test, expect } from '@playwright/test';
import {
	loginToWordPressAdmin,
	visitAdminFacingPage,
	wpAdminUrl,
} from '../utils';

/**
 * @file settings-registry-options-page.spec.js
 * @description End-to-end test to verify that updating WPGraphQL options via /wp-admin/options.php
 * does not cause fatal errors.
 *
 * This test simulates the scenario where a user updates an option via options.php
 * and the option value is an empty string, which previously caused a fatal error
 * because sanitize_options() expected an array but received a string.
 */

describe('Settings Registry Options Page', () => {
	test('Updating WPGraphQL options via options.php does not cause fatal errors', async ({
		page,
	}) => {
		await loginToWordPressAdmin(page);

		// Test with graphql_experiments_settings (the option mentioned in the issue)
		await visitAdminFacingPage(page, wpAdminUrl + '/options.php');

		// Verify we're on the options page
		await expect(page.getByText('All Settings')).toBeVisible();

		// Submit the form with graphql_experiments_settings set to empty string
		// This simulates the scenario where a user updates an option via options.php
		// and the option value is an empty string
		const graphqlExperimentsInput = page.locator(
			'input[name="graphql_experiments_settings"]'
		);
		if ((await graphqlExperimentsInput.count()) > 0) {
			await graphqlExperimentsInput.fill('');

			// Submit the form
			await page
				.locator('form')
				.first()
				.evaluate((form) => form.submit());

			// Wait for navigation
			await page.waitForLoadState('networkidle');

			// Verify that no fatal error occurred
			await expect(page.locator('body')).not.toContainText('Fatal error');
			await expect(page.locator('body')).not.toContainText(
				'Uncaught TypeError'
			);
			await expect(page.locator('body')).not.toContainText(
				'sanitize_options(): Argument #1 ($options) must be of type array'
			);
		}

		// Test with graphql_general_settings as well
		await visitAdminFacingPage(page, wpAdminUrl + '/options.php');
		await expect(page.getByText('All Settings')).toBeVisible();

		// Submit the form with graphql_general_settings set to empty string
		const graphqlGeneralInput = page.locator(
			'input[name="graphql_general_settings"]'
		);
		if ((await graphqlGeneralInput.count()) > 0) {
			await graphqlGeneralInput.fill('');

			// Submit the form
			await page
				.locator('form')
				.first()
				.evaluate((form) => form.submit());

			// Wait for navigation
			await page.waitForLoadState('networkidle');

			// Verify that no fatal error occurred
			await expect(page.locator('body')).not.toContainText('Fatal error');
			await expect(page.locator('body')).not.toContainText(
				'Uncaught TypeError'
			);
			await expect(page.locator('body')).not.toContainText(
				'sanitize_options(): Argument #1 ($options) must be of type array'
			);
		}
	});
});
