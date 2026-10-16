#!/usr/bin/env node

/**
 * Tests for sync-board-from-prs.js
 *
 * Run with: node scripts/sync-board-from-prs.test.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { closingRefs, planMoves, isBot } = require('./sync-board-from-prs');

const SCRIPT = path.join(__dirname, 'sync-board-from-prs.js');
const TMP = path.join(__dirname, '..', '.test-sync-board');

let passed = 0;
function test(name, fn) {
	fn();
	passed++;
	console.log(`  ok  ${name}`);
}

console.log('closingRefs');

test('matches a bare reference', () => {
	assert.deepStrictEqual(closingRefs('Closes #1234', 'wp-graphql'), [1234]);
});

test('matches a colon-separated reference', () => {
	assert.deepStrictEqual(closingRefs('Fixes: #99', 'wp-graphql'), [99]);
});

test('matches past and present tense verbs', () => {
	assert.deepStrictEqual(closingRefs('Fixed #1', 'wp-graphql'), [1]);
	assert.deepStrictEqual(closingRefs('resolve #2', 'wp-graphql'), [2]);
	assert.deepStrictEqual(closingRefs('CLOSED #3', 'wp-graphql'), [3]);
});

test('matches a reference qualified to this repo', () => {
	// The form a cross-repo transfer leaves behind, and the one a naive
	// `closes #N` pattern silently misses.
	assert.deepStrictEqual(
		closingRefs('Closes wp-graphql/wp-graphql#4407', 'wp-graphql'),
		[4407]
	);
});

test('ignores a reference qualified to another repo', () => {
	assert.deepStrictEqual(
		closingRefs('Closes wp-graphql/wpgraphql-acf#240', 'wp-graphql'),
		[]
	);
});

test('ignores a mention with no closing keyword', () => {
	assert.deepStrictEqual(closingRefs('see #555 and #556', 'wp-graphql'), []);
});

test('ignores a keyword that is part of a longer word', () => {
	assert.deepStrictEqual(closingRefs('disclosed #7', 'wp-graphql'), []);
});

test('collects several references and de-duplicates', () => {
	assert.deepStrictEqual(
		closingRefs('Closes #1\nFixes #2\nCloses #1', 'wp-graphql'),
		[1, 2]
	);
});

test('survives a non-string body', () => {
	assert.deepStrictEqual(closingRefs(null, 'wp-graphql'), []);
	assert.deepStrictEqual(closingRefs(undefined, 'wp-graphql'), []);
});

test('is not affected by a previous call', () => {
	// A /g regex kept at module scope would carry lastIndex between calls and
	// drop matches on every other invocation.
	const body = 'Closes #10';
	assert.deepStrictEqual(closingRefs(body, 'wp-graphql'), [10]);
	assert.deepStrictEqual(closingRefs(body, 'wp-graphql'), [10]);
});

console.log('isBot');

test('recognizes bot authors', () => {
	assert.strictEqual(isBot('dependabot'), true);
	assert.strictEqual(isBot('dependabot[bot]'), true);
	assert.strictEqual(isBot('github-actions[bot]'), true);
	assert.strictEqual(isBot({ login: 'x', is_bot: true }), true);
});

test('leaves human authors alone', () => {
	assert.strictEqual(isBot('josephfusco'), false);
	assert.strictEqual(isBot('jasonbahl'), false);
	assert.strictEqual(isBot(undefined), false);
});

console.log('planMoves');

const FROM = ['Up Next', 'Planned', 'Inbox'];

test('moves an eligible item', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10' }],
		[{ id: 'I1', status: 'Up Next', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(moves, [
		{ itemId: 'I1', issue: 10, pr: 50, fromStatus: 'Up Next' },
	]);
});

test('leaves an item that is already In Progress', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10' }],
		[{ id: 'I1', status: 'In Progress', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(moves, []);
});

test('leaves an item that is already Done', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10' }],
		[{ id: 'I1', status: 'Done', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(moves, []);
});

test('skips draft pull requests', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10', isDraft: true }],
		[{ id: 'I1', status: 'Up Next', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(moves, []);
});

test('skips bot pull requests', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10', author: 'dependabot[bot]' }],
		[{ id: 'I1', status: 'Up Next', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(moves, []);
});

test('ignores an issue that is not on the board', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #999' }],
		[{ id: 'I1', status: 'Up Next', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(moves, []);
});

test('moves an issue only once when two PRs claim it', () => {
	const moves = planMoves(
		[
			{ number: 50, body: 'Closes #10' },
			{ number: 51, body: 'Closes #10' },
		],
		[{ id: 'I1', status: 'Up Next', number: 10 }],
		'wp-graphql',
		FROM
	);
	assert.strictEqual(moves.length, 1);
	assert.strictEqual(moves[0].pr, 50);
});

test('a PR closing several issues moves each of them', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10\nCloses #11' }],
		[
			{ id: 'I1', status: 'Up Next', number: 10 },
			{ id: 'I2', status: 'Planned', number: 11 },
		],
		'wp-graphql',
		FROM
	);
	assert.deepStrictEqual(
		moves.map((m) => m.issue),
		[10, 11]
	);
});

test('a narrowed --from list is honored', () => {
	const moves = planMoves(
		[{ number: 50, body: 'Closes #10' }],
		[{ id: 'I1', status: 'Inbox', number: 10 }],
		'wp-graphql',
		['Up Next']
	);
	assert.deepStrictEqual(moves, []);
});

console.log('cli');

test('--dry-run writes nothing and reports the move', () => {
	fs.mkdirSync(TMP, { recursive: true });
	const prs = path.join(TMP, 'prs.json');
	const items = path.join(TMP, 'items.json');
	fs.writeFileSync(prs, JSON.stringify([{ number: 50, body: 'Closes #10' }]));
	fs.writeFileSync(
		items,
		JSON.stringify([{ id: 'I1', status: 'Up Next', number: 10 }])
	);

	const out = execFileSync(
		'node',
		[
			SCRIPT,
			'--project=P',
			'--status-field=F',
			'--target-option=O',
			`--prs=${prs}`,
			`--items=${items}`,
			'--dry-run',
		],
		{ encoding: 'utf8' }
	);

	assert.match(out, /#10 Up Next -> target \(PR #50\)/);
	assert.match(out, /dry run, 1 would move/);
	fs.rmSync(TMP, { recursive: true, force: true });
});

test('exits non-zero when a required argument is missing', () => {
	let code = 0;
	try {
		execFileSync('node', [SCRIPT, '--project=P'], { stdio: 'pipe' });
	} catch (e) {
		code = e.status;
	}
	assert.strictEqual(code, 1);
});

console.log(`\n${passed} passed`);
