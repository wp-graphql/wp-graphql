/**
 * ESLint flat config for wp-graphql-acf (admin JS + E2E tests).
 *
 * Migrated from .eslintrc.cjs for ESLint v9+ (the legacy .eslintrc / .eslintignore
 * formats were removed). Uses eslint:recommended only, to avoid optional plugin deps.
 */
const js = require( '@eslint/js' );
const globals = require( 'globals' );

module.exports = [
	// Replaces the old .eslintignore.
	{
		ignores: [
			'tests/_output/**',
			'build/**',
			'coverage/**',
			'node_modules/**',
			'vendor/**',
		],
	},

	js.configs.recommended,

	// Defaults for all linted files (mirrors the old root `env: { node, es2021 }`).
	{
		languageOptions: {
			ecmaVersion: 2021,
			sourceType: 'script',
			globals: {
				...globals.node,
				...globals.es2021,
			},
		},
	},

	// Admin/browser source.
	{
		files: [ 'src/**/*.js' ],
		languageOptions: {
			globals: {
				...globals.browser,
				$j: 'writable',
				acf: 'readonly',
				ajaxurl: 'readonly',
				jQuery: 'readonly',
				wp_graphql_acf: 'readonly',
			},
		},
		rules: {
			'no-redeclare': 'off',
			'no-unused-vars': [
				'error',
				{ argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
			],
			'no-var': 'warn',
		},
	},

	// Playwright E2E tests (ES modules).
	{
		files: [ 'tests/e2e/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				jQuery: 'readonly',
			},
		},
	},
];
