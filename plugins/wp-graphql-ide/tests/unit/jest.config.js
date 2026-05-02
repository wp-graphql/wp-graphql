module.exports = {
	testEnvironment: 'jsdom',
	testMatch: ['**/tests/unit/specs/**/*.js'],
	transform: {
		'^.+\\.[t|j]sx?$': 'babel-jest',
	},
	transformIgnorePatterns: [
		'node_modules/(?!(cm6-graphql|graphql-language-service|vscode-languageserver-types|@codemirror|@lezer|codemirror|@codemirror/lang-json)/)',
	],
};
