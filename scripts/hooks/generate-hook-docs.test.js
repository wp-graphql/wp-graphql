#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { execSync } = require('child_process');

const SCRIPT_PATH = path.join(__dirname, 'generate-hook-docs.js');
const TEST_ROOT = path.join(__dirname, '..', '..', '.test-hook-docs');
const TEST_PLUGIN_SLUG = 'test-plugin';

function setup() {
	if (fs.existsSync(TEST_ROOT)) {
		fs.rmSync(TEST_ROOT, { recursive: true });
	}

	fs.mkdirSync(TEST_ROOT, { recursive: true });
	fs.mkdirSync(path.join(TEST_ROOT, 'plugins/test-plugin/src'), { recursive: true });
	fs.mkdirSync(path.join(TEST_ROOT, 'plugins/test-plugin/docs/snippets'), {
		recursive: true,
	});
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
		`--config=.test-hook-docs/scripts/hooks/plugin-config.json`,
		`--groups=.test-hook-docs/scripts/hooks/groups.json`,
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
 * Fires when docs are generated.
 *
 * @param array<string,mixed> $payload Hook payload.
 * @since 1.0.0
 * @hookGroup request-lifecycle
 */
do_action( 'graphql_docs_generated', [ 'ok' => true ] );

/**
 * Filter the generated docs payload.
 *
 * @param array<string,mixed> $payload Payload.
 * @since 1.0.0
 */
apply_filters( 'graphql_docs_payload', [ 'ok' => true ] );
`;

	const snippet = `---
title: Docs payload example
hookNames:
  - graphql_docs_payload
---

\`\`\`php
add_filter( 'graphql_docs_payload', function( $payload ) {
\treturn $payload;
} );
\`\`\`
`;

	const pluginConfig = {
		[TEST_PLUGIN_SLUG]: {
			pluginDir: '.test-hook-docs/plugins/test-plugin',
			sourcePaths: ['src'],
			docsDir: '.test-hook-docs/plugins/test-plugin/docs',
			snippetsDir: '.test-hook-docs/plugins/test-plugin/docs/snippets',
		},
	};

	const groups = [
		{
			id: 'request-lifecycle',
			label: 'Request Lifecycle',
			kind: 'both',
			match: ['^graphql_docs_'],
		},
		{
			id: 'uncategorized',
			label: 'Uncategorized',
			kind: 'both',
			match: [],
		},
	];

	fs.writeFileSync(
		path.join(TEST_ROOT, 'plugins/test-plugin/src/Hooks.php'),
		source,
		'utf8'
	);
	fs.writeFileSync(
		path.join(TEST_ROOT, 'plugins/test-plugin/docs/snippets/sample.md'),
		snippet,
		'utf8'
	);
	fs.writeFileSync(
		path.join(TEST_ROOT, 'scripts/hooks/plugin-config.json'),
		JSON.stringify(pluginConfig, null, 2),
		'utf8'
	);
	fs.writeFileSync(
		path.join(TEST_ROOT, 'scripts/hooks/groups.json'),
		JSON.stringify(groups, null, 2),
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
		test('generates json and markdown outputs', () => {
			const result = runScript();
			assert(result.success, 'generator should succeed');

			const indexPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/generated/hooks-index.json'
			);
			const actionDocPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/actions/graphql_docs_generated.md'
			);
			const filterDocPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/filters/graphql_docs_payload.md'
			);

			assert(fs.existsSync(indexPath), 'hooks-index.json should be generated');
			assert(fs.existsSync(actionDocPath), 'action doc should be generated');
			assert(fs.existsSync(filterDocPath), 'filter doc should be generated');

			const index = JSON.parse(fs.readFileSync(indexPath, 'utf8'));
			assert.strictEqual(index.stats.totalHooks, 2, 'expected two hooks');

			const payloadHook = index.hooks.find((hook) => hook.name === 'graphql_docs_payload');
			assert(payloadHook, 'filter hook should exist');
			assert.strictEqual(payloadHook.group, 'request-lifecycle');
			assert.strictEqual(payloadHook.relatedSnippets.length, 1);
		}),
	() =>
		test('fails on missing @hookGroup when strict mode is enabled', () => {
			const result = runScript('--require-explicit-group=true');
			assert(!result.success, 'generator should fail in strict mode');
			assert(
				result.stderr.includes('Errors:'),
				'error output should indicate lint errors'
			);
		}),
	() =>
		test('validate-only mode fails when outputs are stale', () => {
			const firstRun = runScript();
			assert(firstRun.success, 'first run should succeed');

			const actionIndexPath = path.join(
				TEST_ROOT,
				'plugins/test-plugin/docs/actions/index.md'
			);
			fs.writeFileSync(actionIndexPath, 'stale output', 'utf8');

			const validateRun = runScript('--validate-only=true');
			assert(!validateRun.success, 'validate-only should fail for stale files');
			assert(
				validateRun.stderr.includes('Generated files are stale'),
				'validate-only should report stale outputs'
			);
		}),
];

console.log('\nRunning generate-hook-docs.js tests\n');

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
