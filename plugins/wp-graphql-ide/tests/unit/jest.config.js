module.exports = {
	testEnvironment: 'jsdom',
	testMatch: ['**/tests/unit/specs/**/*.js'],
	transform: {
		'^.+\\.[t|j]sx?$': 'babel-jest',
	},
	transformIgnorePatterns: [
		// `@codemirror` covers `@codemirror/lang-json` and the rest of
		// the family — listing the child explicitly is redundant.
		'node_modules/(?!(cm6-graphql|graphql-language-service|vscode-languageserver-types|@codemirror|@lezer|codemirror)/)',
	],
};
