#!/usr/bin/env node

/**
 * Tests for discard-timestamp-only-changes.js
 *
 * Run with: node scripts/discard-timestamp-only-changes.test.js
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const assert = require('assert');
const {
	stripTimestamps,
	isTimestampOnlyChange,
	discardTimestampOnlyChanges,
} = require('./discard-timestamp-only-changes');

const SCRIPT_PATH = path.join(__dirname, 'discard-timestamp-only-changes.js');

const indexJson = (generatedAt, hooks) =>
	`${JSON.stringify({ generatedAt, hooks }, null, 2)}\n`;

const pot = (date, msgid) =>
	[
		'msgid ""',
		'msgstr ""',
		'"Project-Id-Version: WPGraphQL 2.25.0\\n"',
		`"POT-Creation-Date: ${date}\\n"`,
		'"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"',
		'',
		`msgid "${msgid}"`,
		'msgstr ""',
		'',
	].join('\n');

function git(args, cwd) {
	return execFileSync('git', args, { cwd, encoding: 'utf8' });
}

// A throwaway repository with one commit holding the given files.
function makeRepo(files) {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'discard-timestamps-'));
	git(['init', '-q'], dir);
	git(['config', 'user.email', 'test@example.com'], dir);
	git(['config', 'user.name', 'Test'], dir);
	git(['config', 'commit.gpgsign', 'false'], dir);
	Object.entries(files).forEach(([file, content]) => {
		fs.mkdirSync(path.dirname(path.join(dir, file)), { recursive: true });
		fs.writeFileSync(path.join(dir, file), content);
	});
	git(['add', '-A'], dir);
	git(['commit', '-q', '--no-verify', '-m', 'initial'], dir);
	return dir;
}

function write(dir, file, content) {
	fs.writeFileSync(path.join(dir, file), content);
}

function read(dir, file) {
	return fs.readFileSync(path.join(dir, file), 'utf8');
}

let passed = 0;
function test(name, fn) {
	fn();
	passed++;
	console.log(`✓ ${name}`);
}

console.log('Testing discard-timestamp-only-changes.js\n');

test('stripTimestamps blanks generatedAt and POT-Creation-Date', () => {
	assert.strictEqual(
		stripTimestamps(indexJson('2026-10-20T22:56:59.265Z', [])),
		indexJson('', [])
	);
	assert.strictEqual(
		stripTimestamps(pot('2026-10-20T22:56:59+00:00', 'Hello')),
		pot('', 'Hello').replace(
			'"POT-Creation-Date: \\n"',
			'"POT-Creation-Date: "'
		)
	);
});

test('isTimestampOnlyChange is true only for a timestamp-only difference', () => {
	const before = indexJson('2026-10-20T22:56:59.265Z', ['a']);
	assert.strictEqual(
		isTimestampOnlyChange(
			before,
			indexJson('2026-10-20T23:00:58.356Z', ['a'])
		),
		true
	);
	// A real change, with or without a new timestamp.
	assert.strictEqual(
		isTimestampOnlyChange(
			before,
			indexJson('2026-10-20T23:00:58.356Z', ['a', 'b'])
		),
		false
	);
	assert.strictEqual(
		isTimestampOnlyChange(
			before,
			indexJson('2026-10-20T22:56:59.265Z', ['b'])
		),
		false
	);
	// Identical content is not a change at all.
	assert.strictEqual(isTimestampOnlyChange(before, before), false);
	// The POT header date.
	assert.strictEqual(
		isTimestampOnlyChange(
			pot('2026-10-20T22:56:59+00:00', 'Hello'),
			pot('2026-10-20T23:00:59+00:00', 'Hello')
		),
		true
	);
	assert.strictEqual(
		isTimestampOnlyChange(
			pot('2026-10-20T22:56:59+00:00', 'Hello'),
			pot('2026-10-20T23:00:59+00:00', 'Goodbye')
		),
		false
	);
});

test('a "generatedAt" key that is not the timestamp shape still counts as content', () => {
	// A number value does not match the pattern, so a change to it is real.
	assert.strictEqual(
		isTimestampOnlyChange('{ "generatedAt": 1 }', '{ "generatedAt": 2 }'),
		false
	);
});

test('restores timestamp-only files and keeps real changes', () => {
	const dir = makeRepo({
		'plugin/docs/generated/hooks-index.json': indexJson(
			'2026-10-20T22:56:59.136Z',
			['a']
		),
		'plugin/docs/generated/functions-index.json': indexJson(
			'2026-10-20T22:56:59.265Z',
			['f']
		),
		'plugin/languages/plugin.pot': pot(
			'2026-10-20T22:56:59+00:00',
			'Hello'
		),
		'plugin/readme.txt': 'Stable tag: 2.24.2\n',
		'site/recipes-index.json': indexJson('2026-10-20T22:56:59.351Z', ['r']),
	});
	try {
		write(
			dir,
			'plugin/docs/generated/hooks-index.json',
			indexJson('2026-10-20T23:00:58.131Z', ['a'])
		);
		write(
			dir,
			'plugin/docs/generated/functions-index.json',
			indexJson('2026-10-20T23:00:58.356Z', ['f', 'g'])
		);
		write(
			dir,
			'plugin/languages/plugin.pot',
			pot('2026-10-20T23:00:59+00:00', 'Hello')
		);
		write(dir, 'plugin/readme.txt', 'Stable tag: 2.25.0\n');
		write(
			dir,
			'site/recipes-index.json',
			indexJson('2026-10-20T23:00:58.525Z', ['r'])
		);

		const logs = [];
		const result = discardTimestampOnlyChanges({
			paths: ['plugin', 'site/recipes-index.json'],
			cwd: dir,
			log: (message) => logs.push(message),
		});

		assert.deepStrictEqual(result.restored.sort(), [
			'plugin/docs/generated/hooks-index.json',
			'plugin/languages/plugin.pot',
			'site/recipes-index.json',
		]);
		assert.deepStrictEqual(result.kept.sort(), [
			'plugin/docs/generated/functions-index.json',
			'plugin/readme.txt',
		]);
		assert.deepStrictEqual(result.skipped, []);

		// The restored files are back to HEAD, the kept ones still changed.
		const stillChanged = git(['diff', 'HEAD', '--name-only'], dir)
			.trim()
			.split('\n')
			.sort();
		assert.deepStrictEqual(stillChanged, [
			'plugin/docs/generated/functions-index.json',
			'plugin/readme.txt',
		]);
		// A real change keeps its new timestamp.
		assert.ok(
			read(dir, 'plugin/docs/generated/functions-index.json').includes(
				'2026-10-20T23:00:58.356Z'
			)
		);
		assert.strictEqual(logs.length, 3);
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
	}
});

test('only looks under the given paths', () => {
	const dir = makeRepo({
		'plugin/hooks-index.json': indexJson('2026-10-20T22:56:59.136Z', []),
		'other/hooks-index.json': indexJson('2026-10-20T22:56:59.136Z', []),
	});
	try {
		write(
			dir,
			'plugin/hooks-index.json',
			indexJson('2026-10-20T23:00:58.131Z', [])
		);
		write(
			dir,
			'other/hooks-index.json',
			indexJson('2026-10-20T23:00:58.131Z', [])
		);

		const result = discardTimestampOnlyChanges({
			paths: ['plugin'],
			cwd: dir,
			log: () => {},
		});

		assert.deepStrictEqual(result.restored, ['plugin/hooks-index.json']);
		assert.ok(
			read(dir, 'other/hooks-index.json').includes(
				'2026-10-20T23:00:58.131Z'
			)
		);
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
	}
});

test('ignores new and deleted files', () => {
	const dir = makeRepo({
		'plugin/old-index.json': indexJson('2026-10-20T22:56:59.136Z', []),
	});
	try {
		fs.rmSync(path.join(dir, 'plugin/old-index.json'));
		write(
			dir,
			'plugin/new-index.json',
			indexJson('2026-10-20T23:00:58.131Z', [])
		);
		git(['add', '-N', 'plugin/new-index.json'], dir);

		const result = discardTimestampOnlyChanges({
			paths: ['plugin'],
			cwd: dir,
			log: () => {},
		});

		assert.deepStrictEqual(result, { restored: [], kept: [], skipped: [] });
		assert.ok(!fs.existsSync(path.join(dir, 'plugin/old-index.json')));
		assert.ok(fs.existsSync(path.join(dir, 'plugin/new-index.json')));
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
	}
});

test('CLI restores timestamp-only files and exits 0', () => {
	const dir = makeRepo({
		'plugin/hooks-index.json': indexJson('2026-10-20T22:56:59.136Z', []),
	});
	try {
		write(
			dir,
			'plugin/hooks-index.json',
			indexJson('2026-10-20T23:00:58.131Z', [])
		);

		const output = execFileSync(
			'node',
			[SCRIPT_PATH, '--paths=plugin', `--cwd=${dir}`],
			{
				encoding: 'utf8',
			}
		);

		assert.ok(output.includes('Restored plugin/hooks-index.json'));
		assert.ok(output.includes('Restored 1 timestamp-only file(s), kept 0'));
		assert.strictEqual(
			git(['diff', 'HEAD', '--name-only'], dir).trim(),
			''
		);
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
	}
});

test('CLI distinguishes no paths, nothing modified, and an unreadable repository', () => {
	const noPaths = execFileSync('node', [SCRIPT_PATH], { encoding: 'utf8' });
	assert.ok(noPaths.includes('No --paths given'));

	const dir = makeRepo({ 'plugin/hooks-index.json': indexJson('x', []) });
	const notRepo = fs.mkdtempSync(
		path.join(os.tmpdir(), 'discard-timestamps-norepo-')
	);
	try {
		const clean = execFileSync(
			'node',
			[SCRIPT_PATH, '--paths=plugin', `--cwd=${dir}`],
			{
				encoding: 'utf8',
			}
		);
		assert.ok(clean.includes('No modified files under the given paths.'));

		// Best-effort: a failure to list changes is logged and still exits 0.
		const failed = execFileSync(
			'node',
			[SCRIPT_PATH, '--paths=plugin', `--cwd=${notRepo}`],
			{
				encoding: 'utf8',
				stdio: ['ignore', 'pipe', 'pipe'],
			}
		);
		assert.ok(failed.includes('Could not list changed files'));
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
		fs.rmSync(notRepo, { recursive: true, force: true });
	}
});

console.log(`\nAll ${passed} tests passed.`);
