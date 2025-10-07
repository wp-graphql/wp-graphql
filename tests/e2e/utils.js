/**
 * Utility functions for Playwright tests in WordPress Admin and GraphiQL IDE.
 *
 * This file contains helper functions designed to simplify interactions with the WordPress
 * admin dashboard and the GraphiQL IDE within end-to-end tests using Playwright. Functions
 * include logging into the WordPress admin, typing queries and variables into CodeMirror editors,
 * and clearing CodeMirror editor content.
 *
 * @file Utility functions for WordPress Admin and GraphiQL IDE interaction.
 * @module utils
 */

/**
 * @typedef {Object} Selectors
 * @property {string} loginUsername - The CSS selector for the username input field in the WordPress login form.
 * @property {string} loginPassword - The CSS selector for the password input field in the WordPress login form.
 * @property {string} submitButton  - The CSS selector for the submit button in the WordPress login form.
 */

/**
 * CSS selectors used for navigating the WordPress admin login page.
 * @type {Selectors}
 */
const selectors = {
    loginUsername: '#user_login',
    loginPassword: '#user_pass',
    submitButton: '#wp-submit',
};

export const wpHomeUrl = 'http://localhost:8889';
export const wpAdminUrl = 'http://localhost:8889/wp-admin';

/**
 * Log in to the WordPress admin dashboard.
 * @param {import('@playwright/test').Page} page The Playwright page object.
 */
export async function loginToWordPressAdmin( page ) {
    await page.goto( 'http://localhost:8889/wp-admin', {
        waitUntil: 'networkidle',
    } );

    // Check if we're already logged in after navigating to admin
    const isLoggedIn = await page.$( '#wpadminbar' );

    // If already logged in, return early
    if ( isLoggedIn ) {
        return;
    }

    // If not logged in, fill the login form
    await page.fill( selectors.loginUsername, 'admin' );
    await page.fill( selectors.loginPassword, 'password' );
    await page.click( selectors.submitButton );
    await page.waitForSelector( '#wpadminbar' ); // Confirm login by waiting for the admin bar
}

/**
 * Types a GraphQL query into the CodeMirror editor.
 * @param {import('@playwright/test').Page} page  The Playwright page object.
 * @param {string}                          query The GraphQL query to type.
 */
export async function typeQuery( page, query = '' ) {

    const selector = '.query-editor .cm-s-graphiql';

    // Set the value
    await page.evaluate( async ({ query, selector }) => {
        const editor = document.querySelector(selector).CodeMirror;
        await editor.setValue( query );
    }, { query, selector });

    // Wait for the value to be set
    await page.waitForTimeout( 500 );
}

/**
 * Types GraphQL variables into the CodeMirror editor.
 * @param {import('@playwright/test').Page} page      The Playwright page object.
 * @param {Object}                          variables The GraphQL variables to type (as an object).
 */
export async function typeVariables( page, variables ) {
    const variablesString = JSON.stringify( variables, null, 2 );
    // remove trailing curly brace. As users type, the IDE adds a trailing brace, so we're going to trim it
    // as a user wouldn't actually type an extra trailing brace
    const trimmedVariableString = variablesString.substring( 0, -1 );
    await page.click( '[data-name="variables"]' );
    const variablesSelector =
        '.graphiql-editor-tool[aria-label="Variables"]:not(.hidden)';
    await clearCodeMirror( page, variablesSelector );
    await page.keyboard.type( trimmedVariableString );
}

/**
 * Types GraphQL variables into the CodeMirror editor.
 * @param {import('@playwright/test').Page} page      The Playwright page object.
 * @param {string}                          variables The GraphQL variables to type (as a string).
 */
export async function pasteVariables( page, variables ) {
    const trimmedVariableString = variablesString.substring( 0, -1 );

    // open the variable editor
    await page.click( '[data-name="variables"]' );
    // await clearCodeMirror(page, variablesSelector);

    // set the value on the CodeMirror editor
    const variablesSelector =
        '.graphiql-editor-tool[aria-label="Variables"]:not(.hidden) .cm-s-graphiql';
    const variableEditor = await page.locator( variablesSelector );
    const variableEditorInstance = variableEditor.CodeMirror;
    await variableEditorInstance.setValue( trimmedVariableString );
}

/**
 * Clears the content of a CodeMirror editor.
 * @param {import('@playwright/test').Page} page     The Playwright page object.
 * @param {string}                          selector The CSS selector for the CodeMirror editor.
 */
export async function clearCodeMirror( page, selector ) {
    await page.click( selector );
    // Use the appropriate select all command based on the OS
    const selectAllCommand =
        process.platform === 'darwin' ? 'Meta+A' : 'Control+A';
    await page.keyboard.press( selectAllCommand ); // Select all text
    await page.keyboard.press( 'Backspace' ); // Clear the selection
}
export async function visitPublicFacingPage( page ) {
    await page.goto( wpHomeUrl, { waitUntil: 'networkidle' } );
}

export async function visitAdminFacingPage( page, path = null ) {

    if ( ! path ) {
        path = wpAdminUrl;
    }
    await page.goto( path, { waitUntil: 'networkidle' } );
}

export async function visitPluginsPage( page ) {
    await page.goto( `${ wpAdminUrl }/plugins.php`, {
        waitUntil: 'networkidle',
    } );
}

export async function openDrawer( page ) {
    const isDrawerVisible = await page
        .locator( '.graphiql-container' )
        .isVisible();
    if ( ! isDrawerVisible ) {
        await page.waitForSelector( '.EditorDrawerButton', {
            state: 'visible',
        } );
        await clickDrawerButton( page );
        await page.waitForSelector( '.graphiql-container', {
            state: 'visible',
        } );
    }
}

export async function closeDrawer( page ) {
    const isDrawerVisible = await page
        .locator( '.graphiql-container' )
        .isVisible();

    if ( isDrawerVisible ) {
        const overlay = await page.locator( '[vaul-overlay]' );
        if ( overlay ) {
            await overlay.click();
        }
        await expect( page.locator( '.graphiql-container' ) ).toBeHidden();
        await page.waitForSelector( '.EditorDrawerButton', {
            state: 'visible',
        } );
        await clickDrawerCloseButton( page );
        await page.waitForSelector( '.graphiql-container', {
            state: 'hidden',
        } );
    }
}

export async function clickDrawerButton( page ) {
    await page.click( '.EditorDrawerButton' );
}

export async function clickDrawerCloseButton( page ) {
    await page.click( '.EditorDrawerCloseButton' );
}

export async function executeQuery( page ) {
    await page.click( '.graphiql-execute-button' );
}

export async function selectAndClearTextUsingKeyboard( page, selector ) {
    await page.click( selector ); // Focus the element

    // Determine the operating system to use the correct "Select All" shortcut
    const selectAllCommand =
        process.platform === 'darwin' ? 'Meta+A' : 'Control+A';
    await page.keyboard.press( selectAllCommand ); // Select all text using OS-specific shortcut
    await page.keyboard.press( 'Backspace' ); // Clear selected text
}

export async function loadGraphiQL( page, queryParams = { query: null, variables: null, isQueryComposerOpen: null } ) {

    const {
        query,
        variables,
        isQueryComposerOpen,
    } = queryParams;

    let _queryParams = '';

    if ( query ) {
        _queryParams += `&query=${encodeURIComponent( query )}`;
    }

    if ( variables ) {
        _queryParams += `&variables=${encodeURIComponent( JSON.stringify( variables ) )}`;
    }

    _queryParams += `&isQueryComposerOpen=${isQueryComposerOpen ? "true" : "false" }`

    await visitAdminFacingPage( page, wpAdminUrl + `/admin.php?page=graphiql-ide${_queryParams}` );
    await page.waitForSelector( '.graphiql-container', {
        state: 'visible',
    } );

}

/**
 * Activates the specified plugin in WordPress admin.
 *
 * @param {import('@playwright/test').Page} page - The Playwright page object.
 * @param {string} slug - The slug of the plugin to activate.
 * @returns {Promise<void>}
 */
export async function activatePlugin(page, slug) {
    await visitAdminFacingPage(page, wpAdminUrl + '/plugins.php');
    const pluginRow = page.locator(`tr[data-slug="${slug}"]`);
    const isPluginActive = await pluginRow.locator('.deactivate').isVisible();
    if (!isPluginActive) {
        await pluginRow.locator('.activate a').click();
    }
}

/**
 * Deactivates the specified plugin in WordPress admin.
 *
 * @param {import('@playwright/test').Page} page - The Playwright page object.
 * @param {string} slug - The slug of the plugin to deactivate.
 * @returns {Promise<void>}
 */
export async function deactivatePlugin(page, slug) {
    await visitAdminFacingPage(page, wpAdminUrl + '/plugins.php');
    const pluginRow = page.locator(`tr[data-slug="${slug}"]`);
    const isPluginActive = await pluginRow.locator('.deactivate').isVisible();
    if (isPluginActive) {
        await pluginRow.locator('.deactivate a').click();
    }
}
