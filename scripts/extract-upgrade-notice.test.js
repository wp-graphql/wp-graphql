#!/usr/bin/env node

/**
 * Tests for extract-upgrade-notice.js
 *
 * Run with: node scripts/extract-upgrade-notice.test.js
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const assert = require('assert');
const {
	NOTICE_HEADING,
	buildReleaseNotes,
	extractUpgradeNotice,
	parseArgs,
} = require('./extract-upgrade-notice');

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

function writeFile(name, content) {
	const filePath = path.join(TEST_DIR, name);
	fs.writeFileSync(filePath, content);
	return filePath;
}

// Runs the real CLI, passing arguments as an array so nothing goes through a shell.
function runScript(args) {
	const result = spawnSync(process.execPath, [SCRIPT_PATH, ...args], {
		cwd: path.join(__dirname, '..'),
		encoding: 'utf8',
	});
	return { status: result.status, stdout: result.stdout || '', stderr: result.stderr || '' };
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

= 2.2.0 =
* Changelog entry for 2.2.0, a version with no upgrade notice

= 2.1.0 =
* Changelog entry for 2.1.0
`;

const tests = [
	() =>
		test('extracts upgrade notice for exact version match', () => {
			const notice = extractUpgradeNotice(SAMPLE_README, '2.1.0');
			assert(notice.startsWith('**⚠️ BREAKING CHANGES**'), 'Should start with the notice, not its heading');
			assert(notice.includes('#1234'), 'Should preserve issue link/number');
			assert(notice.includes('`schema`'), 'Should preserve code backticks');
			assert(notice.includes('* Update your queries'), 'Should preserve bullet points');
		}),

	() =>
		test('stops at the next version heading', () => {
			const notice = extractUpgradeNotice(SAMPLE_README, '2.1.0');
			assert(!notice.includes('Older upgrade notice'), 'Should not include the 2.0.0 notice');
			assert(!notice.includes('= 2.0.0 ='), 'Should not include the next heading');
		}),

	() =>
		test('extracts the last notice in the section, up to the next section', () => {
			const notice = extractUpgradeNotice(SAMPLE_README, '2.0.0');
			assert.strictEqual(notice, '* Older upgrade notice without breaking marker.');
		}),

	() =>
		test('handles version strings with a leading v', () => {
			const notice = extractUpgradeNotice(SAMPLE_README, 'v2.1.0');
			assert(notice.includes('**⚠️ BREAKING CHANGES**'), 'Should extract notice with leading v');
		}),

	() =>
		test('ignores a changelog entry for a version with no upgrade notice', () => {
			assert.strictEqual(extractUpgradeNotice(SAMPLE_README, '2.2.0'), '');
		}),

	() =>
		test('returns empty string when version has no notice in readme.txt', () => {
			assert.strictEqual(extractUpgradeNotice(SAMPLE_README, '2.5.0'), '');
		}),

	() =>
		test('returns empty string when == Upgrade Notice == section is missing', () => {
			const readme = `=== WPGraphQL ===\nStable tag: 1.0.0\n\n== Changelog ==\n= 1.0.0 =\n* Initial release\n`;
			assert.strictEqual(extractUpgradeNotice(readme, '1.0.0'), '');
		}),

	() =>
		test('does not match a version that only contains the requested one', () => {
			const readme = `== Upgrade Notice ==\n\n= 12.1.0 =\n\nNot this one.\n\n= 2.1.0-beta.1 =\n\nNor this one.\n`;
			assert.strictEqual(extractUpgradeNotice(readme, '2.1.0'), '');
		}),

	() =>
		test('handles Windows line endings', () => {
			const notice = extractUpgradeNotice(SAMPLE_README.replace(/\n/g, '\r\n'), '2.1.0');
			assert(notice.startsWith('**⚠️ BREAKING CHANGES**'), 'Should extract the notice');
			assert(!notice.includes('\r'), 'Should not keep carriage returns');
		}),

	() =>
		test('adds the notice to the top of the release notes', () => {
			const { notes, reason } = buildReleaseNotes('## Changelog\n\n* A fix', 'Read this first.');
			assert.strictEqual(reason, 'added');
			assert.strictEqual(notes, `${NOTICE_HEADING}\n\nRead this first.\n\n---\n\n## Changelog\n\n* A fix`);
		}),

	() =>
		test('leaves release notes unchanged when there is no notice', () => {
			const { notes, reason } = buildReleaseNotes('## Changelog', '');
			assert.strictEqual(reason, 'no-notice');
			assert.strictEqual(notes, '## Changelog');
		}),

	() =>
		test('does not add the notice twice, for a rerun or a re-deploy', () => {
			const once = buildReleaseNotes('## Changelog', 'Read this first.').notes;
			const { notes, reason } = buildReleaseNotes(once, 'Read this first.');
			assert.strictEqual(reason, 'already-included');
			assert.strictEqual(notes, once);
		}),

	() =>
		test('parses --key=value, splitting on the first = only', () => {
			const args = parseArgs(['--version=2.1.0', '--readme=a=b/readme.txt', '--flag']);
			assert.strictEqual(args.version, '2.1.0');
			assert.strictEqual(args.readme, 'a=b/readme.txt');
			assert.strictEqual(args.flag, true);
		}),

	() =>
		test('CLI prints the notice for a version', () => {
			const readmePath = writeFile('readme.txt', SAMPLE_README);
			const result = runScript(['--version=2.1.0', `--readme=${readmePath}`]);
			assert.strictEqual(result.status, 0);
			assert(result.stdout.includes('BREAKING CHANGES'), 'CLI output should contain upgrade notice');
		}),

	() =>
		test('CLI warns, without failing, when readme.txt is missing', () => {
			const result = runScript(['--version=2.1.0', `--plugin-dir=${path.join(TEST_DIR, 'no-such-plugin')}`]);
			assert.strictEqual(result.status, 0, 'Should exit 0, the notice is best-effort');
			assert.strictEqual(result.stdout, '', 'Should print no notice');
			assert(result.stderr.includes('::warning::No readme.txt at'), 'Should warn about the missing readme.txt');
		}),

	() =>
		test('CLI requires --version', () => {
			const result = runScript([]);
			assert.strictEqual(result.status, 1);
		}),

	() =>
		test('CLI writes updated release notes, and nothing on a second run', () => {
			const readmePath = writeFile('readme.txt', SAMPLE_README);
			const currentPath = writeFile('current.md', '## Changelog\n\n* A fix\n');
			const outputPath = path.join(TEST_DIR, 'updated.md');

			const first = runScript(['--version=2.1.0', `--readme=${readmePath}`, `--release-notes=${currentPath}`, `--output=${outputPath}`]);
			assert.strictEqual(first.status, 0);
			const updated = fs.readFileSync(outputPath, 'utf8');
			assert(updated.startsWith(NOTICE_HEADING), 'Should start with the notice heading');
			assert(updated.endsWith('## Changelog\n\n* A fix\n'), 'Should keep the current notes below');

			fs.rmSync(outputPath);
			writeFile('current.md', updated);
			const second = runScript(['--version=2.1.0', `--readme=${readmePath}`, `--release-notes=${currentPath}`, `--output=${outputPath}`]);
			assert.strictEqual(second.status, 0);
			assert(!fs.existsSync(outputPath), 'Should not write anything when the notice is already there');
			assert(second.stdout.includes('already include'), 'Should say why nothing changed');
		}),

	() =>
		test('CLI writes nothing when the version has no notice', () => {
			const readmePath = writeFile('readme.txt', SAMPLE_README);
			const currentPath = writeFile('current.md', '## Changelog\n');
			const outputPath = path.join(TEST_DIR, 'updated.md');
			const result = runScript(['--version=2.5.0', `--readme=${readmePath}`, `--release-notes=${currentPath}`, `--output=${outputPath}`]);
			assert.strictEqual(result.status, 0);
			assert(!fs.existsSync(outputPath), 'Should not write anything');
			assert(result.stdout.includes('No upgrade notice'), 'Should say there was no notice');
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
