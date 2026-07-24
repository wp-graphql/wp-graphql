#!/usr/bin/env node

/**
 * Tests for replace-legacy-hook-versions.js
 *
 * Run with: node scripts/hooks/replace-legacy-hook-versions.test.js
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');
const {
	replacePlaceholders,
	replaceComponentVersions,
} = require('./replace-legacy-hook-versions');

const SCRIPT_PATH = path.join(__dirname, 'replace-legacy-hook-versions.js');
const TEST_DIR = path.join(__dirname, '..', '..', '.test-legacy-hook-versions');
const TEST_FILE = path.join(TEST_DIR, 'legacy-hooks.json');

function setup() {
	if (fs.existsSync(TEST_DIR)) {
		fs.rmSync(TEST_DIR, { recursive: true });
	}
	fs.mkdirSync(TEST_DIR, { recursive: true });
}

function cleanup() {
	if (fs.existsSync(TEST_DIR)) {
		fs.rmSync(TEST_DIR, { recursive: true });
	}
}

function writeFile(obj) {
	fs.writeFileSync(TEST_FILE, `${JSON.stringify(obj, null, 2)}\n`);
}

function readFile() {
	return JSON.parse(fs.readFileSync(TEST_FILE, 'utf8'));
}

function runScript(component, version) {
	const cmd = [
		`node "${SCRIPT_PATH}"`,
		`--component=${component}`,
		`--version=${version}`,
		`--file="${TEST_FILE}"`,
	].join(' ');
	try {
		const output = execSync(cmd, {
			cwd: path.join(__dirname, '..', '..'),
			encoding: 'utf8',
			stdio: ['pipe', 'pipe', 'pipe'],
		});
		return { success: true, output };
	} catch (error) {
		return { success: false, output: error.stdout || error.message };
	}
}

function test(name, fn) {
	try {
		setup();
		fn();
		console.log(`✅ ${name}`);
		return true;
	} catch (error) {
		console.log(`❌ ${name}`);
		console.log(`   Error: ${error.message}`);
		return false;
	} finally {
		cleanup();
	}
}

const PLACEHOLDER = 'x-release-please-version';

const tests = [
	() =>
		test('replaces placeholders in strings, arrays, and nested objects', () => {
			const input = {
				since: PLACEHOLDER,
				list: [PLACEHOLDER, 'literal'],
				nested: { deprecated: PLACEHOLDER, count: 3, flag: true },
			};
			const result = replacePlaceholders(input, '2.7.0');
			assert.deepStrictEqual(result, {
				since: '2.7.0',
				list: ['2.7.0', 'literal'],
				nested: { deprecated: '2.7.0', count: 3, flag: true },
			});
		}),

	() =>
		test('leaves non-placeholder values untouched', () => {
			assert.strictEqual(replacePlaceholders(42, '2.7.0'), 42);
			assert.strictEqual(replacePlaceholders(null, '2.7.0'), null);
			assert.strictEqual(
				replacePlaceholders('already 1.0.0', '2.7.0'),
				'already 1.0.0'
			);
		}),

	() =>
		test('does not treat the version as a regex replacement pattern', () => {
			// A naive .replace(/.../g, version) would interpret $& etc. in the
			// replacement; split/join must reproduce the version verbatim.
			assert.strictEqual(
				replacePlaceholders(PLACEHOLDER, '$&2.0.0'),
				'$&2.0.0'
			);
		}),

	() =>
		test('replaceComponentVersions updates only the target component', () => {
			const payload = {
				'wp-graphql': [{ name: 'old_hook', since: PLACEHOLDER }],
				'wp-graphql-acf': [{ name: 'acf_hook', since: PLACEHOLDER }],
			};
			const { payload: updated, changed } = replaceComponentVersions(
				payload,
				'wp-graphql',
				'2.18.0'
			);
			assert.strictEqual(changed, true);
			assert.strictEqual(updated['wp-graphql'][0].since, '2.18.0');
			// sibling untouched
			assert.strictEqual(updated['wp-graphql-acf'][0].since, PLACEHOLDER);
		}),

	() =>
		test('reports no change when the component has no entries', () => {
			const payload = { 'wp-graphql': [] };
			const { payload: updated, changed } = replaceComponentVersions(
				payload,
				'wp-graphql',
				'2.18.0'
			);
			assert.strictEqual(changed, false);
			assert.strictEqual(updated, payload);
		}),

	() =>
		test('reports reason "no-entries" when the component is absent', () => {
			const payload = { 'wp-graphql': [] };
			const { changed, reason } = replaceComponentVersions(
				payload,
				'not-a-plugin',
				'2.18.0'
			);
			assert.strictEqual(changed, false);
			assert.strictEqual(reason, 'no-entries');
		}),

	() =>
		test('reports reason "no-placeholders" when nothing is left to replace', () => {
			const payload = {
				'wp-graphql': [{ name: 'hook', since: '2.0.0' }],
			};
			const { changed, reason } = replaceComponentVersions(
				payload,
				'wp-graphql',
				'2.18.0'
			);
			assert.strictEqual(changed, false);
			assert.strictEqual(reason, 'no-placeholders');
		}),

	() =>
		test('CLI rewrites the file for a component with placeholders', () => {
			writeFile({
				'wp-graphql': [{ name: 'old_hook', since: PLACEHOLDER }],
			});
			const { success, output } = runScript('wp-graphql', '2.18.0');
			assert(success, `script failed: ${output}`);
			assert.strictEqual(readFile()['wp-graphql'][0].since, '2.18.0');
		}),

	() =>
		test('CLI is a no-op (byte-identical) when nothing matches', () => {
			writeFile({ 'wp-graphql': [] });
			const before = fs.readFileSync(TEST_FILE, 'utf8');
			const { success, output } = runScript('wp-graphql', '2.18.0');
			assert(success, `script failed: ${output}`);
			assert(/No legacy hook placeholders/.test(output), output);
			assert.strictEqual(fs.readFileSync(TEST_FILE, 'utf8'), before);
		}),

	() =>
		test('CLI logs "no entries" (not "no placeholders") for a missing component', () => {
			writeFile({ 'wp-graphql': [{ name: 'h', since: PLACEHOLDER }] });
			const before = fs.readFileSync(TEST_FILE, 'utf8');
			const { success, output } = runScript('not-a-plugin', '2.18.0');
			assert(success, `script failed: ${output}`);
			assert(/No legacy hook entries/.test(output), output);
			assert(!/No legacy hook placeholders/.test(output), output);
			assert.strictEqual(fs.readFileSync(TEST_FILE, 'utf8'), before);
		}),

	() =>
		test('CLI skips (exit 0) without required args, as the inline step did', () => {
			writeFile({ 'wp-graphql': [] });
			const before = fs.readFileSync(TEST_FILE, 'utf8');
			// Missing --component/--version must not fail the release workflow.
			const output = execSync(
				`node "${SCRIPT_PATH}" --file="${TEST_FILE}"`,
				{
					cwd: path.join(__dirname, '..', '..'),
					encoding: 'utf8',
				}
			);
			assert(
				/skipping/i.test(output),
				`expected a skip message, got: ${output}`
			);
			assert.strictEqual(fs.readFileSync(TEST_FILE, 'utf8'), before);
		}),
];

console.log('\n🧪 Running replace-legacy-hook-versions.js tests...\n');

let passed = 0;
let failed = 0;

for (const testFn of tests) {
	if (testFn()) {
		passed++;
	} else {
		failed++;
	}
}

console.log(`\n${'─'.repeat(50)}`);
console.log(`Results: ${passed} passed, ${failed} failed`);
console.log(`${'─'.repeat(50)}\n`);

process.exit(failed > 0 ? 1 : 0);
