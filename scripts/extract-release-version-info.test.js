#!/usr/bin/env node

/**
 * Tests for extract-release-version-info.js
 *
 * Run with: node scripts/extract-release-version-info.test.js
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const assert = require('assert');
const {
	parseComponent,
	parseVersionFromChangelog,
	extractVersionInfo,
} = require('./extract-release-version-info');

const SCRIPT_PATH = path.join(__dirname, 'extract-release-version-info.js');
const TEST_DIR = path.join(__dirname, '..', '.test-version-info');

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

function writeChangelog(component, body) {
	const dir = path.join(TEST_DIR, component);
	fs.mkdirSync(dir, { recursive: true });
	fs.writeFileSync(path.join(dir, 'CHANGELOG.md'), body);
}

function runScript(prTitle, branch, pluginsDir) {
	// execFileSync (args array, no shell) so the values pass through as argv
	// entries — no quoting or injection surface, even for exotic titles.
	const args = [SCRIPT_PATH, `--pr-title=${prTitle}`, `--branch=${branch}`];
	if (pluginsDir) {
		args.push(`--plugins-dir=${pluginsDir}`);
	}
	const output = execFileSync('node', args, {
		cwd: path.join(__dirname, '..'),
		encoding: 'utf8',
	});
	// Parse the key=value stdout the workflow appends to $GITHUB_OUTPUT.
	const out = {};
	output
		.trim()
		.split('\n')
		.forEach((line) => {
			const [k, ...rest] = line.split('=');
			out[k] = rest.join('=');
		});
	return out;
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

const RELEASE_PLEASE_CHANGELOG = `# Changelog

## [2.18.0](https://github.com/wp-graphql/wp-graphql/compare/v2.17.0...v2.18.0) (2026-07-22)

### New Features

* something ([#4025](https://x))

## [2.17.0](https://github.com/wp-graphql/wp-graphql/compare/v2.16.0...v2.17.0) (2026-07-01)
`;

const tests = [
	() =>
		test('parseComponent reads the component from the PR title', () => {
			assert.strictEqual(
				parseComponent(
					'chore(main): release wp-graphql 2.7.0',
					'release-please--branches--main--components--wp-graphql'
				),
				'wp-graphql'
			);
		}),

	() =>
		test('parseComponent handles hyphenated components', () => {
			assert.strictEqual(
				parseComponent('chore(main): release wp-graphql-acf 2.7.0', ''),
				'wp-graphql-acf'
			);
		}),

	() =>
		test('parseComponent falls back to the branch --components-- suffix', () => {
			assert.strictEqual(
				parseComponent(
					'no component here',
					'release-please--branches--main--components--wp-graphql-smart-cache'
				),
				'wp-graphql-smart-cache'
			);
		}),

	() =>
		test('parseComponent returns the whole branch when there is no --components-- marker', () => {
			// Matches the original sed fallback: a bogus-but-non-empty
			// component fails fast downstream rather than resolving to
			// plugins/ and touching every plugin.
			assert.strictEqual(
				parseComponent('no version-y title', 'release-please--odd'),
				'release-please--odd'
			);
		}),

	() =>
		test('parseComponent returns empty only when there is no title match and no branch', () => {
			assert.strictEqual(parseComponent('nothing', ''), '');
		}),

	() =>
		test('parseVersionFromChangelog returns the newest heading version', () => {
			assert.strictEqual(
				parseVersionFromChangelog(RELEASE_PLEASE_CHANGELOG),
				'2.18.0'
			);
		}),

	() =>
		test('parseVersionFromChangelog returns empty with no version heading', () => {
			assert.strictEqual(
				parseVersionFromChangelog('# Changelog\n\nNo versions yet.\n'),
				''
			);
		}),

	() =>
		test('extractVersionInfo prefers the version in the PR title', () => {
			const info = extractVersionInfo({
				prTitle: 'chore(main): release wp-graphql 2.7.0',
				branch: 'release-please--branches--main--components--wp-graphql',
			});
			assert.deepStrictEqual(info, {
				version: '2.7.0',
				component: 'wp-graphql',
				plugin_dir: 'plugins/wp-graphql',
			});
		}),

	() =>
		test('extractVersionInfo falls back to the CHANGELOG when the title has no version', () => {
			writeChangelog('wp-graphql', RELEASE_PLEASE_CHANGELOG);
			const info = extractVersionInfo({
				prTitle: 'chore(main): release wp-graphql',
				branch: 'release-please--branches--main--components--wp-graphql',
				pluginsDir: TEST_DIR,
			});
			assert.strictEqual(info.version, '2.18.0');
			assert.strictEqual(info.component, 'wp-graphql');
		}),

	() =>
		test('extractVersionInfo yields empty version when nothing resolves it', () => {
			const info = extractVersionInfo({
				prTitle: 'chore(main): release wp-graphql',
				branch: 'release-please--branches--main--components--wp-graphql',
				pluginsDir: TEST_DIR, // no CHANGELOG written
			});
			assert.strictEqual(info.version, '');
			assert.strictEqual(info.component, 'wp-graphql');
		}),

	() =>
		test('CLI prints version/component/plugin_dir as key=value lines', () => {
			const out = runScript(
				'chore(main): release wp-graphql-ide 5.3.0',
				'release-please--branches--main--components--wp-graphql-ide'
			);
			assert.strictEqual(out.version, '5.3.0');
			assert.strictEqual(out.component, 'wp-graphql-ide');
			assert.strictEqual(out.plugin_dir, 'plugins/wp-graphql-ide');
		}),

	() =>
		test('CLI resolves the version from a fixture CHANGELOG when the title omits it', () => {
			writeChangelog('wp-graphql-acf', RELEASE_PLEASE_CHANGELOG);
			const out = runScript(
				'chore(main): release wp-graphql-acf',
				'release-please--branches--main--components--wp-graphql-acf',
				TEST_DIR
			);
			assert.strictEqual(out.version, '2.18.0');
			assert.strictEqual(out.component, 'wp-graphql-acf');
		}),

	() =>
		test('CLI reads PR_TITLE / BRANCH_NAME from the env (the workflow path)', () => {
			// No CLI args — the workflow passes these via env so no PR-controlled
			// string reaches the command line.
			const output = execFileSync('node', [SCRIPT_PATH], {
				cwd: path.join(__dirname, '..'),
				encoding: 'utf8',
				env: {
					...process.env,
					PR_TITLE: 'chore(main): release wp-graphql-ide 5.3.0',
					BRANCH_NAME:
						'release-please--branches--main--components--wp-graphql-ide',
				},
			});
			const out = {};
			output
				.trim()
				.split('\n')
				.forEach((line) => {
					const [k, ...rest] = line.split('=');
					out[k] = rest.join('=');
				});
			assert.strictEqual(out.version, '5.3.0');
			assert.strictEqual(out.component, 'wp-graphql-ide');
		}),
];

console.log('\n🧪 Running extract-release-version-info.js tests...\n');

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
