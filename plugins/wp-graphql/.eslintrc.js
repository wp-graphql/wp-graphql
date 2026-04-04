/**
 * ESLint config for wp-graphql. Extends @wordpress/scripts and adds globals/rules
 * for the GraphiQL packages (runtime-injected globals, relaxed JSDoc to match existing code).
 */
const wpScriptsConfig = require('@wordpress/scripts/config/.eslintrc.js');

module.exports = {
	root: true,
	...wpScriptsConfig,
	overrides: [
		...(wpScriptsConfig.overrides || []),
		{
			files: ['packages/**/*.js', 'packages/**/*.jsx'],
			globals: {
				history: 'readonly',
				wpGraphiQL: 'readonly',
				wpGraphiQLSettings: 'readonly',
				wpgraphqlExtensions: 'readonly',
			},
			rules: {
				'array-callback-return': 'off',
				'import/no-extraneous-dependencies': 'off',
				'jest/expect-expect': 'off',
				'jest/no-conditional-expect': 'off',
				'jsx-a11y/click-events-have-key-events': 'off',
				'jsx-a11y/no-static-element-interactions': 'off',
				'jsdoc/check-alignment': 'off',
				'jsdoc/check-line-alignment': 'off',
				'jsdoc/check-param-names': 'off',
				'jsdoc/check-tag-names': 'off',
				'jsdoc/check-tag': 'off',
				'jsdoc/multiline-blocks': 'off',
				'jsdoc/no-multi-asterisks': 'off',
				'jsdoc/require-param': 'off',
				'jsdoc/require-param-type': 'off',
				'jsdoc/require-returns': 'off',
				'jsdoc/require-returns-description': 'off',
				'jsdoc/require-returns-type': 'off',
				'no-nested-ternary': 'off',
				'no-shadow': 'off',
				'no-unused-expressions': 'off',
				'no-unused-vars': 'off',
				eqeqeq: 'off',
				'react/jsx-key': 'off',
				'react/no-unescaped-entities': 'off',
				camelcase: 'off',
				'no-console': 'off',
			},
		},
		{
			files: ['tests/e2e/**/*.js'],
			env: { node: true, browser: true },
			globals: {
				expect: 'readonly',
				localStorage: 'readonly',
			},
			rules: {
				'import/no-extraneous-dependencies': 'off',
				'no-unused-vars': [
					'error',
					{ argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
				],
			},
		},
	],
};
