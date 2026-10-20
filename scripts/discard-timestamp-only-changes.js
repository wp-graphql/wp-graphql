#!/usr/bin/env node

/**
 * Discard working-tree changes that only touch a generation timestamp.
 *
 * The release PR sync (update-release-pr.yml) regenerates the hook, function
 * and recipe indexes and the POT file on every run. Each of those stamps the
 * time it was generated (`"generatedAt"` in the JSON indexes,
 * `POT-Creation-Date` in the POT header), so a regeneration with no real
 * content change still leaves a diff. The workflow then commits and pushes
 * that diff, the push triggers the workflow again, and the loop only stops
 * because GitHub holds bot-triggered runs for approval. Each new commit also
 * cancels the CI already running on the release PR.
 *
 * This restores every modified file under the given paths whose only
 * difference from HEAD is one of those timestamps, so the change check that
 * follows sees a real change or nothing. A file with a real change keeps its
 * new timestamp along with it.
 *
 * Usage:
 *   node scripts/discard-timestamp-only-changes.js --paths=plugins/wp-graphql,websites/wpgraphql.com/src/generated
 *
 * Options:
 *   --paths   Comma-separated paths to check, relative to the repository
 *             root. Required.
 *   --cwd     Repository root (default: the current directory). Lets the
 *             tests run against a throwaway repository.
 *
 * Best-effort by design: this only saves a redundant commit, so it never fails
 * the release sync. A file it cannot compare is logged and left as it is,
 * which falls back to committing it, the behavior before this script existed.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

/**
 * Patterns for the timestamps generators write. Each match is replaced with a
 * fixed value before two versions of a file are compared.
 */
const TIMESTAMP_PATTERNS = [
	// JSON indexes from scripts/{hooks,functions,recipes}/generate-*-docs.js.
	{ pattern: /"generatedAt":\s*"[^"]*"/g, replacement: '"generatedAt": ""' },
	// POT header written by `wp i18n make-pot`.
	{
		pattern: /^"POT-Creation-Date: [^"]*"$/gm,
		replacement: '"POT-Creation-Date: "',
	},
];

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		// Split on the first "=" only, so values that themselves contain "="
		// survive intact.
		const eq = arg.indexOf('=');
		if (arg.startsWith('--') && eq !== -1) {
			args[arg.slice(2, eq)] = arg.slice(eq + 1);
		}
	});
	return args;
}

/**
 * Replace every generation timestamp in the content with a fixed value.
 *
 * @param {string} content File content.
 * @return {string} The content with timestamps blanked.
 */
function stripTimestamps(content) {
	return TIMESTAMP_PATTERNS.reduce(
		(result, { pattern, replacement }) =>
			result.replace(pattern, () => replacement),
		content
	);
}

/**
 * Whether two versions of a file differ, and differ only in timestamps.
 *
 * @param {string} before Content at HEAD.
 * @param {string} after  Content in the working tree.
 * @return {boolean} True when the versions differ and are equal once
 *                   timestamps are blanked.
 */
function isTimestampOnlyChange(before, after) {
	return before !== after && stripTimestamps(before) === stripTimestamps(after);
}

function git(args, cwd) {
	return execFileSync('git', args, {
		cwd,
		encoding: 'utf8',
		stdio: ['ignore', 'pipe', 'pipe'],
		maxBuffer: 64 * 1024 * 1024,
	});
}

/**
 * Restore every modified file under `paths` whose only change is a timestamp.
 *
 * @param {Object}   options
 * @param {string[]} options.paths Paths to check, relative to `cwd`.
 * @param {string}   options.cwd   Repository root.
 * @param {Function} options.log   Logger.
 * @return {{restored: string[], kept: string[], skipped: string[]}} What
 *         happened to each modified file.
 */
function discardTimestampOnlyChanges({ paths, cwd, log = console.log }) {
	const result = { restored: [], kept: [], skipped: [] };

	const modified = git(
		['diff', 'HEAD', '--name-only', '--diff-filter=M', '--', ...paths],
		cwd
	)
		.split('\n')
		.filter(Boolean);

	modified.forEach((file) => {
		let before;
		let after;
		try {
			before = git(['show', `HEAD:${file}`], cwd);
			after = fs.readFileSync(path.join(cwd, file), 'utf8');
		} catch (error) {
			log(`Could not compare ${file}, leaving it changed: ${error.message}`);
			result.skipped.push(file);
			return;
		}

		if (!isTimestampOnlyChange(before, after)) {
			result.kept.push(file);
			return;
		}

		try {
			git(['checkout', 'HEAD', '--', file], cwd);
			log(`Restored ${file}: only its generation timestamp changed.`);
			result.restored.push(file);
		} catch (error) {
			log(`Could not restore ${file}, leaving it changed: ${error.message}`);
			result.skipped.push(file);
		}
	});

	return result;
}

function main() {
	const args = parseArgs();
	const paths = (args.paths || '')
		.split(',')
		.map((p) => p.trim())
		.filter(Boolean);

	if (paths.length === 0) {
		console.log(
			'No --paths given; nothing to check (is the workflow passing --paths?).'
		);
		process.exit(0);
	}

	let result;
	try {
		result = discardTimestampOnlyChanges({
			paths,
			cwd: args.cwd || process.cwd(),
		});
	} catch (error) {
		// Listing the changed files failed, so nothing was restored and the
		// sync falls back to committing whatever changed.
		console.log(
			`Could not list changed files, leaving every change in place: ${error.message}`
		);
		process.exit(0);
	}

	const { restored, kept, skipped } = result;
	if (restored.length + kept.length + skipped.length === 0) {
		console.log('No modified files under the given paths.');
	} else {
		console.log(
			`Restored ${restored.length} timestamp-only file(s), kept ${kept.length} with real changes, could not check ${skipped.length}.`
		);
	}
}

if (require.main === module) {
	main();
}

module.exports = { stripTimestamps, isTimestampOnlyChange, discardTimestampOnlyChanges };
