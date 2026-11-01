#!/usr/bin/env node

/**
 * Script to extract upgrade notice for a specific version from readme.txt
 *
 * Usage:
 *   node scripts/extract-upgrade-notice.js --version=2.1.0 --plugin-dir=plugins/wp-graphql
 *   node scripts/extract-upgrade-notice.js 2.1.0 plugins/wp-graphql/readme.txt
 *
 * Options:
 *   --version     Version number to extract (required, leading 'v' is stripped)
 *   --readme      Path to readme.txt file
 *   --plugin-dir  Path to plugin directory containing readme.txt
 */

const fs = require('fs');
const path = require('path');

function parseArgs() {
	const args = {};
	const positionals = [];
	process.argv.slice(2).forEach((arg) => {
		if (arg.startsWith('--')) {
			const eq = arg.indexOf('=');
			if (eq !== -1) {
				args[arg.slice(2, eq)] = arg.slice(eq + 1);
			} else {
				args[arg.slice(2)] = true;
			}
		} else {
			positionals.push(arg);
		}
	});

	if (positionals.length > 0 && !args.version) {
		args.version = positionals[0];
	}
	if (positionals.length > 1 && !args.readme && !args['plugin-dir']) {
		args.readme = positionals[1];
	}

	return args;
}

function escapeRegExp(string) {
	return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Extract upgrade notice for a version from readme.txt content or file path
 *
 * @param {string} readmeContentOrVersion - Content of readme.txt, file path, or version
 * @param {string} versionOrPath - Version number or file path
 * @returns {string} Extracted upgrade notice content or empty string
 */
function extractUpgradeNotice(readmeContentOrVersion, versionOrPath) {
	if (!readmeContentOrVersion || !versionOrPath) {
		return '';
	}

	let readmeContent = '';
	let version = '';

	// Determine if arguments are (readmeContent, version) or (version, readmePath) or (readmePath, version)
	const isPath = (val) => {
		try {
			return typeof val === 'string' && (fs.existsSync(val) || val.endsWith('readme.txt') || path.isAbsolute(val));
		} catch (e) {
			return false;
		}
	};

	if (isPath(readmeContentOrVersion)) {
		try {
			readmeContent = fs.readFileSync(readmeContentOrVersion, 'utf8');
		} catch (e) {
			readmeContent = '';
		}
		version = versionOrPath;
	} else if (isPath(versionOrPath)) {
		version = readmeContentOrVersion;
		try {
			readmeContent = fs.readFileSync(versionOrPath, 'utf8');
		} catch (e) {
			readmeContent = '';
		}
	} else if (readmeContentOrVersion.includes('== Upgrade Notice ==') || readmeContentOrVersion.includes('===')) {
		readmeContent = readmeContentOrVersion;
		version = versionOrPath;
	} else {
		version = readmeContentOrVersion;
		readmeContent = versionOrPath;
	}

	if (!readmeContent || !version) {
		return '';
	}

	const cleanVersion = version.replace(/^v/i, '');
	const escapedVersion = escapeRegExp(cleanVersion);

	// Find Upgrade Notice section
	const upgradeNoticeMatch = readmeContent.match(
		/== Upgrade Notice ==\n\n?([\s\S]*?)(?=\n==|$)/
	);
	if (!upgradeNoticeMatch) {
		return '';
	}

	const sectionBody = upgradeNoticeMatch[1];

	// Find version block starting with `= X.Y.Z =` up to next version heading or section or EOF
	const versionPattern = new RegExp(
		`(^|\\n)=\\s*${escapedVersion}\\s*=[\\s\\S]*?(?=\\n+\\s*=\\s*[0-9]+\\.[0-9]+\\.[0-9]+(?:-[\\w.-]+)?\\s*=|\\n+==|$)`,
		'i'
	);

	const match = sectionBody.match(versionPattern);
	if (!match) {
		return '';
	}

	let block = match[0].trim();
	block = block.replace(/^\n/, '');

	// Remove the `= X.Y.Z =` heading line
	const headingRegex = new RegExp(`^=\\s*${escapedVersion}\\s*=\\s*\\n*`, 'i');
	block = block.replace(headingRegex, '').trim();

	return block;
}

function main() {
	const args = parseArgs();

	if (!args.version) {
		console.error('Error: --version is required');
		console.error('Usage: node scripts/extract-upgrade-notice.js <version> [path-to-readme.txt]');
		process.exit(1);
	}

	let readmePath = args.readme || args['path-to-readme'];
	if (!readmePath) {
		const pluginDir = args['plugin-dir'] || 'plugins/wp-graphql';
		readmePath = path.join(process.cwd(), pluginDir, 'readme.txt');
	} else {
		readmePath = path.resolve(process.cwd(), readmePath);
	}

	if (!fs.existsSync(readmePath)) {
		process.stdout.write('');
		process.exit(0);
	}

	let readmeContent;
	try {
		readmeContent = fs.readFileSync(readmePath, 'utf8');
	} catch (error) {
		console.error(`Error reading ${readmePath}: ${error.message}`);
		process.exit(1);
	}

	const notice = extractUpgradeNotice(readmeContent, args.version);
	if (notice) {
		process.stdout.write(notice + '\n');
	}
	process.exit(0);
}

if (require.main === module) {
	main();
}

module.exports = {
	extractUpgradeNotice,
	parseArgs,
};
