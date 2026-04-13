/** ESLint config for wp-graphql-acf (admin JS + E2E tests). Uses eslint:recommended only to avoid optional deps. */
module.exports = {
	root: true,
	env: { node: true, es2021: true },
	extends: [ 'eslint:recommended' ],
	overrides: [
		{
			files: [ 'src/**/*.js' ],
			env: { browser: true },
			globals: {
				$j: 'writable',
				acf: 'readonly',
				ajaxurl: 'readonly',
				jQuery: 'readonly',
				wp_graphql_acf: 'readonly',
			},
			rules: {
				'no-redeclare': 'off',
				'no-unused-vars': [ 'error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' } ],
				'no-var': 'warn',
			},
		},
		{
			files: [ 'tests/e2e/**/*.js' ],
			parserOptions: { ecmaVersion: 2022, sourceType: 'module' },
			globals: {
				jQuery: 'readonly',
			},
			rules: {
				'import/no-extraneous-dependencies': 'off',
				'import/no-unresolved': 'off',
				'jsdoc/require-param-type': 'off',
				'jsdoc/require-param': 'off',
				'jsdoc/check-tag-names': 'off',
				'jsdoc/check-tag': 'off',
			},
		},
	],
};
