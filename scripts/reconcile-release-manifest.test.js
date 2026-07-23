#!/usr/bin/env node

/**
 * Tests for reconcile-release-manifest.js
 *
 * Run with: node scripts/reconcile-release-manifest.test.js
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');
const { reconcileManifest } = require('./reconcile-release-manifest');

const SCRIPT_PATH = path.join(__dirname, 'reconcile-release-manifest.js');
const TEST_DIR = path.join(__dirname, '..', '.test-reconcile-manifest');

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

function writeJson(name, obj) {
	fs.writeFileSync(
		path.join(TEST_DIR, name),
		`${JSON.stringify(obj, null, 2)}\n`
	);
}

// Runs the real CLI against files in TEST_DIR, using --main-manifest to stand
// in for the git read so the test needs no remote.
function runScript(component) {
	const cmd = [
		`node "${SCRIPT_PATH}"`,
		`--component=${component}`,
		`--manifest=.test-reconcile-manifest/branch.json`,
		`--main-manifest=.test-reconcile-manifest/main.json`,
	].join(' ');
	try {
		const output = execSync(cmd, {
			cwd: path.join(__dirname, '..'),
			encoding: 'utf8',
			stdio: ['pipe', 'pipe', 'pipe'],
		});
		return { success: true, output };
	} catch (error) {
		return { success: false, output: error.stdout || error.message };
	}
}

function readBranch() {
	return JSON.parse(
		fs.readFileSync(path.join(TEST_DIR, 'branch.json'), 'utf8')
	);
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

// The real-world case: main released wp-graphql 2.18.0 and acf 2.7.0 while an
// IDE release PR sat open, so the IDE branch's copy of those lines went stale.
const MAIN = {
	'plugins/wp-graphql': '2.18.0',
	'plugins/wp-graphql-smart-cache': '2.2.2',
	'plugins/wp-graphql-ide': '5.2.0',
	'plugins/wp-graphql-acf': '2.7.0',
};

const STALE_IDE_BRANCH = {
	'plugins/wp-graphql': '2.17.0',
	'plugins/wp-graphql-smart-cache': '2.2.2',
	'plugins/wp-graphql-ide': '5.3.0',
	'plugins/wp-graphql-acf': '2.6.5',
};

const tests = [
	() =>
		test('adopts main sibling versions and keeps the own bump', () => {
			const result = reconcileManifest(
				MAIN,
				STALE_IDE_BRANCH,
				'wp-graphql-ide'
			);
			assert.deepStrictEqual(result, {
				'plugins/wp-graphql': '2.18.0',
				'plugins/wp-graphql-smart-cache': '2.2.2',
				'plugins/wp-graphql-ide': '5.3.0', // own bump preserved
				'plugins/wp-graphql-acf': '2.7.0', // pulled from main
			});
		}),

	() =>
		test('preserves main key order', () => {
			const result = reconcileManifest(
				MAIN,
				STALE_IDE_BRANCH,
				'wp-graphql-ide'
			);
			assert.deepStrictEqual(Object.keys(result), Object.keys(MAIN));
		}),

	() =>
		test('is a no-op when already reconciled', () => {
			const current = {
				'plugins/wp-graphql': '2.18.0',
				'plugins/wp-graphql-smart-cache': '2.2.2',
				'plugins/wp-graphql-ide': '5.3.0',
				'plugins/wp-graphql-acf': '2.7.0',
			};
			const result = reconcileManifest(MAIN, current, 'wp-graphql-ide');
			assert.deepStrictEqual(result, current);
		}),

	() =>
		test('does not touch the released component even if main is ahead', () => {
			// main somehow shows a higher version for the component being
			// released; the branch bump must still win, never regress.
			const mainAhead = { ...MAIN, 'plugins/wp-graphql-ide': '9.9.9' };
			const result = reconcileManifest(
				mainAhead,
				STALE_IDE_BRANCH,
				'wp-graphql-ide'
			);
			assert.strictEqual(result['plugins/wp-graphql-ide'], '5.3.0');
		}),

	() =>
		test('leaves the branch untouched when the component is unknown', () => {
			const result = reconcileManifest(
				MAIN,
				STALE_IDE_BRANCH,
				'not-a-plugin'
			);
			assert.deepStrictEqual(result, STALE_IDE_BRANCH);
		}),

	() =>
		test('CLI rewrites a stale branch manifest', () => {
			writeJson('main.json', MAIN);
			writeJson('branch.json', STALE_IDE_BRANCH);

			const { success, output } = runScript('wp-graphql-ide');
			assert(success, `script failed: ${output}`);
			assert.deepStrictEqual(readBranch(), {
				'plugins/wp-graphql': '2.18.0',
				'plugins/wp-graphql-smart-cache': '2.2.2',
				'plugins/wp-graphql-ide': '5.3.0',
				'plugins/wp-graphql-acf': '2.7.0',
			});
		}),

	() =>
		test('CLI leaves an already-reconciled manifest byte-identical', () => {
			const current = {
				'plugins/wp-graphql': '2.18.0',
				'plugins/wp-graphql-smart-cache': '2.2.2',
				'plugins/wp-graphql-ide': '5.3.0',
				'plugins/wp-graphql-acf': '2.7.0',
			};
			writeJson('main.json', MAIN);
			writeJson('branch.json', current);
			const before = fs.readFileSync(
				path.join(TEST_DIR, 'branch.json'),
				'utf8'
			);

			const { success, output } = runScript('wp-graphql-ide');
			assert(success, `script failed: ${output}`);
			assert(
				/nothing to do/.test(output),
				`expected no-op message, got: ${output}`
			);
			const after = fs.readFileSync(
				path.join(TEST_DIR, 'branch.json'),
				'utf8'
			);
			assert.strictEqual(after, before, 'file should be unchanged');
		}),

	() =>
		test('CLI exits non-zero without --component', () => {
			writeJson('main.json', MAIN);
			writeJson('branch.json', STALE_IDE_BRANCH);
			let failed = false;
			try {
				execSync(
					`node "${SCRIPT_PATH}" --manifest=.test-reconcile-manifest/branch.json --main-manifest=.test-reconcile-manifest/main.json`,
					{ cwd: path.join(__dirname, '..'), stdio: 'pipe' }
				);
			} catch (e) {
				failed = true;
			}
			assert(failed, 'expected non-zero exit without --component');
		}),
];

console.log('\n🧪 Running reconcile-release-manifest.js tests...\n');

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
