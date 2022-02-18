import { clearLocalStorage } from '@wordpress/e2e-test-utils'
import { wait, loadGraphiQL, executeQuery, setVariables, setQuery } from './helpers'

describe('Graphiql', function () {

    // load graphiql and set a basic query to execute
    beforeEach(async() => {
        // Clear all values stored to local storage
        clearLocalStorage();

    })

    // query should be able to be set by a user and executed
    it('executes query', async() => {

        await loadGraphiQL();
        await setQuery('{posts{nodes{id}}}');
        await setVariables({ first: 10 });
        await wait( 1000 );
        await executeQuery();
        await wait( 1000 );

        // Ensure there are nodes in the response. we don't care if it's empty or not
        // as we've not created any posts yet, but we do want to make sure we get a response
        // matching the shape of the request
        const postsInResponse = await page.$x("//div[contains(text(), 'posts')]");
        expect( postsInResponse.length === 1 );

        const nodesInResponse = await page.$x("//div[contains(text(), 'nodes')]");
        expect( nodesInResponse.length === 1 );

    })

    // Query should execute without errors
    it ( 'has no errors', async () => {

        await loadGraphiQL();
        await setQuery('{posts{nodes{id}}}');
        await setVariables({ first: 10 });
        await wait( 1000 );
        await executeQuery();
        await wait( 1000 );
        const nodesInResponse = await page.$x("//span[contains(text(), 'errors')]");
        expect( nodesInResponse.length === 0 );

    })

    // query should return errors when expected
    it ( 'renders errors when errors are expected', async () => {

        await loadGraphiQL();
        await setQuery('{nonExistantFieldThatShouldError}');
        await setVariables({ first: 10 });
        await wait( 1000 );
        await executeQuery();
        await wait( 1000 );
        const nodesInResponse = await page.$x("//span[contains(text(), 'errors')]");
        expect( nodesInResponse.length === 1 );

    })

    // Graphiql should load with custom query from queryParams loaded
    it ( 'loads with custom query from url query params', async() => {

        await loadGraphiQL({ query: 'query TestFromUri { posts { nodes { id } } }' } );
        await wait(3000 );

        // Ensure the operation name is in the code mirror query editor
        const operationNameInEditor = await page.$x("//span[contains(text(), 'TestFromUri')]");
        expect( operationNameInEditor.length === 1 );
        expect( operationNameInEditor[0]._remoteObject.description === 'span.cm-def' );

        // Ensure the operation name is in the code mirror query editor
        const postsInEditor = await page.$x("//span[contains(text(), 'posts')]");
        expect( postsInEditor.length === 1 );
        expect( postsInEditor[0]._remoteObject.description === 'span.cm-def' );

        const nodesInEditor = await page.$x("//span[contains(text(), 'nodes')]");
        expect( nodesInEditor.length === 1 );
        expect( nodesInEditor[0]._remoteObject.description === 'span.cm-def' );

    })

    // GraphiQL should load with the code explorer open
    it ( 'loads with the explorer hidden by default', async() => {

        await loadGraphiQL({ query: 'query TestFromUri { posts { nodes { id } } }' } );
        await wait(3000 );

        // check to see if the docExplorer is hidden
        const style = await page.evaluate( async () => {
            const docExplorerWrap = document.querySelector('.docExplorerWrap');

            return window.getComputedStyle(docExplorerWrap);

        });

        expect( style.display === 'none' )

    })

    it ( 'opens explorer on click', async() => {

        await loadGraphiQL({ query: 'query TestFromUri { posts { nodes { id } } }' } );
        await wait(3000 );

        const style = page.evaluate( async () => {
            const docExplorerWrap = document.querySelector('.docExplorerWrap');
            return window.getComputedStyle(docExplorerWrap);
        });

        expect( style?.display === 'none' )

        const [button] = await page.$x("//button[contains(text(), 'Explorer')]");
        await button.click();
        await wait( 1000 );


        const newStyle = await page.evaluate( async () => {
            const docExplorerWrap = document.querySelector('.docExplorerWrap');
            return window.getComputedStyle(docExplorerWrap);

        });

        expect( newStyle?.display !== 'none' )

        // click the button again to toggle the explorer closed
        await button.click();
        await wait( 1000 );


        const closedStyle = await page.evaluate( async () => {
            const docExplorerWrap = document.querySelector('.docExplorerWrap');
            return window.getComputedStyle(docExplorerWrap);

        });

        expect( closedStyle?.display === 'none' )

    })
    it ('loads with explorer open if queryParam says to', async() => {
        await loadGraphiQL({ explorerIsOpen: true } );
        await wait( 1000 );
        const style = await page.evaluate( async () => {
            const docExplorerWrap = document.querySelector('.docExplorerWrap');
            return window.getComputedStyle(docExplorerWrap);

        });

        expect( style?.display !== 'none' )
    })

    it( 'loads with the documentation explorer closed', async () => {
        await loadGraphiQL();
        await wait( 1000 );
        const documentationExplorer = await page.$x("//div[contains(@class, 'doc-explorer')]") ?? [];
        expect( documentationExplorer.length === 0 );
    })

    it ('documentation explorer can be toggled open and closed', async () => {

        await loadGraphiQL();
        await wait( 1000 );
        const documentationExplorer = await page.$x("//div[contains(@class, 'doc-explorer')]") ?? [];
        expect( documentationExplorer.length === 0 );

        const [button] = await page.$x("//button[contains(@class, 'docExplorerShow')]") ?? [];

        if ( button.length ) {
            await button.click();
            await wait(1000);
            const documentationExplorerShowing = await page.$x("//div[contains(@class, 'doc-explorer')]") ?? [];
            expect(documentationExplorerShowing.length === 1);

            await wait( 1000 );
            const [closeButton] = await page.$x("//button[contains(@class, 'docExplorerHide')]") ?? [];

            // make sure the doc explorer is gone after closing it
            if ( closeButton.length ) {
                await closeButton.click();
                await wait(1000);
                const documentationExplorerShowing = await page.$x("//div[contains(@class, 'doc-explorer')]") ?? [];
                expect(documentationExplorerShowing.length === 0);
            }

        }


    })

})
