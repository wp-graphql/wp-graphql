#!/usr/bin/env node

/**
 * Tests for update-readme-changelog.js
 *
 * Run with: node scripts/update-readme-changelog.test.js
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');

const SCRIPT_PATH = path.join(__dirname, 'update-readme-changelog.js');
const TEST_DIR = path.join(__dirname, '..', '.test-readme-changelog');

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

function createChangelog(content) {
	fs.writeFileSync(path.join(TEST_DIR, 'CHANGELOG.md'), content);
}

function createReadme(content) {
	fs.writeFileSync(path.join(TEST_DIR, 'readme.txt'), content);
}

function readReadme() {
	return fs.readFileSync(path.join(TEST_DIR, 'readme.txt'), 'utf8');
}

function runScript(version) {
	const cmd = `node "${SCRIPT_PATH}" --version=${version} --plugin-dir=.test-readme-changelog`;
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

const tests = [
	() =>
		test('extracts bracketed version header and inserts top changelog entry', () => {
			createChangelog(`# Changelog

## [2.1.0](https://example.com/compare/v2.0.0...v2.1.0) (2026-01-01)

### New Features

* add feature ([#1](https://example.com/issues/1)) ([abcdef1](https://example.com/commit/abcdef1))

## 2.0.0

### Bug Fixes

* fixed old bug
`);

			createReadme(`=== Test ===
Stable tag: 2.1.0

== Changelog ==

= 2.0.0 =

**Bug Fixes**

* fixed old bug
`);

			const result = runScript('2.1.0');
			assert(result.success, 'script should succeed');
			const readme = readReadme();
			assert(readme.includes('= 2.1.0 ='), 'should insert version header');
			assert(
				readme.indexOf('= 2.1.0 =') < readme.indexOf('= 2.0.0 ='),
				'should insert new version at top'
			);
			assert(readme.includes('**New Features**'), 'should map section heading');
			assert(
				readme.includes('* add feature ([#1](https://example.com/issues/1))'),
				'should preserve issue link'
			);
			assert(!readme.includes('abcdef1'), 'should strip trailing commit hash link');
		}),

	() =>
		test('extracts plain version header and replaces existing block', () => {
			createChangelog(`# Changelog

## 1.4.0

### Bug Fixes

* fix current issue ([#2](https://example.com/issues/2))
`);

			createReadme(`=== Test ===
Stable tag: 1.4.0

== Changelog ==

= 1.4.0 =

**Bug Fixes**

* old stale content

= 1.3.0 =

* previous
`);

			const result = runScript('1.4.0');
			assert(result.success, 'script should succeed');

			const readme = readReadme();
			assert(
				!readme.includes('* old stale content'),
				'should replace existing version block content'
			);
			assert(
				readme.includes('* fix current issue ([#2](https://example.com/issues/2))'),
				'should contain replacement content'
			);
		}),

	() =>
		test('normalizes markdown note blockquote and heading spacing', () => {
			createChangelog(`# Changelog

## [3.0.0](https://example.com) (2026-02-02)

> **Note:** Important release note.

### Other Changes

- adjust text
`);

			createReadme(`=== Test ===
Stable tag: 3.0.0

== Changelog ==

= 2.0.0 =

* old
`);

			const result = runScript('3.0.0');
			assert(result.success, 'script should succeed');
			const readme = readReadme();
			assert(
				readme.includes('**Note:** Important release note.'),
				'should preserve note content without blockquote marker'
			);
			assert(readme.includes('**Other Changes**'), 'should normalize heading');
			assert(readme.includes('* adjust text'), 'should normalize dash to bullet');
		}),

	() =>
		test('returns no-op when requested version is missing', () => {
			createChangelog(`# Changelog

## [1.0.0](https://example.com) (2026-01-01)

### Bug Fixes

* fix
`);

			const originalReadme = `=== Test ===
Stable tag: 1.0.0

== Changelog ==

= 1.0.0 =

* fix
`;

			createReadme(originalReadme);

			const result = runScript('9.9.9');
			assert(result.success, 'script should exit successfully on no-op');
			assert(
				result.output.includes('No changelog entry found'),
				'should report missing version'
			);
			assert.strictEqual(readReadme(), originalReadme, 'readme should stay unchanged');
		}),

	() =>
		test('collapses excessive blank lines in changelog section', () => {
			createChangelog(`# Changelog

## [5.0.0](https://example.com) (2026-03-03)

### Bug Fixes

* spacing fix
`);

			createReadme(`=== Test ===
Stable tag: 5.0.0

== Changelog ==




= 4.9.0 =

* old
`);

			const result = runScript('5.0.0');
			assert(result.success, 'script should succeed');
			const readme = readReadme();
			assert(
				readme.includes('== Changelog ==\n\n= 5.0.0 ='),
				'should normalize heading-to-first-version spacing'
			);
			assert(
				!readme.includes('== Changelog ==\n\n\n'),
				'should not leave triple blank lines'
			);
		}),
];

console.log('\n🧪 Running update-readme-changelog.js tests...\n');

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
