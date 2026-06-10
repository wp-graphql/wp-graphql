#!/usr/bin/env node

/**
 * Sync one version from CHANGELOG.md to readme.txt changelog format.
 *
 * Usage:
 *   node scripts/update-readme-changelog.js --version=1.2.3 --plugin-dir=plugins/wp-graphql
 */

const fs = require('fs');
const path = require('path');

function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		const [key, value] = arg.replace(/^--/, '').split('=');
		args[key] = value;
	});
	return args;
}

function escapeRegExp(value) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function getVersionFromHeading(line) {
	const trimmed = line.trim();
	const bracketed = trimmed.match(/^##\s+\[v?([0-9]+\.[0-9]+\.[0-9]+(?:-[\w.-]+)?)\]/i);
	if (bracketed) {
		return bracketed[1];
	}

	const plain = trimmed.match(/^##\s+v?([0-9]+\.[0-9]+\.[0-9]+(?:-[\w.-]+)?)(?:\s|$)/i);
	return plain ? plain[1] : null;
}

function extractVersionSection(changelogContent, version) {
	const lines = changelogContent.split('\n');
	const targetVersion = version.toLowerCase();
	let start = -1;
	let end = lines.length;

	for (let i = 0; i < lines.length; i++) {
		const line = lines[i];
		if (!line.trim().startsWith('## ')) {
			continue;
		}
		const foundVersion = getVersionFromHeading(line);
		if (foundVersion && foundVersion.toLowerCase() === targetVersion) {
			start = i + 1;
			break;
		}
	}

	if (start === -1) {
		return null;
	}

	for (let i = start; i < lines.length; i++) {
		if (lines[i].trim().startsWith('## ')) {
			end = i;
			break;
		}
	}

	return lines.slice(start, end).join('\n').trim();
}

function stripTrailingCommitHashLink(line) {
	return line.replace(
		/\s+\(\[[a-f0-9]{7,40}\]\([^)]+\/commit\/[a-f0-9]{7,40}\)\)\s*$/i,
		''
	);
}

function normalizeMarkdownLine(line) {
	const trimmed = line.trim();
	if (!trimmed) {
		return '';
	}

	if (trimmed.startsWith('### ')) {
		return `**${trimmed.replace(/^###\s+/, '').trim()}**`;
	}

	if (trimmed.startsWith('#### ')) {
		return `**${trimmed.replace(/^####\s+/, '').trim()}**`;
	}

	if (trimmed.startsWith('> ')) {
		const note = trimmed.replace(/^>\s*/, '');
		return note;
	}

	if (/^[-*]\s+/.test(trimmed)) {
		const withoutCommitHash = stripTrailingCommitHashLink(trimmed);
		const normalizedBullet = withoutCommitHash.replace(/^-\s+/, '* ');
		return normalizedBullet;
	}

	return stripTrailingCommitHashLink(trimmed);
}

function normalizeSectionForReadme(sectionContent) {
	const lines = sectionContent.split('\n');
	const output = [];
	let previousBlank = true;

	lines.forEach((line) => {
		const normalized = normalizeMarkdownLine(line);

		if (!normalized) {
			if (!previousBlank) {
				output.push('');
			}
			previousBlank = true;
			return;
		}

		output.push(normalized);
		previousBlank = false;
	});

	while (output.length > 0 && output[output.length - 1] === '') {
		output.pop();
	}

	return output.join('\n');
}

function buildReadmeVersionBlock(version, normalizedSection) {
	let block = `= ${version} =\n\n`;
	if (normalizedSection.trim()) {
		block += `${normalizedSection.trim()}\n`;
	} else {
		block += '* No changelog details provided.\n';
	}
	return block;
}

function normalizeChangelogSpacing(readmeContent) {
	const headingRegex = /^== Changelog ==[ \t]*$/m;
	const headingMatch = readmeContent.match(headingRegex);

	if (!headingMatch) {
		return readmeContent;
	}

	const sectionStart = headingMatch.index + headingMatch[0].length;
	const afterHeading = readmeContent.slice(sectionStart);
	const nextHeadingMatch = afterHeading.match(/^== [^=\n].*? ==\s*$/m);
	const sectionEnd = nextHeadingMatch
		? sectionStart + nextHeadingMatch.index
		: readmeContent.length;

	const prefix = readmeContent.slice(0, sectionStart);
	let sectionBody = readmeContent.slice(sectionStart, sectionEnd);
	const suffix = readmeContent.slice(sectionEnd);

	const lines = sectionBody.split('\n');
	const collapsed = [];
	let previousWasBlank = false;

	for (const line of lines) {
		const isBlank = line.trim() === '';
		if (isBlank) {
			if (!previousWasBlank) {
				collapsed.push('');
			}
			previousWasBlank = true;
		} else {
			collapsed.push(line);
			previousWasBlank = false;
		}
	}

	sectionBody = collapsed.join('\n').trim();
	sectionBody = sectionBody ? `\n\n${sectionBody}\n` : '\n\n';

	return `${prefix}${sectionBody}${suffix}`;
}

function upsertVersionInReadme(readmeContent, versionBlock, version) {
	const changelogHeadingRegex = /^== Changelog ==[ \t]*$/m;
	const headingMatch = readmeContent.match(changelogHeadingRegex);
	if (!headingMatch) {
		throw new Error('Could not find "== Changelog ==" section in readme.txt');
	}

	// Scope all changelog mutations to the slice that starts at the heading.
	// Everything before (Description, Upgrade Notice, …) is untouchable
	// prefix and can never be matched by the replace below — this is what
	// stops the bug where, on a release-please run, the FIRST `= X.Y.Z =`
	// in readme.txt is in the Upgrade Notice section (already written by
	// the prior `update-upgrade-notice.js` step) and the changelog block
	// silently overwrites it.
	const changelogStart = headingMatch.index;
	const prefix = readmeContent.slice(0, changelogStart);
	const changelogSection = readmeContent.slice(changelogStart);

	// Lookahead alternatives (all evaluated within `changelogSection`):
	//   `\n= [digit]…` — next sibling version heading
	//   `\n== `        — next major section heading
	//   `$`            — end of input (i.e. end of section / file)
	// Lookbehind `(?<=^|\n)` anchors to a line-start `= X.Y.Z =` heading
	// without consuming the preceding `\n`, so the replacement preserves it.
	//
	// (The prior implementation used `\Z` in the lookahead, which is NOT a
	// valid JS regex escape — it matched the literal character `Z`. When
	// neither sibling alternative fired, the lazy match failed entirely and
	// the script fell through to the `inserted` branch, prepending a
	// duplicate `= X.Y.Z =` block.)
	const versionPattern = new RegExp(
		`(?<=^|\\n)= ${escapeRegExp(version)} =[\\s\\S]*?(?=\\n= [0-9]+\\.[0-9]+\\.[0-9]+(?:-[\\w.-]+)? =\\s*(?:\\n|$)|\\n== |$)`
	);

	if (versionPattern.test(changelogSection)) {
		const updatedSection = changelogSection.replace(
			versionPattern,
			versionBlock.trimEnd() + '\n\n'
		);
		return {
			updatedContent: normalizeChangelogSpacing(prefix + updatedSection),
			action: 'updated',
		};
	}

	// Insert at the top of the Changelog section.
	const insertAt = headingMatch[0].length;
	const sectionPrefix = changelogSection.slice(0, insertAt);
	const sectionSuffix = changelogSection.slice(insertAt);

	return {
		updatedContent: normalizeChangelogSpacing(
			`${prefix}${sectionPrefix}\n\n${versionBlock.trimEnd()}\n\n${sectionSuffix.replace(/^\n+/, '')}`
		),
		action: 'inserted',
	};
}

function main() {
	const args = parseArgs();

	if (!args.version) {
		console.error('Error: --version is required');
		process.exit(1);
	}

	const pluginDir = args['plugin-dir'] || 'plugins/wp-graphql';
	const repoRoot = process.cwd();
	const changelogPath = path.join(repoRoot, pluginDir, 'CHANGELOG.md');
	const readmePath = path.join(repoRoot, pluginDir, 'readme.txt');

	if (!fs.existsSync(changelogPath)) {
		console.error(`CHANGELOG.md not found: ${changelogPath}`);
		process.exit(1);
	}

	if (!fs.existsSync(readmePath)) {
		console.error(`readme.txt not found: ${readmePath}`);
		process.exit(1);
	}

	const changelogContent = fs.readFileSync(changelogPath, 'utf8');
	const readmeContent = fs.readFileSync(readmePath, 'utf8');

	const section = extractVersionSection(changelogContent, args.version);
	if (!section) {
		console.log(`No changelog entry found for version ${args.version}`);
		process.exit(0);
	}

	const normalizedSection = normalizeSectionForReadme(section);
	const versionBlock = buildReadmeVersionBlock(args.version, normalizedSection);
	const { updatedContent, action } = upsertVersionInReadme(
		readmeContent,
		versionBlock,
		args.version
	);

	if (updatedContent === readmeContent) {
		console.log(`No changes needed for readme changelog ${args.version}`);
		process.exit(0);
	}

	fs.writeFileSync(readmePath, updatedContent);
	console.log(`Successfully ${action} readme changelog for version ${args.version}`);
}

if (require.main === module) {
	main();
}

module.exports = {
	extractVersionSection,
	normalizeSectionForReadme,
	buildReadmeVersionBlock,
	upsertVersionInReadme,
};
