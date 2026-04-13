const defaults = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const glob = require('glob');

// Define the path to the plugins directory
const pluginsPath = path.resolve(__dirname, 'plugins');

// Use glob to find all plugin directories
const plugins = glob.sync(`${pluginsPath}/*`);

// Define entry points for the main app and plugins
const mainAppEntry = {
	'wpgraphql-ide': path.resolve(process.cwd(), 'src', 'wpgraphql-ide.js'),
	'wpgraphql-ide-render': path.resolve(
		process.cwd(),
		'src',
		'wpgraphql-ide-render.js'
	),
	graphql: path.resolve(process.cwd(), 'src', 'graphql.js'),
};

// Reduce plugins to an entry object and an output object
const { pluginsEntry, pluginsOutput } = plugins.reduce(
	(result, plugin) => {
		const pluginName = path.basename(plugin);
		result.pluginsEntry[pluginName] = path.resolve(
			plugin,
			'src',
			`${pluginName}.js`
		);
		result.pluginsOutput[pluginName] = path.resolve(plugin, 'build');
		return result;
	},
	{ pluginsEntry: {}, pluginsOutput: {} }
);

// Generate Webpack configurations for each plugin
const pluginConfigs = Object.entries(pluginsEntry).map(([name, entry]) => ({
	...defaults,
	entry: {
		[name]: entry,
	},
	externals: {
		react: 'React',
		'react-dom': 'ReactDOM',
		graphql: 'graphql',
	},
	output: {
		path: pluginsOutput[name],
		filename: `${name}.js`,
	},
}));

// Export an array of Webpack configurations, one for the main app and one for each plugin
module.exports = [
	{
		...defaults,
		entry: mainAppEntry,
		externals: {
			react: 'React',
			'react-dom': 'ReactDOM',
			graphql: 'graphql',
		},
		output: {
			path: path.resolve(__dirname, 'build'),
			filename: '[name].js',
		},
	},
	// Configurations for each plugin
	...pluginConfigs,
];
