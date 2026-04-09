const defaults = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

/**
 * Resolve a single physical copy of @graphiql/react for the whole app bundle.
 *
 * The `graphiql` npm package nests its own `@graphiql/react` peer, while our
 * `GraphiQLToolbar` imports hooks from `@graphiql/react` at the workspace root.
 * Without deduping, webpack bundles two copies → React context from
 * `EditorContextProvider` (graphiql's copy) does not match hooks (root copy), e.g.
 * "Tried to use usePrettifyEditors without the necessary context".
 */
function resolveGraphiqlReactDir() {
	const graphiqlDir = path.dirname(require.resolve('graphiql/package.json'));
	try {
		return path.dirname(
			require.resolve('@graphiql/react/package.json', {
				paths: [graphiqlDir],
			})
		);
	} catch {
		return path.dirname(require.resolve('@graphiql/react/package.json'));
	}
}

const graphiqlReactDir = resolveGraphiqlReactDir();

// The plugin configuration overrides.
const plugins = [
	new RemoveEmptyScriptsPlugin({
		stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
	}),
];

module.exports = {
	...defaults,
	resolve: {
		...defaults.resolve,
		alias: {
			...defaults.resolve?.alias,
			'@graphiql/react': graphiqlReactDir,
		},
	},
	entry: {
		index: path.resolve(process.cwd(), 'packages/wpgraphiql', 'index.js'),
		app: path.resolve(process.cwd(), 'packages/wpgraphiql', 'app.js'),
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
			'packages/extensions',
			'index.js'
		),
		updates: path.resolve(process.cwd(), 'packages/updates', 'index.scss'),
	},
	plugins: [
		...defaults.plugins.filter(
			(plugin) =>
				!plugins.some(
					(p) => p.constructor.name === plugin.constructor.name
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
