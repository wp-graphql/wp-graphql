#!/usr/bin/env node

/**
 * Script to update version constants in plugin files
 *
 * This script updates plugin-specific version constants (e.g., WPGRAPHQL_VERSION,
 * WPGRAPHQL_SMART_CACHE_VERSION, WPGRAPHQL_IDE_VERSION) in their respective files
 * during the release PR update process.
 *
 * Usage:
 *   node scripts/update-version-constants.js --version=1.0.0 --component=wp-graphql --plugin-dir=plugins/wp-graphql
 *
 * Options:
 *   --version     Version number to set (required)
 *   --component   Component name (required): wp-graphql, wp-graphql-smart-cache, wp-graphql-ide
 *   --plugin-dir  Path to the plugin directory (required, relative to repo root)
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
 * Validate version format
 * @param {string} version - Version string to validate
 * @returns {boolean} True if valid, false otherwise
 */
function isValidVersion(version) {
	// Matches semantic versioning: x.y.z or x.y.z-beta.n, etc.
	return /^\d+\.\d+\.\d+(?:-[\w.-]+)?$/.test(version);
}

/**
 * Load release-please config to get constant mappings
 * @returns {Object} Config object with packages
 */
function loadReleasePleaseConfig() {
	const repoRoot = process.cwd();
	const configPath = path.join(repoRoot, 'release-please-config.json');
	
	if (!fs.existsSync(configPath)) {
		throw new Error(`release-please-config.json not found at ${configPath}`);
	}

	try {
		return JSON.parse(fs.readFileSync(configPath, 'utf8'));
	} catch (error) {
		throw new Error(`Failed to parse release-please-config.json: ${error.message}`);
	}
}

/**
 * Map component name to constant name and file from release-please-config.json
 * @param {string} component - Component name
 * @returns {Object|null} Object with constantName, fileName, and mainPluginFile, or null if not found
 */
function getConstantMapping(component) {
	const config = loadReleasePleaseConfig();
	const packages = config.packages || {};

	// Find the package that matches this component
	for (const [packagePath, packageConfig] of Object.entries(packages)) {
		if (packageConfig.component === component) {
			// Check if constantMap is defined
			if (packageConfig.constantMap) {
				return packageConfig.constantMap;
			}
			// If no constantMap, return null (plugin doesn't need version constant updates)
			return null;
		}
	}

	return null;
}

/**
 * Update version constant in a PHP file
 * @param {string} filePath - Path to the PHP file
 * @param {string} constantName - Name of the constant to update
 * @param {string} version - Version number to set
 * @returns {Object} Result object with updated flag and message
 */
function updateVersionConstant(filePath, constantName, version) {
	if (!fs.existsSync(filePath)) {
		return {
			updated: false,
			error: `File not found: ${filePath}`,
		};
	}

	let content;
	try {
		content = fs.readFileSync(filePath, 'utf8');
	} catch (error) {
		return {
			updated: false,
			error: `Error reading file ${filePath}: ${error.message}`,
		};
	}

	const originalContent = content;

	// Pattern to match various formats:
	// - define( 'CONSTANT_NAME', 'X.Y.Z' );
	// - define( 'CONSTANT_NAME', "X.Y.Z" );
	// - define( "CONSTANT_NAME", 'X.Y.Z' );
	// - Indented defines (tabs/spaces)
	// - Also handles x-release-please-version placeholders

	// Escape the constant name for regex
	const escapedConstant = constantName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

	// Single comprehensive pattern that matches:
	// - Optional whitespace before define (captured for preservation)
	// - define keyword
	// - Optional whitespace
	// - Opening parenthesis
	// - Optional whitespace
	// - Quote (single or double) around constant name
	// - Constant name
	// - Quote (single or double) around constant name
	// - Optional whitespace
	// - Comma
	// - Optional whitespace
	// - Quote (single or double) around version
	// - Any version string (including x-release-please-version)
	// - Quote (single or double) around version
	// - Optional whitespace
	// - Closing parenthesis
	// - Optional whitespace
	// - Semicolon
	const pattern = new RegExp(
		`(\\s*)define\\s*\\(\\s*['"]${escapedConstant}['"]\\s*,\\s*['"]([^'"]*)['"]\\s*\\)\\s*;`,
		'g'
	);

	let replacementCount = 0;
	content = content.replace(
		pattern,
		(match, leadingWhitespace, oldVersion) => {
			replacementCount++;
			// Preserve leading whitespace (tabs/spaces) for indentation
			return `${leadingWhitespace}define( '${constantName}', '${version}' );`;
		}
	);

	if (content === originalContent) {
		// Check if constant exists at all
		const constantExists = new RegExp(
			`define\\s*\\(\\s*['"]${escapedConstant}['"]`,
			'i'
		).test(content);

		if (!constantExists) {
			return {
				updated: false,
				error: `Constant ${constantName} not found in ${path.basename(filePath)}`,
			};
		}

		// Constant exists but version already matches
		return {
			updated: false,
			message: `Constant ${constantName} already has version ${version} in ${path.basename(filePath)}`,
		};
	}

	try {
		fs.writeFileSync(filePath, content, 'utf8');
	} catch (error) {
		return {
			updated: false,
			error: `Error writing file ${filePath}: ${error.message}`,
		};
	}

	// Verify the update
	if (content.includes(`define( '${constantName}', '${version}' )`)) {
		return {
			updated: true,
			message: `Updated ${constantName} to ${version} in ${path.basename(filePath)} (${replacementCount} replacement${replacementCount === 1 ? '' : 's'})`,
		};
	} else {
		// Find the actual constant line for debugging
		const constantLine = content
			.split('\n')
			.find((line) => line.includes(constantName));

		return {
			updated: false,
			error: `Failed to verify ${constantName} update in ${path.basename(filePath)}. Current line: ${constantLine || '(not found)'}`,
		};
	}
}

/**
 * Update Version header in main plugin file
 * @param {string} filePath - Path to the main plugin PHP file
 * @param {string} version - Version number to set
 * @returns {Object} Result object with updated flag and message
 */
function updatePluginVersionHeader(filePath, version) {
	if (!fs.existsSync(filePath)) {
		return {
			updated: false,
			error: `File not found: ${filePath}`,
		};
	}

	let content;
	try {
		content = fs.readFileSync(filePath, 'utf8');
	} catch (error) {
		return {
			updated: false,
			error: `Error reading file ${filePath}: ${error.message}`,
		};
	}

	const originalContent = content;

	// Pattern to match Version header in plugin header comment:
	// - * Version: X.Y.Z
	// - * @version  X.Y.Z
	// Also handles x-release-please-version placeholders

	// Pattern 1: Version header (most common format)
	const versionHeaderPattern = /^(\s*\*\s*Version:\s*)([^\s\n]+)/gm;
	let replacementCount = 0;
	content = content.replace(
		versionHeaderPattern,
		(match, prefix, oldVersion) => {
			replacementCount++;
			return `${prefix}${version}`;
		}
	);

	// Pattern 2: @version docblock tag
	const versionDocblockPattern = /^(\s*\*\s*@version\s+)([^\s\n]+)/gm;
	content = content.replace(
		versionDocblockPattern,
		(match, prefix, oldVersion) => {
			replacementCount++;
			return `${prefix}${version}`;
		}
	);

	if (content === originalContent) {
		// Check if Version header exists at all
		const versionExists = /^\s*\*\s*Version:/m.test(content);

		if (!versionExists) {
			return {
				updated: false,
				error: `Version header not found in ${path.basename(filePath)}`,
			};
		}

		// Version exists but already matches
		return {
			updated: false,
			message: `Version header already has version ${version} in ${path.basename(filePath)}`,
		};
	}

	try {
		fs.writeFileSync(filePath, content, 'utf8');
	} catch (error) {
		return {
			updated: false,
			error: `Error writing file ${filePath}: ${error.message}`,
		};
	}

	// Verify the update
	// Use regex to match Version header with variable whitespace
	// Matches: "Version: 4.1.0", "Version:    4.1.0", "@version 4.1.0", etc.
	const versionHeaderRegex = new RegExp(
		`Version:\\s+${version.replace(/\./g, '\\.')}`
	);
	const versionDocblockRegex = new RegExp(
		`@version\\s+${version.replace(/\./g, '\\.')}`
	);

	if (versionHeaderRegex.test(content) || versionDocblockRegex.test(content)) {
		return {
			updated: true,
			message: `Updated Version header to ${version} in ${path.basename(filePath)} (${replacementCount} replacement${replacementCount === 1 ? '' : 's'})`,
		};
	} else {
		// Find the actual version line for debugging
		const versionLine = content
			.split('\n')
			.find((line) => /Version:/.test(line) || /@version/.test(line));

		return {
			updated: false,
			error: `Failed to verify Version header update in ${path.basename(filePath)}. Current line: ${versionLine || '(not found)'}`,
		};
	}
}

/**
 * Main function
 */
function main() {
	try {
		const args = parseArgs();

		// Validate required arguments
		if (!args.version) {
			console.error('❌ Error: --version is required');
			console.error(
				'Usage: node scripts/update-version-constants.js --version=1.0.0 --component=wp-graphql --plugin-dir=plugins/wp-graphql'
			);
			process.exit(1);
		}

		if (!args.component) {
			console.error('❌ Error: --component is required');
			console.error(
				'Usage: node scripts/update-version-constants.js --version=1.0.0 --component=wp-graphql --plugin-dir=plugins/wp-graphql'
			);
			process.exit(1);
		}

		if (!args['plugin-dir']) {
			console.error('❌ Error: --plugin-dir is required');
			console.error(
				'Usage: node scripts/update-version-constants.js --version=1.0.0 --component=wp-graphql --plugin-dir=plugins/wp-graphql'
			);
			process.exit(1);
		}

		// Validate version format
		if (!isValidVersion(args.version)) {
			console.error(`❌ Error: Invalid version format: ${args.version}`);
			console.error(
				'Expected format: x.y.z or x.y.z-beta.n (semantic versioning)'
			);
			process.exit(1);
		}

		const version = args.version;
		const component = args.component;
		const pluginDir = args['plugin-dir'];

		console.log(
			`🔄 Updating version constants for ${component} to ${version}...`
		);

		const mapping = getConstantMapping(component);
		if (!mapping) {
			console.warn(
				`⚠️  No version constant mapping for component: ${component}`
			);
			console.warn(
				`   This component may not need version constant updates, or constantMap is missing in release-please-config.json`
			);
			console.warn(
				`   To enable version updates, add a "constantMap" entry to the plugin's config in release-please-config.json`
			);
			process.exit(0);
		}

		// Resolve plugin directory paths
		const repoRoot = process.cwd();
		const constantFilePath = path.resolve(
			repoRoot,
			pluginDir,
			mapping.fileName
		);
		const mainPluginFilePath = path.resolve(
			repoRoot,
			pluginDir,
			mapping.mainPluginFile
		);

		// Update version constant
		const constantResult = updateVersionConstant(
			constantFilePath,
			mapping.constantName,
			version
		);

		// Update Version header in main plugin file
		const headerResult = updatePluginVersionHeader(
			mainPluginFilePath,
			version
		);

		let hasErrors = false;
		let hasUpdates = false;

		// Process constant update result
		if (constantResult.updated) {
			console.log(`✅ ${constantResult.message}`);
			hasUpdates = true;
		} else if (constantResult.error) {
			console.error(`❌ ${constantResult.error}`);
			hasErrors = true;
		} else if (constantResult.message) {
			console.log(`ℹ️  ${constantResult.message}`);
		}

		// Process header update result
		if (headerResult.updated) {
			console.log(`✅ ${headerResult.message}`);
			hasUpdates = true;
		} else if (headerResult.error) {
			console.error(`❌ ${headerResult.error}`);
			hasErrors = true;
		} else if (headerResult.message) {
			console.log(`ℹ️  ${headerResult.message}`);
		}

		// Exit with appropriate code
		if (hasErrors) {
			process.exit(1);
		} else if (hasUpdates) {
			process.exit(0);
		} else {
			// No updates needed (versions already correct)
			process.exit(0);
		}
	} catch (error) {
		console.error(`❌ Unexpected error: ${error.message}`);
		console.error(error.stack);
		process.exit(1);
	}
}

if (require.main === module) {
	main();
}

module.exports = {
	updateVersionConstant,
	updatePluginVersionHeader,
	getConstantMapping,
};
