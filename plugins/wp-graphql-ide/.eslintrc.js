/**
 * ESLint config for wp-graphql-ide. Extends @wordpress/scripts and relaxes
 * rules for existing code (console, JSDoc, browser globals, activeElement, etc.).
 */
const wpScriptsConfig = require('@wordpress/scripts/config/.eslintrc.js');

module.exports = {
	root: true,
	...wpScriptsConfig,
	overrides: [
		...(wpScriptsConfig.overrides || []),
		{
			files: ['src/**/*.js', 'src/**/*.jsx'],
			globals: {
				MutationObserver: 'readonly',
			},
			rules: {
				'no-console': 'off',
				'jsdoc/require-returns-description': 'off',
				'@wordpress/no-global-active-element': 'off',
			},
		},
		{
			files: ['bin/**/*.js', 'webpack.config.js'],
			rules: {
				'no-console': 'off',
				'import/no-extraneous-dependencies': 'off',
			},
		},
		{
			files: ['plugins/**/src/**/*.js', 'plugins/**/src/**/*.jsx'],
			globals: {
				HTMLTextAreaElement: 'readonly',
			},
			rules: {
				'no-console': 'off',
				'import/no-extraneous-dependencies': 'off',
				'jsx-a11y/click-events-have-key-events': 'off',
				'jsx-a11y/no-static-element-interactions': 'off',
				'@wordpress/no-global-active-element': 'off',
				'no-unused-vars': [
					'error',
					{ argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
				],
			},
		},
		{
			files: ['tests-examples/**/*.js'],
			env: { node: true, browser: true },
			globals: {
				localStorage: 'readonly',
			},
		},
		{
			files: ['tests/e2e/**/*.js'],
			env: { node: true, browser: true },
			globals: {
				expect: 'readonly',
				localStorage: 'readonly',
				navigator: 'readonly',
			},
			rules: {
				'no-console': 'off',
				'no-duplicate-imports': 'off',
				'no-unused-vars': [
					'error',
					{ argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
				],
				'import/no-extraneous-dependencies': 'off',
				'jsdoc/check-param-names': 'off',
				'jsdoc/require-param-type': 'off',
				'jsdoc/require-returns-description': 'off',
			},
		},
		{
			files: ['tests/unit/**/*.js'],
			rules: {
				'jest/expect-expect': 'off',
				'jest/no-disabled-tests': 'off',
			},
		},
	],
};
