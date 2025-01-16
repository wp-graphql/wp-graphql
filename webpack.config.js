const defaults = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );

// The plugin configuration overrides.
const plugins = [
    new RemoveEmptyScriptsPlugin( {
        stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
    } ),
];

module.exports = {
    ...defaults,
    entry: {
        index: path.resolve( process.cwd(), 'packages/wpgraphiql', 'index.js' ),
        app: path.resolve( process.cwd(), 'packages/wpgraphiql', 'app.js' ),
        graphiqlQueryComposer: path.resolve(
            process.cwd(),
            'packages/graphiql-query-composer',
            'index.js'
        ),
        graphiqlAuthSwitch: path.resolve(
            process.cwd(),
            'packages/graphiql-auth-switch',
            'index.js'
        ),
        graphiqlFullscreenToggle: path.resolve(
            process.cwd(),
            'packages/graphiql-fullscreen-toggle',
            'index.js'
        ),
        extensions: path.resolve(
            process.cwd(),
            "packages/extensions",
            "index.js"
        ),
        updates: path.resolve(
            process.cwd(),
            'packages/updates',
            'index.scss'
        ),
    },
    plugins: [
        ...defaults.plugins.filter(
            ( plugin ) =>
                ! plugins.some(
                    ( p ) => p.constructor.name === plugin.constructor.name
                )
        ),
        ...plugins,
    ],
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
        wpGraphiQL: 'wpGraphiQL',
        graphql: 'wpGraphiQL.GraphQL',
    },
};
