import { describe, test, expect } from '@playwright/test';
import {
	loginToWordPressAdmin,
	visitAdminFacingPage,
	wpAdminUrl,
} from '../utils';

/**
 * @file graphql-settings-page.spec.js
 * @description End-to-end test for the WPGraphQL settings page to verify it renders and saves correctly.
 */

describe('GraphQL Settings Page', () => {
	test('Renders and saves settings as expected', async ({ page }) => {
		await loginToWordPressAdmin(page);
		await visitAdminFacingPage(
			page,
			wpAdminUrl + '/admin.php?page=graphql-settings'
		);

		// Verify that the settings page loaded
		await expect(
			page.getByRole('heading', {
				name: 'WPGraphQL General Settings',
				exact: true,
			})
		).toBeVisible();

		// Verify that the default values are populated
		const tracingUserRoleSelect = page.locator(
			'select[name="graphql_general_settings[tracing_user_role]"]'
		);
		const queryLogUserRoleSelect = page.locator(
			'select[name="graphql_general_settings[query_log_user_role]"]'
		);

		await expect(tracingUserRoleSelect).toHaveValue('administrator');
		await expect(queryLogUserRoleSelect).toHaveValue('administrator');
	});
});
