#!/usr/bin/env node

/**
 * Tests for extract-upgrade-notice.js
 *
 * Run with: node scripts/extract-upgrade-notice.test.js
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');
const { extractUpgradeNotice } = require('./extract-upgrade-notice');

const SCRIPT_PATH = path.join(__dirname, 'extract-upgrade-notice.js');
const TEST_DIR = path.join(__dirname, '..', '.test-extract-upgrade-notice');

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

function createReadme(content) {
	const readmePath = path.join(TEST_DIR, 'readme.txt');
	fs.writeFileSync(readmePath, content);
	return readmePath;
}

function runScript(args) {
	const cmd = `node "${SCRIPT_PATH}" ${args}`;
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

const SAMPLE_README = `=== WPGraphQL ===
Contributors: wp-graphql
Stable tag: 2.1.0

== Upgrade Notice ==

= 2.1.0 =
**⚠️ BREAKING CHANGES**: This release contains breaking changes.
* Update your queries ([#1234](https://github.com/wp-graphql/wp-graphql/pull/1234))
* Review \`schema\` definitions.

= 2.0.0 =
* Older upgrade notice without breaking marker.

== Changelog ==

= 2.1.0 =
* Changelog entry for 2.1.0
`;

const tests = [
	() =>
		test('extracts upgrade notice for exact version match', () => {
			const readmePath = createReadme(SAMPLE_README);
			const notice = extractUpgradeNotice('2.1.0', readmePath);
			assert(notice.includes('**⚠️ BREAKING CHANGES**'), 'Should include breaking changes warning');
			assert(notice.includes('#1234'), 'Should preserve issue link/number');
			assert(notice.includes('`schema`'), 'Should preserve code backticks');
		}),

	() =>
		test('handles version strings with a leading v', () => {
			const readmePath = createReadme(SAMPLE_README);
			const notice = extractUpgradeNotice('v2.1.0', readmePath);
			assert(notice.includes('**⚠️ BREAKING CHANGES**'), 'Should extract notice with leading v');
		}),

	() =>
		test('returns empty string when version has no notice in readme.txt', () => {
			const readmePath = createReadme(SAMPLE_README);
			const notice = extractUpgradeNotice('2.5.0', readmePath);
			assert.strictEqual(notice, '', 'Should return empty string for missing version notice');
		}),

	() =>
		test('returns empty string when == Upgrade Notice == section is missing', () => {
			const readmePath = createReadme(`=== WPGraphQL ===\nStable tag: 1.0.0\n\n== Changelog ==\n= 1.0.0 =\n* Initial release\n`);
			const notice = extractUpgradeNotice('1.0.0', readmePath);
			assert.strictEqual(notice, '', 'Should return empty string when Upgrade Notice section is missing');
		}),

	() =>
		test('preserves issue numbers, bold text, and unordered list items', () => {
			const readmePath = createReadme(SAMPLE_README);
			const notice = extractUpgradeNotice('2.1.0', readmePath);
			assert(notice.startsWith('**⚠️ BREAKING CHANGES**'), 'Should start with bold text');
			assert(notice.includes('* Update your queries'), 'Should preserve bullet point');
		}),

	() =>
		test('CLI invocation extracts upgrade notice correctly', () => {
			const readmePath = path.join(TEST_DIR, 'readme.txt');
			fs.writeFileSync(readmePath, SAMPLE_README);
			const result = runScript(`2.1.0 "${readmePath}"`);
			assert(result.success, 'CLI command should succeed');
			assert(result.output.includes('BREAKING CHANGES'), 'CLI output should contain upgrade notice');
		}),
];

console.log('\n🧪 Running extract-upgrade-notice.js tests...\n');

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
