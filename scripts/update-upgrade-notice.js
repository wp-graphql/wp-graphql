#!/usr/bin/env node

/**
 * Script to update the Upgrade Notice section in readme.txt based on breaking changes
 *
 * This script is designed to work with release-please's CHANGELOG.md format.
 * It extracts breaking changes for a specific version and adds them to the
 * Upgrade Notice section in readme.txt.
 *
 * Usage:
 *   node scripts/update-upgrade-notice.js --version=1.0.0 --plugin-dir=plugins/wp-graphql
 *
 * Options:
 *   --version     Version number for the upgrade notice
 *   --plugin-dir  Path to the plugin directory (relative to repo root)
 */

const fs = require('fs');
const path = require('path');

/**
 * Parse command line arguments
 * @returns {Object} Parsed arguments
 */
function parseArgs() {
	const args = {};
	process.argv.slice(2).forEach((arg) => {
		const [key, value] = arg.replace(/^--/, '').split('=');
		args[key] = value;
	});
	return args;
}

/**
 * Escape special characters in a string for use in a regular expression
 * @param {string} string - The string to escape
 * @returns {string} The escaped string safe for use in RegExp
 */
function escapeRegExp(string) {
	return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Extract breaking changes from CHANGELOG.md for a specific version
 *
 * @param {string} changelogPath Path to the CHANGELOG.md file
 * @param {string} version Version to extract breaking changes for
 * @returns {Array} Array of breaking change descriptions
 */
function extractBreakingChanges(changelogPath, version) {
	if (!fs.existsSync(changelogPath)) {
		console.log('CHANGELOG.md not found.');
		return [];
	}

	const content = fs.readFileSync(changelogPath, 'utf8');

	// Find the section for this version
	// Release-please format: ## [1.0.0](url) (date) or ## 1.0.0 (date)
	const escapedVersion = escapeRegExp(version);
	const versionPattern = new RegExp(
		`## \\[?${escapedVersion}\\]?[^\\n]*\\n([\\s\\S]*?)(?=\\n## |$)`,
		'i'
	);

	const versionMatch = content.match(versionPattern);
	if (!versionMatch) {
		console.log(`No changelog entry found for version ${version}`);
		return [];
	}

	const versionContent = versionMatch[1];

	// Look for breaking changes section
	// Release-please uses "### ⚠ BREAKING CHANGES" or similar headers
	const breakingPatterns = [
		/### ⚠ BREAKING CHANGES?\n([\s\S]*?)(?=\n### |$)/i,
		/### BREAKING CHANGES?\n([\s\S]*?)(?=\n### |$)/i,
		/### ⚠️ BREAKING CHANGES?\n([\s\S]*?)(?=\n### |$)/i,
	];

	let breakingSection = null;
	for (const pattern of breakingPatterns) {
		const match = versionContent.match(pattern);
		if (match) {
			breakingSection = match[1];
			break;
		}
	}

	if (!breakingSection) {
		console.log('No breaking changes section found for this version.');
		return [];
	}

	// Extract individual breaking changes (lines starting with * or -)
	const breakingChanges = [];
	const lines = breakingSection.split('\n');

	for (const line of lines) {
		const trimmed = line.trim();
		if (trimmed.startsWith('* ') || trimmed.startsWith('- ')) {
			// Clean up the line - remove leading bullet and clean markdown
			let change = trimmed.replace(/^[*-]\s+/, '');

			// Keep PR links but clean up other markdown
			breakingChanges.push(change);
		}
	}

	console.log(
		`Found ${breakingChanges.length} breaking change(s) for version ${version}`
	);
	return breakingChanges;
}

/**
 * Update the Upgrade Notice section in readme.txt
 *
 * @param {string} readmePath Path to readme.txt
 * @param {string} version Version number
 * @param {Array} breakingChanges Array of breaking change descriptions
 * @returns {boolean} Whether changes were made
 */
function updateUpgradeNotice(readmePath, version, breakingChanges) {
	if (!fs.existsSync(readmePath)) {
		console.error('readme.txt not found:', readmePath);
		return false;
	}

	if (breakingChanges.length === 0) {
		console.log('No breaking changes to add to upgrade notice.');
		return false;
	}

	let content = fs.readFileSync(readmePath, 'utf8');

	// Format the upgrade notice
	let upgradeNotice = `= ${version} =\n\n`;
	upgradeNotice +=
		'**⚠️ BREAKING CHANGES**: This release contains breaking changes that may require updates to your code.\n\n';

	breakingChanges.forEach((change) => {
		upgradeNotice += `* ${change}\n`;
	});

	upgradeNotice += '\nPlease review these changes before upgrading.\n';

	// Find the Upgrade Notice section
	const upgradeNoticeMatch = content.match(
		/(== Upgrade Notice ==\n\n?)([\s\S]*?)(?=\n==|$)/
	);
	const escapedVersion = escapeRegExp(version);

	if (upgradeNoticeMatch) {
		// Check if this version's notice already exists
		const versionNoticeRegex = new RegExp(`= ${escapedVersion} =`);
		const hasVersionNotice = versionNoticeRegex.test(upgradeNoticeMatch[2]);

		if (hasVersionNotice) {
			// Replace existing notice for this version
			const existingNoticeRegex = new RegExp(
				`(= ${escapedVersion} =[\\s\\S]*?)(?=\\n= \\d|$)`
			);
			content = content.replace(existingNoticeRegex, upgradeNotice);
			console.log(
				`Updated existing upgrade notice for version ${version}`
			);
		} else {
			// Add new notice at the top of the section
			content = content.replace(
				/(== Upgrade Notice ==\n\n?)/,
				`$1${upgradeNotice}\n`
			);
			console.log(`Added new upgrade notice for version ${version}`);
		}
	} else {
		// Add Upgrade Notice section before Changelog
		const changelogMatch = content.match(/\n== Changelog ==/);
		if (changelogMatch) {
			const insertPos = changelogMatch.index;
			content =
				content.slice(0, insertPos) +
				`\n== Upgrade Notice ==\n\n${upgradeNotice}\n` +
				content.slice(insertPos);
			console.log(
				`Created Upgrade Notice section with notice for version ${version}`
			);
		} else {
			console.error(
				'Could not find Changelog section to insert Upgrade Notice before.'
			);
			return false;
		}
	}

	fs.writeFileSync(readmePath, content);
	return true;
}

/**
 * Main function
 */
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

	console.log(`Checking for breaking changes in version ${args.version}...`);
	console.log(`Changelog: ${changelogPath}`);
	console.log(`Readme: ${readmePath}`);

	// Extract breaking changes from changelog
	const breakingChanges = extractBreakingChanges(changelogPath, args.version);

	// Update upgrade notice if there are breaking changes
	if (breakingChanges.length > 0) {
		const updated = updateUpgradeNotice(
			readmePath,
			args.version,
			breakingChanges
		);
		if (updated) {
			console.log('✅ Upgrade notice updated successfully!');
		} else {
			console.log('⚠️ Failed to update upgrade notice.');
			process.exit(1);
		}
	} else {
		console.log('ℹ️ No breaking changes found - no upgrade notice needed.');
	}
}

// Run the script
main();
