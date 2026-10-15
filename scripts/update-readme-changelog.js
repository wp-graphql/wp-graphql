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

/**
 * Break release-please's version annotations so they survive in readme.txt.
 *
 * readme.txt is one of release-please's `extra-files` with `type: generic`, and
 * that updater rewrites the version on any line containing one of these
 * annotations. It cannot tell an annotation from a changelog entry whose own
 * prose happens to mention one, so such an entry gets rewritten on every
 * subsequent release: the 2.7.0 entry "replace x-release-please-version
 * placeholders with 2.7.0" had drifted to 2.24.1 before this was noticed.
 *
 * Dropping the final hyphen leaves the line reading the same while making it
 * invisible to the updater. CHANGELOG.md is not an extra-file, so it keeps the
 * commit subject verbatim and remains the exact record.
 *
 * @param {string} line A changelog line on its way into readme.txt.
 * @returns {string} The line, with any annotation defused.
 */
function neutralizeReleasePleaseAnnotations(line) {
	return line.replace(
		/x-release-please-(version|major|minor|patch|date)/g,
		'x-release-please $1'
	);
}

function normalizeMarkdownLine(line) {
	const trimmed = neutralizeReleasePleaseAnnotations(line.trim());
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

	// Scope mutations to the slice that starts at the heading; anything
	// before is pass-through prefix.
	const changelogStart = headingMatch.index;
	const prefix = readmeContent.slice(0, changelogStart);
	const changelogSection = readmeContent.slice(changelogStart);

	// Match a `= X.Y.Z =` heading bounded by the next sibling version
	// heading, the next major section heading, or end of input.
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
	neutralizeReleasePleaseAnnotations,
	normalizeSectionForReadme,
	buildReadmeVersionBlock,
	upsertVersionInReadme,
};
