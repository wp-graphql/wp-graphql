import { visitAdminPage } from '@wordpress/e2e-test-utils'

export const wait = async ( time = 5000 ) => {
    await new Promise(resolve => setTimeout(resolve, time));
}

/**
 * Load the GraphiQL IDE. Optionally pass queryParams to load the page with
 * @param opts
 * @returns {Promise<void>}
 */
export const loadGraphiQL = async ( queryParams = { query: null, variables: null, explorerIsOpen: null }) => {

    // get the variables and query out of the queryParams to load the page with
    const {
        query = null,
        variables = null,
        explorerIsOpen
    } = queryParams;

    let _queryParams = '';

    if ( query ) {
        _queryParams += `&query=${encodeURIComponent(query)}`
    }

    if ( variables ) {
        _queryParams += `&variables=${encodeURIComponent(JSON.stringify(variables))}`
    }

    _queryParams += `&explorerIsOpen=${explorerIsOpen ? "1" : "false" }`

    await Promise.all([
        visitAdminPage('admin.php', `?page=graphiql-ide${_queryParams}` ),
        page.waitForSelector('#graphiql .graphiql-container', { visible: true, timeout: 5000 }).catch( error => {
            console.log( `failed to wait for graphiql...`)
        })
    ]);


}

/**
 * Set the value of the GraphiQL Query Editor
 *
 * Must be called within a page.evaluate call
 *
 * @param query
 * @returns {Promise<void>}
 */
export const setQuery = async ( query ) => {

    return await page.evaluate( async (query) => {
        const _queryEditor = await document.querySelector('.query-editor .cm-s-graphiql').CodeMirror;
        return await _queryEditor ? _queryEditor.setValue( query ) : null;
    }, query );
}

/**
 * Set the value of the variable editor.
 *
 * Must be called within a page.evaluate call
 *
 * @param variables
 * @returns {Promise<void>}
 */
export const setVariables = async ( variables = {} ) => {

    return await page.evaluate( async (variables) => {
        let _variableEditor = await document.querySelector('.variable-editor .cm-s-graphiql').CodeMirror;
        return await _variableEditor ? _variableEditor.setValue(JSON.stringify(variables, null, 2)) : null;
    }, variables )

}

/**
 * Click the "execute" button
 *
 * @returns {Promise<void>}
 */
export const executeQuery = async () => {
    await page.click( '.execute-button' );
}

