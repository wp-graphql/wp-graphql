import React from 'react';

import GraphiQL from "graphiql"
import GraphiQLExplorer from "graphiql-explorer"
import CodeExporter from "graphiql-code-exporter"
import {getIntrospectionQuery, buildClientSchema, parse, print} from "graphql"
import "whatwg-fetch"
import snippets from "./snippets"

/**
 * Style the app
 */
import 'graphiql/graphiql.css';
import './app.css';
import "graphiql-code-exporter/CodeExporter.css"

const parameters = {}

window.location.search
    .substr(1)
    .split(`&`)
    .forEach(function (entry) {
        var eq = entry.indexOf(`=`)
        if (eq >= 0) {
            parameters[decodeURIComponent(entry.slice(0, eq))] = decodeURIComponent(entry.slice(eq + 1).replace(/\+/g, '%20'))
        }
    })

// Produce a Location query string from a parameter object.
function locationQuery(params) {
    return (
        'admin.php' +
        '?' +
        Object.keys(params)
            .map(function (key) {
                return encodeURIComponent(key) + `=` + encodeURIComponent(params[key])
            })
            .join(`&`)
    )
}

// Derive a fetch URL from the current URL, sans the GraphQL parameters.
const graphqlParamNames = {
    query: true,
    variables: true,
    operationName: true,
    explorerIsOpen: true,
}

const otherParams = {}

for (var k in parameters) {
    if (parameters.hasOwnProperty(k) && graphqlParamNames[k] !== true) {
        otherParams[k] = parameters[k]
    }
}

let nonce = (window.wpGraphiQLSettings && window.wpGraphiQLSettings.nonce) ? window.wpGraphiQLSettings.nonce : null;
let endpoint = (window.wpGraphiQLSettings && window.wpGraphiQLSettings.graphqlEndpoint) ? window.wpGraphiQLSettings.graphqlEndpoint : window.location.origin;


function graphQLFetcher(graphQLParams) {
    return fetch(endpoint, {
        method: `post`,
        headers: {
            Accept: `application/json`,
            "Content-Type": `application/json`,
            'X-WP-Nonce': nonce
        },
        body: JSON.stringify(graphQLParams),
        credentials: `include`,
    }).then(function (response) {
        return response.json()
    })
}

// When the query and variables string is edited, update the URL bar so
// that it can be easily shared.
function onEditVariables(newVariables) {
    parameters.variables = newVariables
    updateURL()
}

function onEditOperationName(newOperationName) {
    parameters.operationName = newOperationName
    updateURL()
}

function updateURL() {
    // eslint-disable-next-line
    history.replaceState(null, null, locationQuery(parameters))
}

// We control query, so we need to recreate initial query text that show up
// on visiting graphiql - in order it will be
//  - query from query string (if set)
//  - query stored in localStorage (which graphiql set when closing window)
//  - default empty query
const DEFAULT_QUERY =
    parameters.query && print( parse( parameters.query ) ) ||
    (window.localStorage && window.localStorage.getItem(`graphiql:query`)) ||
    null

const QUERY_EXAMPLE_SITEMETADATA_TITLE = `#     {
#       generalSettings {
#         url
#         title
#       }
#     }`

const QUERY_EXAMPLE_FALLBACK = `#     {
#       posts {
#         nodes {
#           title
#           uri
#         }
#       }
#     }`

function generateDefaultFallbackQuery(queryExample) {
    return `# Welcome to GraphiQL
#
# GraphiQL is an in-browser tool for writing, validating, and
# testing GraphQL queries.
#
# Type queries into this side of the screen, and you will see intelligent
# typeaheads aware of the current GraphQL type schema and live syntax and
# validation errors highlighted within the text.
#
# GraphQL queries typically start with a "{" character. Lines that starts
# with a # are ignored.
#
# An example GraphQL query might look like:
#
${queryExample}
#
# Keyboard shortcuts:
#
#  Prettify Query:  Shift-Ctrl-P (or press the prettify button above)
#
#     Merge Query:  Shift-Ctrl-M (or press the merge button above)
#
#       Run Query:  Ctrl-Enter (or press the play button above)
#
#   Auto Complete:  Ctrl-Space (or just start typing)
#
`
}

const storedExplorerPaneState =
    typeof parameters.explorerIsOpen !== `undefined`
        ? parameters.explorerIsOpen === `false`
        ? false
        : true
        : window.localStorage
        ? window.localStorage.getItem(`graphiql:graphiqlExplorerOpen`) !== `false`
        : true

const storedCodeExporterPaneState =
    typeof parameters.codeExporterIsOpen !== `undefined`
        ? parameters.codeExporterIsOpen === `false`
        ? false
        : true
        : window.localStorage
        ? window.localStorage.getItem(`graphiql:graphiqlCodeExporterOpen`) ===
        `true`
        : false

class App extends React.Component {
    state = {
        schema: null,
        query: DEFAULT_QUERY,
        explorerIsOpen: storedExplorerPaneState,
        codeExporterIsOpen: storedCodeExporterPaneState,
    }

    componentDidMount() {
        graphQLFetcher({
            query: getIntrospectionQuery(),
        }).then(result => {
            const newState = {schema: buildClientSchema(result.data)}

            if (this.state.query === null) {
                try {
                    const siteMetadataType = result.data.__schema.types.find(
                        type => type.name === `SiteSiteMetadata` && type.kind === `OBJECT`
                    )
                    if (siteMetadataType) {
                        const titleField = siteMetadataType.fields.find(
                            field =>
                                field.name === `title` &&
                                field.type &&
                                field.type.kind === `SCALAR` &&
                                field.type.name === `String`
                        )

                        if (titleField) {
                            newState.query = generateDefaultFallbackQuery(
                                QUERY_EXAMPLE_SITEMETADATA_TITLE
                            )
                        }
                    }
                    // eslint-disable-next-line no-empty
                } catch (e) {
                    console.error(e)
                }
                if (!newState.query) {
                    newState.query = generateDefaultFallbackQuery(QUERY_EXAMPLE_FALLBACK)
                }
            }

            this.setState(newState)
        })

        const editor = this._graphiql.getQueryEditor()
        editor.setOption(`extraKeys`, {
            ...(editor.options.extraKeys || {}),
            "Shift-Alt-LeftClick": this._handleInspectOperation,
        })
    }

    _handleInspectOperation = (cm, mousePos) => {
        const parsedQuery = parse(this.state.query || ``)

        if (!parsedQuery) {
            console.error(`Couldn't parse query document`)
            return null
        }

        const token = cm.getTokenAt(mousePos)
        const start = {line: mousePos.line, ch: token.start}
        const end = {line: mousePos.line, ch: token.end}
        const relevantMousePos = {
            start: cm.indexFromPos(start),
            end: cm.indexFromPos(end),
        }

        const position = relevantMousePos

        const def = parsedQuery.definitions.find(definition => {
            if (!definition.loc) {
                console.log(`Missing location information for definition`)
                return false
            }

            const {start, end} = definition.loc
            return start <= position.start && end >= position.end
        })

        if (!def) {
            console.error(`Unable to find definition corresponding to mouse position`)
            return null
        }

        const operationKind =
            def.kind === `OperationDefinition`
                ? def.operation
                : def.kind === `FragmentDefinition`
                ? `fragment`
                : `unknown`

        const operationName =
            def.kind === `OperationDefinition` && !!def.name
                ? def.name.value
                : def.kind === `FragmentDefinition` && !!def.name
                ? def.name.value
                : `unknown`

        const selector = `.graphiql-explorer-root #${operationKind}-${operationName}`

        const el = document.querySelector(selector)
        if (el) {
            el.scrollIntoView()
            return true
        }

        return false
    }

    _handleEditQuery = query => {
        parameters.query = query
        updateURL()
        this.setState({query})
    }

    _handleToggleExplorer = () => {
        const newExplorerIsOpen = !this.state.explorerIsOpen
        if (window.localStorage) {
            window.localStorage.setItem(
                `graphiql:graphiqlExplorerOpen`,
                newExplorerIsOpen
            )
        }
        parameters.explorerIsOpen = newExplorerIsOpen
        updateURL()
        this.setState({explorerIsOpen: newExplorerIsOpen})
    }

    _handleToggleExporter = () => {
        const newCodeExporterIsOpen = !this.state.codeExporterIsOpen
        if (window.localStorage) {
            window.localStorage.setItem(
                `graphiql:graphiqlCodeExporterOpen`,
                newCodeExporterIsOpen
            )
        }
        parameters.codeExporterIsOpen = newCodeExporterIsOpen
        updateURL()
        this.setState({ codeExporterIsOpen: newCodeExporterIsOpen })
    }



    render() {
        const { query, schema, codeExporterIsOpen } = this.state
        const codeExporter = codeExporterIsOpen ? (
            <CodeExporter
                hideCodeExporter={this._handleToggleExporter}
                snippets={snippets}
                query={query}
                codeMirrorTheme="default"
            />
        ) : null

        return (
            <React.Fragment>
                <GraphiQLExplorer
                    schema={schema}
                    query={query}
                    onEdit={this._handleEditQuery}
                    explorerIsOpen={this.state.explorerIsOpen}
                    onToggleExplorer={this._handleToggleExplorer}
                    onRunOperation={operationName =>
                        this._graphiql.handleRunQuery(operationName)
                    }
                />
                <GraphiQL
                    ref={ref => (this._graphiql = ref)}
                    fetcher={graphQLFetcher}
                    schema={schema}
                    query={query}
                    onEditQuery={this._handleEditQuery}
                    onEditVariables={onEditVariables}
                    onEditOperationName={onEditOperationName}
                >
                    <GraphiQL.Toolbar>
                        <GraphiQL.Button
                            onClick={() => this._graphiql.handlePrettifyQuery()}
                            label="Prettify"
                            title="Prettify Query (Shift-Ctrl-P)"
                        />
                        <GraphiQL.Button
                            onClick={() => this._graphiql.handleToggleHistory()}
                            label="History"
                            title="Show History"
                        />
                        <GraphiQL.Button
                            onClick={this._handleToggleExplorer}
                            label="Explorer"
                            title="Toggle Explorer"
                        />
                        <GraphiQL.Button
                            onClick={this._handleToggleExporter}
                            label="Code Exporter"
                            title="Toggle Code Exporter"
                        />
                    </GraphiQL.Toolbar>
                </GraphiQL>
                {codeExporter}
            </React.Fragment>
        );
    }
}

export default App;
