import { describe, test, expect, beforeEach } from '@playwright/test'
import { loginToWordPressAdmin, visitAdminFacingPage, wpAdminUrl } from '../utils'

const selectors = {
    graphiqlEnabledCheckbox: '#wpuf-graphql_general_settings\\[graphiql_enabled\\]',
}

describe( 'Settings Page', () => {

    beforeEach( async ({ page }) => {
        await loginToWordPressAdmin( page );
        await page.evaluate(() => localStorage.clear());
    });

    test( 'GraphiQL IDE can be disabled', async ({ page }) => {
        await visitAdminFacingPage( page, wpAdminUrl + '/admin.php?page=graphql-settings' );
        // await page.goto('http://localhost:8888/wp-login.php?redirect_to=http%3A%2F%2Flocalhost%3A8888%2Fwp-admin%2F&reauth=1');

        await page.waitForTimeout( 500 );
        await expect( page.locator(selectors.graphiqlEnabledCheckbox ) ).toBeChecked();
        await page.locator(selectors.graphiqlEnabledCheckbox ).uncheck();
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await page.waitForTimeout( 500 );
        await expect(page.getByText('Settings saved.')).toBeVisible();
        await expect( page.locator(selectors.graphiqlEnabledCheckbox ) ).not.toBeChecked();
        await page.locator( selectors.graphiqlEnabledCheckbox ).check();
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await page.waitForTimeout( 500 );
        await expect(page.getByText('Settings saved.')).toBeVisible();
        await expect( page.locator( selectors.graphiqlEnabledCheckbox ) ).toBeChecked();

    });

});
