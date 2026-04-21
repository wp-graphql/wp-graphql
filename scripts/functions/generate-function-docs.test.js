#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { execSync } = require('child_process');

const SCRIPT_PATH = path.join(__dirname, 'generate-function-docs.js');
const TEST_ROOT = path.join(__dirname, '..', '..', '.test-function-docs');
const TEST_PLUGIN_SLUG = 'test-plugin';

function setup() {
	if (fs.existsSync(TEST_ROOT)) {
		fs.rmSync(TEST_ROOT, { recursive: true });
	}

	fs.mkdirSync(TEST_ROOT, { recursive: true });
	fs.mkdirSync(path.join(TEST_ROOT, 'plugins/test-plugin'), { recursive: true });
	fs.mkdirSync(path.join(TEST_ROOT, 'plugins/test-plugin/docs'), { recursive: true });
	fs.mkdirSync(path.join(TEST_ROOT, 'scripts/hooks'), { recursive: true });
}

function cleanup() {
	if (fs.existsSync(TEST_ROOT)) {
		fs.rmSync(TEST_ROOT, { recursive: true });
	}
}

function runScript(extraArgs = '') {
	const command = [
		`node "${SCRIPT_PATH}"`,
		`--plugin=${TEST_PLUGIN_SLUG}`,
		`--config=.test-function-docs/scripts/hooks/plugin-config.json`,
		extraArgs,
	]
		.filter(Boolean)
		.join(' ');

	try {
		const output = execSync(command, {
			cwd: path.join(__dirname, '..', '..'),
			encoding: 'utf8',
			stdio: ['pipe', 'pipe', 'pipe'],
		});
		return { success: true, output };
	} catch (error) {
		return {
			success: false,
			stdout: error.stdout || '',
			stderr: error.stderr || '',
		};
	}
}

function writeFixtureFiles() {
	const source = `<?php
/**
 * Formats fixture output.
 *
 * @param string $value Value to format.
 * @return string
 * @since 1.0.0
 */
function fixture_format( string $value ): string {
	return strtoupper( $value );
}

/**
 * Wrapper function with missing docs.
 *
 * @param string $query The query.
 * @functionGroup Query Helpers
 */
function fixture_query( $query ) {
	/**
	 * This inline docblock should not be associated with fixture_query.
	 *
	 * @param string $query Query string.
	 */
	$query = apply_filters( 'fixture_query', $query );
	return $query;
}

if ( ! function_exists( 'fixture_polyfill' ) ) {
	/**
	 * Polyfill function.
	 *
	 * @param string $value The value.
	 * @return bool
	 * @since 1.1.0
	 */
	function fixture_polyfill( string $value ): bool {
		return ! empty( $value );
	}
}
`;

	const pluginConfig = {
		[TEST_PLUGIN_SLUG]: {
			pluginDir: '.test-function-docs/plugins/test-plugin',
			docsDir: '.test-function-docs/plugins/test-plugin/docs',
		},
	};

	fs.writeFileSync(path.join(TEST_ROOT, 'plugins/test-plugin/access-functions.php'), source, 'utf8');
	fs.writeFileSync(
		path.join(TEST_ROOT, 'scripts/hooks/plugin-config.json'),
		JSON.stringify(pluginConfig, null, 2),
		'utf8'
	);
}

function test(name, fn) {
	try {
		setup();
		writeFixtureFiles();
		fn();
		console.log(`PASS ${name}`);
		return true;
	} catch (error) {
		console.log(`FAIL ${name}`);
		console.log(error.message);
		return false;
	} finally {
		cleanup();
	}
}

const tests = [
	() =>
		test('generates function docs and artifacts', () => {
			const result = runScript();
			assert(result.success, 'generator should succeed');

			const indexPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/generated/functions-index.json'
			);
			const lintPath = path.join(TEST_ROOT, 'plugins/test-plugin/docs/generated/functions-lint.json');
			const functionDocPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/functions/fixture_format.md'
			);
			const polyfillDocPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/functions/fixture_polyfill.md'
			);
			const indexDocPath = path.join(TEST_ROOT, 'plugins/test-plugin/docs/functions/index.md');

			assert(fs.existsSync(indexPath), 'functions index json should be generated');
			assert(fs.existsSync(lintPath), 'function lint json should be generated');
			assert(fs.existsSync(functionDocPath), 'function doc should be generated');
			assert(fs.existsSync(polyfillDocPath), 'polyfill function doc should be generated');
			assert(fs.existsSync(indexDocPath), 'function index markdown should be generated');

			const functionDoc = fs.readFileSync(functionDocPath, 'utf8');
			assert(functionDoc.includes('# fixture_format'), 'doc should include heading');
			assert(functionDoc.includes('```php'), 'doc should include signature code block');
			assert(functionDoc.includes('## Source'), 'doc should include source section');

			const indexDoc = fs.readFileSync(indexDocPath, 'utf8');
			assert(indexDoc.includes('## Query Helpers'), 'custom @functionGroup heading should be rendered');
			assert(
				indexDoc.includes('- [`fixture_query`](/functions/fixture_query)'),
				'custom @functionGroup should include matching function links'
			);

			const index = JSON.parse(fs.readFileSync(indexPath, 'utf8'));
			assert.strictEqual(index.stats.totalFunctions, 3, 'expected three functions');

			const lint = JSON.parse(fs.readFileSync(lintPath, 'utf8'));
			assert(
				lint.warnings.some((item) => item.type === 'missing_function_since'),
				'missing since should be reported'
			);
			assert(
				lint.warnings.some((item) => item.type === 'missing_function_return'),
				'missing return should be reported'
			);
		}),
	() =>
		test('validate-only mode fails when docs are stale', () => {
			const firstRun = runScript();
			assert(firstRun.success, 'first run should succeed');

			const docPath = path.join(TEST_ROOT, 'plugins/test-plugin/docs/functions/fixture_format.md');
			fs.writeFileSync(docPath, 'stale output', 'utf8');

			const validateRun = runScript('--validate-only=true');
			assert(!validateRun.success, 'validate-only should fail for stale output');
			assert(
				validateRun.stderr.includes('Generated function docs are stale'),
				'validate-only should report stale function docs'
			);
		}),
];

console.log('\nRunning generate-function-docs.js tests\n');

let passed = 0;
let failed = 0;

tests.forEach((runTest) => {
	if (runTest()) {
		passed++;
	} else {
		failed++;
	}
});

console.log(`\nResults: ${passed} passed, ${failed} failed\n`);
process.exit(failed > 0 ? 1 : 0);
