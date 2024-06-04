const defaults = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaults,
	entry: {
		index: path.resolve( process.cwd(), 'src', 'index.js' ),
		render: path.resolve( process.cwd(), 'src', 'render.js' ),
		graphql: path.resolve( process.cwd(), 'src', 'graphql.js' ),
	},
	externals: {
		react: 'React',
		'react-dom': 'ReactDOM',
		graphql: 'graphql',
	},
};
