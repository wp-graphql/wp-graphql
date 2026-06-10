#!/usr/bin/env node

/**
 * Tests for update-upgrade-notice.js
 *
 * Run with: node scripts/update-upgrade-notice.test.js
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');

const SCRIPT_PATH = path.join(__dirname, 'update-upgrade-notice.js');
const TEST_DIR = path.join(__dirname, '..', '.test-upgrade-notice');

/**
 * Setup test directory with test files
 */
function setup() {
	if (fs.existsSync(TEST_DIR)) {
		fs.rmSync(TEST_DIR, { recursive: true });
	}
	fs.mkdirSync(TEST_DIR, { recursive: true });
}

/**
 * Cleanup test directory
 */
function cleanup() {
	if (fs.existsSync(TEST_DIR)) {
		fs.rmSync(TEST_DIR, { recursive: true });
	}
}

/**
 * Create test CHANGELOG.md
 */
function createChangelog(content) {
	fs.writeFileSync(path.join(TEST_DIR, 'CHANGELOG.md'), content);
}

/**
 * Create test readme.txt
 */
function createReadme(content) {
	fs.writeFileSync(path.join(TEST_DIR, 'readme.txt'), content);
}

/**
 * Read readme.txt content
 */
function readReadme() {
	return fs.readFileSync(path.join(TEST_DIR, 'readme.txt'), 'utf8');
}

/**
 * Run the script with given arguments
 */
function runScript(version) {
	const cmd = `node "${SCRIPT_PATH}" --version=${version} --plugin-dir=.test-upgrade-notice`;
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

/**
 * Test helper to run a test case
 */
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

// ===========================================
// TEST CASES
// ===========================================

const tests = [
	// Test 1: Basic breaking changes extraction
	() =>
		test('extracts breaking changes from standard release-please format', () => {
			createChangelog(`# Changelog

## [3.0.0](https://github.com/wp-graphql/wp-graphql/compare/v2.0.0...v3.0.0) (2026-01-20)

### ⚠ BREAKING CHANGES

* Remove deprecated query ([#123](https://github.com/wp-graphql/wp-graphql/pull/123))
* Change default behavior ([#124](https://github.com/wp-graphql/wp-graphql/pull/124))

### Features

* Add new feature ([#125](https://github.com/wp-graphql/wp-graphql/pull/125))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('Found 2 breaking change(s)'),
				'Should find 2 breaking changes'
			);

			const readme = readReadme();
			assert(
				readme.includes('== Upgrade Notice =='),
				'Should add Upgrade Notice section'
			);
			assert(
				readme.includes('= 3.0.0 ='),
				'Should include version header'
			);
			assert(
				readme.includes('Remove deprecated query'),
				'Should include first breaking change'
			);
			assert(
				readme.includes('Change default behavior'),
				'Should include second breaking change'
			);
			assert(readme.includes('#123'), 'Should preserve PR link');
		}),

	// Test 2: No breaking changes
	() =>
		test('handles version with no breaking changes', () => {
			createChangelog(`# Changelog

## [2.1.0](https://github.com/wp-graphql/wp-graphql/compare/v2.0.0...v2.1.0) (2026-01-20)

### Features

* Add new feature ([#125](https://github.com/wp-graphql/wp-graphql/pull/125))

### Bug Fixes

* Fix bug ([#126](https://github.com/wp-graphql/wp-graphql/pull/126))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('2.1.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('No breaking changes'),
				'Should report no breaking changes'
			);

			const readme = readReadme();
			assert(
				!readme.includes('== Upgrade Notice =='),
				'Should NOT add Upgrade Notice section'
			);
		}),

	// Test 3: Version not found in changelog
	() =>
		test('handles version not found in changelog', () => {
			createChangelog(`# Changelog

## [2.0.0](https://github.com/wp-graphql/wp-graphql/compare/v1.0.0...v2.0.0) (2026-01-20)

### Features

* Add new feature
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('No changelog entry found'),
				'Should report version not found'
			);
		}),

	// Test 4: Update existing upgrade notice
	() =>
		test('updates existing upgrade notice for same version', () => {
			createChangelog(`# Changelog

## [3.0.0](https://github.com/wp-graphql/wp-graphql/compare/v2.0.0...v3.0.0) (2026-01-20)

### ⚠ BREAKING CHANGES

* New breaking change ([#127](https://github.com/wp-graphql/wp-graphql/pull/127))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Upgrade Notice ==

= 3.0.0 =

**⚠️ BREAKING CHANGES**: Old notice content.

* Old breaking change

Please review these changes before upgrading.

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('Updated existing upgrade notice'),
				'Should update existing notice'
			);

			const readme = readReadme();
			assert(
				readme.includes('New breaking change'),
				'Should contain new breaking change'
			);
			// Count occurrences of "= 3.0.0 =" to ensure no duplicates
			const matches = readme.match(/= 3\.0\.0 =/g);
			assert(
				matches && matches.length === 1,
				'Should have exactly one version header'
			);
			// Regression guard: the existing Changelog section must survive
			// being adjacent to the upserted Upgrade Notice block.
			assert(
				readme.includes('== Changelog =='),
				'Should preserve the existing Changelog section'
			);
			assert(
				readme.includes('= 2.0.0 ='),
				'Should preserve the existing 2.0.0 changelog entry'
			);
		}),

	// Test 5: Alternative breaking changes header format
	() =>
		test('handles alternative BREAKING CHANGES header (without emoji)', () => {
			createChangelog(`# Changelog

## [3.0.0](https://github.com/wp-graphql/wp-graphql/compare/v2.0.0...v3.0.0) (2026-01-20)

### BREAKING CHANGES

* Remove old API ([#128](https://github.com/wp-graphql/wp-graphql/pull/128))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('Found 1 breaking change(s)'),
				'Should find breaking change'
			);
		}),

	// Test 6: Version format without brackets
	() =>
		test('handles version without brackets in changelog', () => {
			createChangelog(`# Changelog

## 3.0.0 (2026-01-20)

### ⚠ BREAKING CHANGES

* Breaking change without brackets ([#129](https://github.com/wp-graphql/wp-graphql/pull/129))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('Found 1 breaking change(s)'),
				'Should find breaking change'
			);
		}),

	// Test 7: Multiple versions in changelog
	() =>
		test('extracts breaking changes for correct version only', () => {
			createChangelog(`# Changelog

## [4.0.0](https://github.com/wp-graphql/wp-graphql/compare/v3.0.0...v4.0.0) (2026-02-01)

### ⚠ BREAKING CHANGES

* Version 4 breaking change ([#200](https://github.com/wp-graphql/wp-graphql/pull/200))

## [3.0.0](https://github.com/wp-graphql/wp-graphql/compare/v2.0.0...v3.0.0) (2026-01-20)

### ⚠ BREAKING CHANGES

* Version 3 breaking change ([#100](https://github.com/wp-graphql/wp-graphql/pull/100))

## [2.0.0](https://github.com/wp-graphql/wp-graphql/compare/v1.0.0...v2.0.0) (2026-01-01)

### Features

* Some feature
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');

			const readme = readReadme();
			assert(
				readme.includes('Version 3 breaking change'),
				'Should include v3 breaking change'
			);
			assert(
				!readme.includes('Version 4 breaking change'),
				'Should NOT include v4 breaking change'
			);
			assert(readme.includes('#100'), 'Should include correct PR link');
			assert(!readme.includes('#200'), 'Should NOT include v4 PR link');
		}),

	// Test 8a: Regression — wp-graphql-ide 5.0.0 release PR shape (PR #3892).
	// The release-please flow pre-populated `= 5.0.0 =` in the Upgrade Notice
	// section with no later `= <digit>` heading after it, immediately followed
	// by `== Changelog ==`. The buggy lazy match swallowed the Changelog header
	// up to EOF and the downstream `update-readme-changelog.js` then threw.
	() =>
		test('preserves "== Changelog ==" when upserting an upgrade notice with no later = <digit> heading', () => {
			createChangelog(`# Changelog

## [5.0.0](https://github.com/wp-graphql/wp-graphql/compare/v4.5.0...v5.0.0) (2026-06-09)

### ⚠ BREAKING CHANGES

* Rebuild IDE UI on @wordpress/components ([#3784](https://github.com/wp-graphql/wp-graphql/pull/3784))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 4.5.0

== Upgrade Notice ==

= 5.0.0 =
Hand-written upgrade-notice paragraph from the feature PR.

WPGraphQL IDE follows Semver versioning. Breaking changes will be documented in the Upgrade Notice section above.

== Changelog ==

= 5.0.0 =

**Breaking changes**

* IDE UI rebuilt on @wordpress/components.

= 4.5.0 =
* Previous release.
`);

			const result = runScript('5.0.0');
			assert(result.success, `Script should succeed. Output: ${result.output}`);

			const readme = readReadme();
			assert(
				readme.includes('== Changelog =='),
				'Must preserve the == Changelog == section heading'
			);
			assert(
				readme.includes('= 4.5.0 ='),
				'Must preserve earlier changelog entries'
			);
			// New upgrade-notice content for 5.0.0 should be present.
			assert(
				readme.includes('Rebuild IDE UI on @wordpress/components'),
				'Should write the new breaking change into the upgrade notice'
			);
			// The pre-existing 5.0.0 Changelog body should still be there.
			assert(
				readme.includes('IDE UI rebuilt on @wordpress/components'),
				'Must preserve the pre-existing 5.0.0 changelog body'
			);
			// `= 5.0.0 =` should appear exactly twice: once in Upgrade Notice,
			// once in Changelog.
			const fiveZero = readme.match(/= 5\.0\.0 =/g) || [];
			assert(
				fiveZero.length === 2,
				`Expected exactly 2 occurrences of "= 5.0.0 =" (one per section), got ${fiveZero.length}`
			);
		}),

	// Test 8: Scoped breaking changes (e.g., **resolver:**)
	() =>
		test('preserves scope prefix in breaking changes', () => {
			createChangelog(`# Changelog

## [3.0.0](https://github.com/wp-graphql/wp-graphql/compare/v2.0.0...v3.0.0) (2026-01-20)

### ⚠ BREAKING CHANGES

* **resolver:** Change resolver behavior ([#130](https://github.com/wp-graphql/wp-graphql/pull/130))
* **types:** Remove deprecated type ([#131](https://github.com/wp-graphql/wp-graphql/pull/131))
`);

			createReadme(`=== Test Plugin ===
Stable tag: 2.0.0

== Changelog ==

= 2.0.0 =
* Initial release
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');

			const readme = readReadme();
			assert(
				readme.includes('**resolver:**'),
				'Should preserve resolver scope'
			);
			assert(
				readme.includes('**types:**'),
				'Should preserve types scope'
			);
		}),
];

// ===========================================
// RUN TESTS
// ===========================================

console.log('\n🧪 Running update-upgrade-notice.js tests...\n');

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

// Exit with error code if any tests failed
process.exit(failed > 0 ? 1 : 0);
