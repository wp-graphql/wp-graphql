import { describe, test, expect, beforeEach, afterEach } from '@playwright/test';
import { loginToWordPressAdmin, visitAdminFacingPage, wpAdminUrl } from '../utils';
import { activatePlugin, deactivatePlugin } from '../utils';

/**
 * @file settings-page.spec.js
 * @description End-to-end tests for the WPGraphQL settings page. This spec relies on a custom WordPress plugin
 * located at `./tests/e2e/plugins/settings-page-spec/` that registers additional settings sections and fields
 * for testing purposes.
 */

const selectors = {
    navTabGeneral: '#graphql_general_settings-tab',
    navTabA: '#graphql_section_a_settings-tab',
    navTabB: '#graphql_section_b_settings-tab',
    sectionA: '#graphql_section_a_settings',
    sectionB: '#graphql_section_b_settings',
    generalSettings: '#graphql_general_settings',
    checkboxA: '#wpuf-graphql_section_a_settings\\[graphql_section_a_checkbox\\]',
    checkboxB: '#wpuf-graphql_section_b_settings\\[graphql_section_b_checkbox\\]'
};

const pluginSlug = 'settings-page-spec';

describe('Settings Page', () => {

    beforeEach(async ({ page }) => {
        await loginToWordPressAdmin(page);

        // Activate the custom plugin for the test
        await activatePlugin(page, pluginSlug);
    });

    afterEach(async ({ page }) => {
        // Deactivate the custom plugin after the test
        await deactivatePlugin(page, pluginSlug);
    });

    test('Verify custom plugin is active and tabs are present', async ({ page }) => {
        await visitAdminFacingPage(page, wpAdminUrl + '/admin.php?page=graphql-settings');
        await page.waitForTimeout(500);

        await expect(page.locator(selectors.navTabGeneral)).toBeVisible();
        await expect(page.locator(selectors.navTabA)).toBeVisible();
        await expect(page.locator(selectors.navTabB)).toBeVisible();
    });

    test('Switch between tabs and verify visibility', async ({ page }) => {
        await visitAdminFacingPage(page, wpAdminUrl + '/admin.php?page=graphql-settings');
        await page.waitForTimeout(500);

        // Verify General tab is active by default
        await expect(page.locator(selectors.navTabGeneral)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.generalSettings)).toBeVisible();
        await expect(page.locator(selectors.sectionA)).not.toBeVisible();
        await expect(page.locator(selectors.sectionB)).not.toBeVisible();

        // Switch to Section A tab
        await page.locator(selectors.navTabA).click();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.navTabA)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.sectionA)).toBeVisible();
        await expect(page.locator(selectors.generalSettings)).not.toBeVisible();
        await expect(page.locator(selectors.sectionB)).not.toBeVisible();

        // Switch to Section B tab
        await page.locator(selectors.navTabB).click();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.navTabB)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.sectionB)).toBeVisible();
        await expect(page.locator(selectors.generalSettings)).not.toBeVisible();
        await expect(page.locator(selectors.sectionA)).not.toBeVisible();
    });

    test('Verify checkbox functionality in Section A and B', async ({ page }) => {
        await visitAdminFacingPage(page, wpAdminUrl + '/admin.php?page=graphql-settings');
        await page.waitForTimeout(500);

        // Switch to Section A tab and check checkbox
        await page.locator(selectors.navTabA).click();
        await page.waitForTimeout(500);
        await page.locator(selectors.checkboxA).check();
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.checkboxA)).toBeChecked();

        // Switch to Section B tab and check checkbox
        await page.locator(selectors.navTabB).click();
        await page.waitForTimeout(500);
        await page.locator(selectors.checkboxB).check();
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.checkboxB)).toBeChecked();
    });

    test('Verify localStorage retains last active tab', async ({ page }) => {
        await visitAdminFacingPage(page, wpAdminUrl + '/admin.php?page=graphql-settings');
        await page.waitForTimeout(500);

        // Clear localStorage to ensure clean test state
        await page.evaluate(() => {
            try {
                localStorage.clear();
            } catch (e) {
                // Ignore localStorage access errors
            }
        });

        // Switch to Section A tab
        await page.locator(selectors.navTabA).click();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.navTabA)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.sectionA)).toBeVisible();

        // Reload and check if Section A is still active
        await page.reload();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.navTabA)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.sectionA)).toBeVisible();

        // Switch to Section B tab
        await page.locator(selectors.navTabB).click();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.navTabB)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.sectionB)).toBeVisible();

        // Reload and check if Section B is still active
        await page.reload();
        await page.waitForTimeout(500);
        await expect(page.locator(selectors.navTabB)).toHaveClass(/nav-tab-active/);
        await expect(page.locator(selectors.sectionB)).toBeVisible();
    });
});
