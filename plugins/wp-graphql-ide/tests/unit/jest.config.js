const path = require('path');

// `@wordpress/components` nests its own `@wordpress/element` -> `react`
// copy; rendering with the root `react-dom` against that second React
// crashes hooks ("Cannot read properties of null (reading 'useContext')").
// Map every react/react-dom specifier to the single hoisted copy.
const reactDir = path.dirname(require.resolve('react/package.json'));
const reactDomDir = path.dirname(require.resolve('react-dom/package.json'));

module.exports = {
	testEnvironment: 'jsdom',
	testMatch: ['**/tests/unit/specs/**/*.js'],
	moduleNameMapper: {
		'^react$': path.join(reactDir, 'index.js'),
		'^react/(.*)$': `${reactDir}/$1`,
		'^react-dom$': path.join(reactDomDir, 'index.js'),
		'^react-dom/(.*)$': `${reactDomDir}/$1`,
	},
	transform: {
		'^.+\\.[t|j]sx?$': 'babel-jest',
	},
	transformIgnorePatterns: [
		// `@codemirror` covers `@codemirror/lang-json` and the rest of
		// the family — listing the child explicitly is redundant.
		// `uuid` ships ESM-only and is dragged in by `@wordpress/components`,
		// so it needs the babel transform too.
		'node_modules/(?!(cm6-graphql|graphql-language-service|vscode-languageserver-types|@codemirror|@lezer|codemirror|uuid)/)',
	],
};
