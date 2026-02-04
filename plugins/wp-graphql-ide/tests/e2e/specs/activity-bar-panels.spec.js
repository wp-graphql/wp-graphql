import { describe, test, beforeEach, expect } from '@playwright/test';
import { loginToWordPressAdmin, openDrawer, typeQuery } from '../utils';

// Hook to run before each test
beforeEach( async ( { page, context } ) => {
	// Log in to WordPress Admin
	await loginToWordPressAdmin( page );
	await openDrawer( page );
	// Grant clipboard permissions
	await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
} );

const selectors = {
	docsExplorerButton: '.graphiql-sidebar .graphiql-sidebar-section:nth-child(1) button:nth-child(1)',
	docsExplorerPanel: '.graphiql-plugin .graphiql-doc-explorer',
	docsExplorerSpinner: '.graphiql-doc-explorer-content .graphiql-spinner',
	historyPanelButton: '.graphiql-sidebar .graphiql-sidebar-section:nth-child(1) button:nth-child(2)',
	historyPanel: '.graphiql-plugin .graphiql-history',
	explorerButton: '.graphiql-sidebar .graphiql-sidebar-section:nth-child(1) button:nth-child(3)',
	explorerPanel: '.graphiql-plugin .docExplorerWrap',
	helpButton: '.graphiql-sidebar .graphiql-sidebar-section:nth-child(1) button:nth-child(4)',
	helpPanel: '.graphiql-plugin .wpgraphql-ide-help-panel',
	refetchButton: '.graphiql-sidebar .graphiql-sidebar-section:nth-child(2) button:nth-child(1)',
	executeQueryButton: '.graphiql-execute-button',
};

describe( 'Activity Bar Panels', () => {
	describe( 'Docs Explorer', () => {
		beforeEach( async ( { page } ) => {
			// Assert that the Docs Explorer is not visible initially
			await expect(
				page.locator( selectors.docsExplorerPanel )
			).not.toBeVisible();
			await page.click( selectors.docsExplorerButton );
		} );

		test( 'should be visible when activated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect(
				page.locator( selectors.docsExplorerPanel )
			).toBeVisible();
		} );

		test( 'spinner should show when schema is loading', async ( {
			page,
		} ) => {
			await page.click( selectors.refetchButton );
			await expect(
				page.locator( selectors.docsExplorerSpinner )
			).toBeVisible();
		} );

		test( 'should be hidden when deactivated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect(
				page.locator( selectors.docsExplorerPanel )
			).toBeVisible();

			// Click the Docs Explorer button
			await page.click( selectors.docsExplorerButton );

			// Assert that the Docs Explorer is now hidden
			await expect(
				page.locator( selectors.docsExplorerPanel )
			).not.toBeVisible();
		} );
	} );

	describe( 'History Panel', () => {
		beforeEach( async ( { page } ) => {
			// Assert that the Docs Explorer is not visible initially
			await expect(
				page.locator( selectors.historyPanel )
			).not.toBeVisible();
			await page.click( selectors.historyPanelButton );
		} );

		test( 'should be visible when activated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect(
				page.locator( selectors.historyPanel )
			).toBeVisible();
		} );

		test( 'query should show in history panel', async ( { page } ) => {
			await expect(
				page.locator( '.graphiql-history-item' )
			).not.toBeVisible();
			await typeQuery( page, 'query { posts { nodes { id } } }' );
			await page.click( selectors.executeQueryButton );
			await expect(
				page.locator( '.graphiql-history-item' ),
				'history item shows after query is executed'
			).toBeVisible();
		} );

		test( 'should be hidden when deactivated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect(
				page.locator( selectors.historyPanel )
			).toBeVisible();

			// Click the Docs Explorer button
			await page.click( selectors.historyPanelButton );

			// Assert that the Docs Explorer is now hidden
			await expect(
				page.locator( selectors.historyPanel )
			).not.toBeVisible();
		} );
	} );

	describe( 'Explorer Panel', () => {
		beforeEach( async ( { page } ) => {
			// Assert that the Docs Explorer is not visible initially
			await expect(
				page.locator( selectors.explorerPanel )
			).not.toBeVisible();
			await page.click( selectors.explorerButton );
		} );
		test( 'should be visible when activated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect(
				page.locator( selectors.explorerPanel )
			).toBeVisible();
		} );
		test( 'should be hidden when deactivated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect(
				page.locator( selectors.explorerPanel )
			).toBeVisible();
			// Click the Docs Explorer button
			await page.click( selectors.explorerButton );
			// Assert that the Docs Explorer is now hidden
			await expect(
				page.locator( selectors.explorerPanel )
			).not.toBeVisible();
		} );
	} );

	describe( 'Help Panel', () => {
		beforeEach( async ( { page } ) => {
			// Assert that the Docs Explorer is not visible initially
			await expect(
				page.locator( selectors.helpPanel )
			).not.toBeVisible();
			await page.click( selectors.helpButton );
		} );
		test( 'should be visible when activated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect( page.locator( selectors.helpPanel ) ).toBeVisible();
		} );
		test( 'should be hidden when deactivated', async ( { page } ) => {
			// Assert that the Docs Explorer is now visible
			await expect( page.locator( selectors.helpPanel ) ).toBeVisible();
			// Click the Docs Explorer button
			await page.click( selectors.helpButton );
			// Assert that the Docs Explorer is now hidden
			await expect(
				page.locator( selectors.helpPanel )
			).not.toBeVisible();
		} );
	} );
} );
