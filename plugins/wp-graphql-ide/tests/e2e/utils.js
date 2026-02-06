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

export const wpHomeUrl = 'http://localhost:8888';
export const wpAdminUrl = 'http://localhost:8888/wp-admin';

/**
 * Log in to the WordPress admin dashboard.
 * @param {import('@playwright/test').Page} page The Playwright page object.
 */
export async function loginToWordPressAdmin(page) {
	const isLoggedIn = await page.$('#wpadminbar');

	// If already logged in, return early
	if (isLoggedIn) {
		return;
	}

	await page.goto('http://localhost:8888/wp-admin', {
		waitUntil: 'networkidle',
	});
	await page.fill(selectors.loginUsername, 'admin');
	await page.fill(selectors.loginPassword, 'password');
	await page.click(selectors.submitButton);
	await page.waitForSelector('#wpadminbar'); // Confirm login by waiting for the admin bar
}

/**
 * Returns the value of a CodeMirror editor.
 * @param  locator The Playwright locator for the CodeMirror editor.
 * @return {Promise<*>}
 */
export async function getCodeMirrorValue(locator) {
	return await locator.evaluate((queryEditorElement) => {
		// Access the CodeMirror instance and get its value
		const codeMirrorInstance = queryEditorElement.CodeMirror;
		return codeMirrorInstance.getValue();
	});
}

/**
 * Returns the value of a CodeMirror editor.
 * @param  locator The Playwright locator for the CodeMirror editor.
 * @param  string  The value to set in the CodeMirror editor.
 * @param  value
 * @return {Promise<*>}
 */
export async function setCodeMirrorValue(locator, value) {
	return await locator.evaluate((queryEditorElement, val) => {
		// Access the CodeMirror instance and get its value
		const codeMirrorInstance = queryEditorElement.CodeMirror;
		codeMirrorInstance.setValue(val);
	}, value);
}

/**
 * Types a GraphQL query into the CodeMirror editor.
 * @param {import('@playwright/test').Page} page  The Playwright page object.
 * @param {string}                          query The GraphQL query to type.
 */
export async function typeQuery(page, query) {
	const querySelector = '[aria-label="Query Editor"] .CodeMirror';
	await page.click(querySelector);
	await clearCodeMirror(page, querySelector);
	await page.keyboard.type(query);
}

/**
 * Types GraphQL variables into the CodeMirror editor.
 * @param {import('@playwright/test').Page} page      The Playwright page object.
 * @param {Object}                          variables The GraphQL variables to type (as an object).
 */
export async function typeVariables(page, variables) {
	const variablesString = JSON.stringify(variables, null, 2);
	// remove trailing curly brace. As users type, the IDE adds a trailing brace, so we're going to trim it
	// as a user wouldn't actually type an extra trailing brace
	const trimmedVariableString = variablesString.substring(0, -1);
	await page.click('[data-name="variables"]');
	const variablesSelector =
		'.graphiql-editor-tool[aria-label="Variables"]:not(.hidden)';
	await clearCodeMirror(page, variablesSelector);
	await page.keyboard.type(trimmedVariableString);
}

/**
 * Types GraphQL variables into the CodeMirror editor.
 * @param {import('@playwright/test').Page} page      The Playwright page object.
 * @param {string}                          variables The GraphQL variables to type (as a string).
 */
export async function pasteVariables(page, variables) {
	const trimmedVariableString = variablesString.substring(0, -1);

	// open the variable editor
	await page.click('[data-name="variables"]');
	// await clearCodeMirror(page, variablesSelector);

	// set the value on the CodeMirror editor
	const variablesSelector =
		'.graphiql-editor-tool[aria-label="Variables"]:not(.hidden) .cm-s-graphiql';
	const variableEditor = await page.locator(variablesSelector);
	const variableEditorInstance = variableEditor.CodeMirror;
	await variableEditorInstance.setValue(trimmedVariableString);
}

/**
 * Clears the content of a CodeMirror editor.
 * @param {import('@playwright/test').Page} page     The Playwright page object.
 * @param {string}                          selector The CSS selector for the CodeMirror editor.
 */
export async function clearCodeMirror(page, selector) {
	await page.click(selector);
	// Use the appropriate select all command based on the OS
	const selectAllCommand =
		process.platform === 'darwin' ? 'Meta+A' : 'Control+A';
	await page.keyboard.press(selectAllCommand); // Select all text
	await page.keyboard.press('Backspace'); // Clear the selection
}
export async function visitPublicFacingPage(page) {
	await page.goto(wpHomeUrl, { waitUntil: 'networkidle' });
}

export async function visitAdminFacingPage(page) {
	await page.goto(wpAdminUrl, { waitUntil: 'networkidle' });
}

export async function visitPluginsPage(page) {
	await page.goto(`${wpAdminUrl}/plugins.php`, {
		waitUntil: 'networkidle',
	});
}

export async function openDrawer(page) {
	// Wait for the IDE scripts to load and the IDE to be ready
	// The WPGraphQLIDEReady event is dispatched after the IDE is rendered
	await page.waitForFunction(() => {
		return window.WPGraphQLIDE !== undefined;
	}, { timeout: 10000 });

	// Wait for the drawer button to be available
	await page.waitForSelector('.AppDrawerButton', {
		state: 'visible',
		timeout: 10000,
	});

	const isDrawerVisible = await page
		.locator('.graphiql-container')
		.isVisible();
	if (!isDrawerVisible) {
		await clickDrawerButton(page);
		await page.waitForSelector('.graphiql-container', {
			state: 'visible',
			timeout: 10000,
		});
	}

	await page.waitForLoadState('networkidle');
}

export async function closeDrawer(page) {
	const isDrawerVisible = await page
		.locator('.graphiql-container')
		.isVisible();

	if (isDrawerVisible) {
		const overlay = await page.locator('[vaul-overlay]');
		if (overlay) {
			await overlay.click();
		}
		await expect(page.locator('.graphiql-container')).toBeHidden();
		await page.waitForSelector('.AppDrawerButton', {
			state: 'visible',
		});
		await clickDrawerCloseButton(page);
		await page.waitForSelector('.graphiql-container', {
			state: 'hidden',
		});
	}
}

export async function clickDrawerButton(page) {
	await page.click('.AppDrawerButton');
}

export async function clickDrawerCloseButton(page) {
	await page.click('.AppDrawerCloseButton');
}

export async function executeQuery(page) {
	await page.click('.graphiql-execute-button');
}

async function selectAndClearTextUsingKeyboard(page, selector) {
	await page.click(selector); // Focus the element

	// Determine the operating system to use the correct "Select All" shortcut
	const selectAllCommand =
		process.platform === 'darwin' ? 'Meta+A' : 'Control+A';
	await page.keyboard.press(selectAllCommand); // Select all text using OS-specific shortcut
	await page.keyboard.press('Backspace'); // Clear selected text
}

export async function simulateHeavyJSLoad(page) {
	await page.evaluate(() => {
		// Simulate heavy DOM manipulations
		for (let i = 0; i < 500; i++) {
			const div = document.createElement('div');
			div.textContent = `Heavy content ${i}`;
			div.style.backgroundColor =
				'#' + Math.floor(Math.random() * 16777215).toString(16);
			document.body.appendChild(div);
		}

		// Simulate heavy computations
		const heavyComputation = Array.from(
			{ length: 50000 },
			(_, i) => i ** 2
		).reduce((a, b) => a + b);
		console.log('Heavy computation result:', heavyComputation);

		// Simulate asynchronous operations
		new Promise((resolve) => setTimeout(resolve, 5000)).then(() =>
			console.log('Delayed operation completed')
		);

		// Simulate frequent DOM updates
		setInterval(() => {
			const randomDiv = document.querySelector(
				`div:nth-child(${Math.floor(Math.random() * 500) + 1})`
			);
			if (randomDiv) {
				randomDiv.style.backgroundColor =
					'#' + Math.floor(Math.random() * 16777215).toString(16);
			}
		}, 10);
	});
}

/**
 * Sets the value of the 'graphiql:query' key in local storage.
 * @param {import('@playwright/test').Page} page  The Playwright page object.
 * @param {string}                          value The value to set for the 'graphiql:query' key in local storage.
 * @return {Promise<void>}
 */
export async function setQueryInLocalStorage(page, value) {
	await page.evaluate((val) => {
		localStorage.setItem('graphiql:query', val);
	}, value);
}

/**
 * Retrieves the value of the 'graphiql:query' key from local storage.
 * @param {import('@playwright/test').Page} page The Playwright page object.
 * @return {Promise<string>} The value of the 'graphiql:query' key from local storage.
 */
export async function getQueryFromLocalStorage(page) {
	return await page.evaluate(() => {
		return localStorage.getItem('graphiql:query');
	});
}
