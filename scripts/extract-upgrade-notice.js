#!/usr/bin/env node

/**
 * Extracts a release's upgrade notice from a plugin's readme.txt, and optionally adds it to the
 * top of that release's GitHub release notes.
 *
 * Usage:
 *   node scripts/extract-upgrade-notice.js --version=2.1.0 --plugin-dir=plugins/wp-graphql
 *   node scripts/extract-upgrade-notice.js --version=2.1.0 --plugin-dir=plugins/wp-graphql \
 *     --release-notes=current-notes.md --output=updated-notes.md
 *
 * Options:
 *   --version        Version to extract (required, a leading `v` is stripped)
 *   --plugin-dir     Plugin directory containing readme.txt (default: plugins/wp-graphql)
 *   --readme         Path to readme.txt, instead of --plugin-dir
 *   --release-notes  File holding the release's current notes. With --output, writes the notes
 *                    with the upgrade notice added to --output, and writes nothing when there is
 *                    no notice or the notes already include it.
 *   --output         Where to write the updated release notes (requires --release-notes)
 *
 * Without --release-notes, prints the notice to stdout, or nothing when there isn't one.
 *
 * The notices in our readme.txt files use Markdown that GitHub renders the same way (bold, code,
 * links, lists and `#1234` issue references), so the text is used as written.
 */

const fs = require('fs');
const path = require('path');

const NOTICE_HEADING = '### ⚠️ Upgrade Notice';

/**
 * Parses `--key=value` arguments, splitting on the first `=` only, since values can contain one.
 *
 * @param {string[]} argv Arguments after the script name.
 * @returns {Object<string, string|boolean>} The parsed arguments.
 */
function parseArgs(argv) {
	const args = {};
	for (const arg of argv) {
		if (!arg.startsWith('--')) {
			continue;
		}
		const eq = arg.indexOf('=');
		if (eq === -1) {
			args[arg.slice(2)] = true;
		} else {
			args[arg.slice(2, eq)] = arg.slice(eq + 1);
		}
	}
	return args;
}

function escapeRegExp(string) {
	return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Returns the upgrade notice for a version, the text of the `= X.Y.Z =` entry under
 * `== Upgrade Notice ==`, without its heading. Entries with the same version in other sections,
 * like the changelog, are ignored.
 *
 * @param {string} readmeContent The contents of readme.txt.
 * @param {string} version       The version, with or without a leading `v`.
 * @returns {string} The notice, or an empty string when the version has none.
 */
function extractUpgradeNotice(readmeContent, version) {
	if (typeof readmeContent !== 'string' || !readmeContent || !version) {
		return '';
	}

	const content = readmeContent.replace(/\r\n/g, '\n');
	const section = content.match(/(?:^|\n)== Upgrade Notice ==\n([\s\S]*?)(?=\n== |$)/);
	if (!section) {
		return '';
	}

	const escapedVersion = escapeRegExp(String(version).replace(/^v/i, ''));
	const entry = section[1].match(new RegExp(`(?:^|\\n)=\\s*${escapedVersion}\\s*=\\n([\\s\\S]*?)(?=\\n=\\s*\\d+\\.\\d+\\.\\d+(?:-[\\w.-]+)?\\s*=|$)`));

	return entry ? entry[1].trim() : '';
}

/**
 * Adds an upgrade notice to the top of a release's notes.
 *
 * @param {string} currentNotes The release's current notes.
 * @param {string} notice       The upgrade notice.
 * @returns {{ notes: string, reason: 'added'|'no-notice'|'already-included' }} The updated notes
 *   and why, so each outcome can be logged on its own.
 */
function buildReleaseNotes(currentNotes, notice) {
	const notes = typeof currentNotes === 'string' ? currentNotes : '';

	if (!notice) {
		return { notes, reason: 'no-notice' };
	}

	// A rerun of the release job, or a re-deploy of an existing tag, must not add it twice.
	if (notes.includes(NOTICE_HEADING)) {
		return { notes, reason: 'already-included' };
	}

	return {
		notes: `${NOTICE_HEADING}\n\n${notice}\n\n---\n\n${notes}`,
		reason: 'added',
	};
}

function main() {
	const args = parseArgs(process.argv.slice(2));

	if (!args.version || args.version === true) {
		console.error('Error: --version is required.');
		process.exit(1);
	}

	if (args.output && !args['release-notes']) {
		console.error('Error: --output requires --release-notes.');
		process.exit(1);
	}

	const readmePath = args.readme
		? path.resolve(process.cwd(), String(args.readme))
		: path.resolve(process.cwd(), String(args['plugin-dir'] || 'plugins/wp-graphql'), 'readme.txt');

	// Every plugin we release has a readme.txt, so a missing one means a wrong path, not "no
	// notice". Say so on its own line, but exit 0: the caller treats the notice as best-effort.
	if (!fs.existsSync(readmePath)) {
		console.error(`::warning::No readme.txt at ${readmePath}, so no upgrade notice was added.`);
		process.exit(0);
	}

	const notice = extractUpgradeNotice(fs.readFileSync(readmePath, 'utf8'), String(args.version));

	if (!args['release-notes']) {
		if (notice) {
			process.stdout.write(`${notice}\n`);
		}
		process.exit(0);
	}

	// The workflow just wrote this file, so failing to read it is unexpected: let it throw.
	const currentNotes = fs.readFileSync(path.resolve(process.cwd(), String(args['release-notes'])), 'utf8');
	const { notes, reason } = buildReleaseNotes(currentNotes, notice);

	if (reason === 'no-notice') {
		console.log(`No upgrade notice for ${args.version} in ${readmePath}, release notes unchanged.`);
	} else if (reason === 'already-included') {
		console.log(`The release notes already include the upgrade notice for ${args.version}, left unchanged.`);
	} else {
		if (args.output) {
			fs.writeFileSync(path.resolve(process.cwd(), String(args.output)), notes);
		}
		console.log(`Added the upgrade notice for ${args.version} to the release notes.`);
	}
	process.exit(0);
}

if (require.main === module) {
	main();
}

module.exports = {
	NOTICE_HEADING,
	buildReleaseNotes,
	extractUpgradeNotice,
	parseArgs,
};
