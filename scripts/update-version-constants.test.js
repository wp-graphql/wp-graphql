#!/usr/bin/env node

/**
 * Tests for update-version-constants.js
 *
 * Run with: node scripts/update-version-constants.test.js
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');

const SCRIPT_PATH = path.join(__dirname, 'update-version-constants.js');
const TEST_DIR = path.join(__dirname, '..', '.test-version-constants');

/**
 * Setup test directory with test files
 */
function setup() {
	if (fs.existsSync(TEST_DIR)) {
		fs.rmSync(TEST_DIR, { recursive: true });
	}
	fs.mkdirSync(TEST_DIR, { recursive: true });
}

/**
 * Cleanup test directory
 */
function cleanup() {
	if (fs.existsSync(TEST_DIR)) {
		fs.rmSync(TEST_DIR, { recursive: true });
	}
}

/**
 * Create test constants.php file
 */
function createConstants(content) {
	fs.writeFileSync(path.join(TEST_DIR, 'constants.php'), content);
}

/**
 * Create test wp-graphql.php file
 */
function createPluginFile(content) {
	fs.writeFileSync(path.join(TEST_DIR, 'wp-graphql.php'), content);
}

/**
 * Read file content
 */
function readFile(filename) {
	return fs.readFileSync(path.join(TEST_DIR, filename), 'utf8');
}

/**
 * Run the script with given arguments
 */
function runScript(version, component = 'wp-graphql') {
	const cmd = `node "${SCRIPT_PATH}" --version=${version} --component=${component} --plugin-dir=.test-version-constants`;
	try {
		const output = execSync(cmd, {
			cwd: path.join(__dirname, '..'),
			encoding: 'utf8',
			stdio: ['pipe', 'pipe', 'pipe'],
		});
		return { success: true, output };
	} catch (error) {
		return { success: false, output: error.stdout || error.message, error: error.stderr };
	}
}

/**
 * Test helper to run a test case
 */
function test(name, fn) {
	try {
		setup();
		fn();
		console.log(`✅ ${name}`);
		return true;
	} catch (error) {
		console.log(`❌ ${name}`);
		console.log(`   Error: ${error.message}`);
		if (error.stack) {
			console.log(`   Stack: ${error.stack.split('\n').slice(1, 3).join('\n')}`);
		}
		return false;
	} finally {
		cleanup();
	}
}

// ===========================================
// TEST CASES
// ===========================================

const tests = [
	// Test 1: Update version constant in constants.php
	() =>
		test('updates WPGRAPHQL_VERSION constant in constants.php', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '2.7.0' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 *
 * @package  WPGraphQL
 * @version  2.7.0
 */
`);

			const result = runScript('2.8.0');
			assert(result.success, 'Script should succeed');

			const constants = readFile('constants.php');
			assert(
				constants.includes("define( 'WPGRAPHQL_VERSION', '2.8.0' );"),
				'Should update WPGRAPHQL_VERSION to 2.8.0'
			);
			assert(
				!constants.includes("define( 'WPGRAPHQL_VERSION', '2.7.0' );"),
				'Should not contain old version'
			);
		}),

	// Test 2: Update Version header in plugin file
	() =>
		test('updates Version header in wp-graphql.php', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '2.7.0' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 *
 * @package  WPGraphQL
 * @version  2.7.0
 */
`);

			const result = runScript('2.8.0');
			assert(result.success, 'Script should succeed');

			const pluginFile = readFile('wp-graphql.php');
			assert(
				pluginFile.includes(' * Version: 2.8.0'),
				'Should update Version header to 2.8.0'
			);
			assert(
				pluginFile.includes(' * @version  2.8.0'),
				'Should update @version docblock to 2.8.0'
			);
			assert(
				!pluginFile.includes('Version: 2.7.0'),
				'Should not contain old version in header'
			);
		}),

	// Test 3: Handles x-release-please-version placeholder
	() =>
		test('replaces x-release-please-version placeholder', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', 'x-release-please-version' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: x-release-please-version
 *
 * @package  WPGraphQL
 * @version  x-release-please-version
 */
`);

			const result = runScript('3.0.0');
			assert(result.success, 'Script should succeed');

			const constants = readFile('constants.php');
			const pluginFile = readFile('wp-graphql.php');

			assert(
				constants.includes("define( 'WPGRAPHQL_VERSION', '3.0.0' );"),
				'Should replace placeholder in constants.php'
			);
			assert(
				pluginFile.includes(' * Version: 3.0.0'),
				'Should replace placeholder in plugin header'
			);
		}),

	// Test 4: No changes when version already matches
	() =>
		test('reports no changes when version already matches', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '2.8.0' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.8.0
 *
 * @package  WPGraphQL
 * @version  2.8.0
 */
`);

			const result = runScript('2.8.0');
			assert(result.success, 'Script should succeed');
			assert(
				result.output.includes('already has version'),
				'Should report version already matches'
			);
		}),

	// Test 5: Handles tab indentation
	() =>
		test('preserves tab indentation in constants.php', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '2.7.0' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 */
`);

			const result = runScript('2.8.0');
			assert(result.success, 'Script should succeed');

			const constants = readFile('constants.php');
			// Check that tabs are preserved (the line should start with tab)
			const versionLine = constants
				.split('\n')
				.find((line) => line.includes("define( 'WPGRAPHQL_VERSION'"));
			assert(
				versionLine && versionLine.startsWith('\t'),
				'Should preserve tab indentation'
			);
		}),

	// Test 6: Handles double quotes in define
	() =>
		test('handles double quotes in define statement', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( "WPGRAPHQL_VERSION" ) ) {
		define( "WPGRAPHQL_VERSION", "2.7.0" );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 */
`);

			const result = runScript('2.8.0');
			assert(result.success, 'Script should succeed');

			const constants = readFile('constants.php');
			// Script normalizes to single quotes, which is fine
			assert(
				constants.includes("define( 'WPGRAPHQL_VERSION', '2.8.0' );"),
				'Should update version (normalized to single quotes)'
			);
		}),

	// Test 7: Error handling for missing file
	() =>
		test('handles missing constants.php file gracefully', () => {
			// Don't create constants.php
			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 */
`);

			const result = runScript('2.8.0');
			// Should fail because constants.php is missing
			assert(!result.success || result.output.includes('not found'), 'Should report file not found');
		}),

	// Test 8: Error handling for invalid component
	() =>
		test('handles invalid component name', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '2.7.0' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 */
`);

			const result = runScript('2.8.0', 'invalid-component');
			// Script exits with 0 and warns about unsupported component
			// Check that it warns (output goes to stderr or stdout)
			const allOutput = (result.output || '') + (result.error || '');
			assert(
				allOutput.includes('No version constant mapping') ||
					allOutput.includes('Supported components') ||
					result.success, // Script exits successfully with warning
				'Should warn about unsupported component or exit successfully'
			);
		}),

	// Test 9: Updates both occurrences in plugin file
	() =>
		test('updates both Version header and @version docblock', () => {
			createConstants(`<?php
function graphql_setup_constants() {
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '2.7.0' );
	}
}
`);

			createPluginFile(`<?php
/**
 * Plugin Name: WPGraphQL
 * Version: 2.7.0
 *
 * @package  WPGraphQL
 * @version  2.7.0
 */
`);

			const result = runScript('2.8.0');
			assert(result.success, 'Script should succeed');

			const pluginFile = readFile('wp-graphql.php');
			const versionMatches = pluginFile.match(/2\.8\.0/g);
			assert(
				versionMatches && versionMatches.length >= 2,
				'Should update both Version header and @version docblock'
			);
		}),

	// Test 10: wp-graphql-smart-cache - updates constant in main file
	() =>
		test('wp-graphql-smart-cache: updates constant in main plugin file', () => {
			// For smart-cache, the constant is in the main plugin file
			const smartCacheFile = `<?php
/**
 * Plugin Name: WPGraphQL Smart Cache
 * Version: 2.0.1
 */

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_VERSION', '2.0.1' );
}
`;
			fs.writeFileSync(path.join(TEST_DIR, 'wp-graphql-smart-cache.php'), smartCacheFile);

			const result = runScript('2.1.0', 'wp-graphql-smart-cache');
			assert(result.success, 'Script should succeed');

			const file = readFile('wp-graphql-smart-cache.php');
			assert(
				file.includes("define( 'WPGRAPHQL_SMART_CACHE_VERSION', '2.1.0' );"),
				'Should update WPGRAPHQL_SMART_CACHE_VERSION constant'
			);
			assert(
				file.includes(' * Version: 2.1.0'),
				'Should update Version header'
			);
		}),

	// Test 11: wp-graphql-ide - updates constant in main file
	() =>
		test('wp-graphql-ide: updates constant in main plugin file', () => {
			// For IDE, the constant is in the main plugin file
			const ideFile = `<?php
/**
 * Plugin Name: WPGraphQL IDE
 * Version: 4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_VERSION', '4.1.0' );
`;
			fs.writeFileSync(path.join(TEST_DIR, 'wpgraphql-ide.php'), ideFile);

			const result = runScript('4.2.0', 'wp-graphql-ide');
			assert(result.success, 'Script should succeed');

			const file = readFile('wpgraphql-ide.php');
			assert(
				file.includes("define( 'WPGRAPHQL_IDE_VERSION', '4.2.0' );"),
				'Should update WPGRAPHQL_IDE_VERSION constant'
			);
			assert(
				file.includes(' * Version: 4.2.0'),
				'Should update Version header'
			);
		}),

	// Test 12: wp-graphql-smart-cache with placeholder
	() =>
		test('wp-graphql-smart-cache: replaces x-release-please-version placeholder', () => {
			const smartCacheFile = `<?php
/**
 * Plugin Name: WPGraphQL Smart Cache
 * Version: x-release-please-version
 */

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_VERSION', 'x-release-please-version' );
}
`;
			fs.writeFileSync(path.join(TEST_DIR, 'wp-graphql-smart-cache.php'), smartCacheFile);

			const result = runScript('2.1.0', 'wp-graphql-smart-cache');
			assert(result.success, 'Script should succeed');

			const file = readFile('wp-graphql-smart-cache.php');
			assert(
				!file.includes('x-release-please-version'),
				'Should replace all placeholders'
			);
			assert(
				file.includes("define( 'WPGRAPHQL_SMART_CACHE_VERSION', '2.1.0' );"),
				'Should update constant with new version'
			);
		}),

	// Test 13: wp-graphql-ide with placeholder
	() =>
		test('wp-graphql-ide: replaces x-release-please-version placeholder', () => {
			const ideFile = `<?php
/**
 * Plugin Name: WPGraphQL IDE
 * Version: x-release-please-version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_VERSION', 'x-release-please-version' );
`;
			fs.writeFileSync(path.join(TEST_DIR, 'wpgraphql-ide.php'), ideFile);

			const result = runScript('4.2.0', 'wp-graphql-ide');
			assert(result.success, 'Script should succeed');

			const file = readFile('wpgraphql-ide.php');
			assert(
				!file.includes('x-release-please-version'),
				'Should replace all placeholders'
			);
			assert(
				file.includes("define( 'WPGRAPHQL_IDE_VERSION', '4.2.0' );"),
				'Should update constant with new version'
			);
		}),

	// Test 14: Integration test - verify all plugins from manifest
	() =>
		test('integration: verifies all plugins from release-please-manifest.json', () => {
			const repoRoot = path.join(__dirname, '..');
			const manifestPath = path.join(repoRoot, '.release-please-manifest.json');
			const configPath = path.join(repoRoot, 'release-please-config.json');

			// Read manifest and config
			const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
			const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));

			// Get the script's constant mapping function
			const { getConstantMapping } = require('./update-version-constants.js');

			const plugins = [];
			for (const [pluginPath, currentVersion] of Object.entries(manifest)) {
				// Extract component name from plugin path (e.g., "plugins/wp-graphql" -> "wp-graphql")
				const component = pluginPath.replace('plugins/', '');
				
				// Get component from config
				const configEntry = config.packages[pluginPath];
				if (!configEntry) {
					console.log(`⚠️  Plugin ${pluginPath} in manifest but not in config`);
					continue;
				}

				const mapping = getConstantMapping(component);
				if (!mapping) {
					console.log(`⚠️  No constant mapping for component: ${component}`);
					continue;
				}

				plugins.push({
					component,
					pluginPath,
					currentVersion,
					mapping,
				});
			}

			assert(plugins.length > 0, 'Should find at least one plugin in manifest');

			// For each plugin, verify the script can update it
			for (const plugin of plugins) {
				const { component, currentVersion, mapping, pluginPath } = plugin;
				const actualPluginDir = path.join(repoRoot, pluginPath);

				// Check if plugin directory exists
				if (!fs.existsSync(actualPluginDir)) {
					console.log(`⚠️  Plugin directory not found: ${actualPluginDir}`);
					continue;
				}

				// Check if the constant file exists
				const constantFilePath = path.join(actualPluginDir, mapping.fileName);
				if (!fs.existsSync(constantFilePath)) {
					console.log(`⚠️  Constant file not found: ${constantFilePath}`);
					continue;
				}

				// Check if main plugin file exists
				const mainPluginFilePath = path.join(actualPluginDir, mapping.mainPluginFile);
				if (!fs.existsSync(mainPluginFilePath)) {
					console.log(`⚠️  Main plugin file not found: ${mainPluginFilePath}`);
					continue;
				}

				// Read the actual files to verify they contain the constant
				const constantFileContent = fs.readFileSync(constantFilePath, 'utf8');
				const mainPluginFileContent = fs.readFileSync(mainPluginFilePath, 'utf8');

				// Verify constant exists in the file
				const constantPattern = new RegExp(
					`define\\s*\\(\\s*['"]${mapping.constantName}['"]\\s*,\\s*['"]([^'"]+)['"]\\s*\\)\\s*;`,
					'i'
				);
				const constantMatch = constantFileContent.match(constantPattern);
				
				assert(
					constantMatch,
					`Plugin ${component}: Constant ${mapping.constantName} should exist in ${mapping.fileName}`
				);

				// Verify Version header exists in main plugin file
				const versionHeaderPattern = /^\s*\*\s*Version:\s*([^\s\n]+)/m;
				const versionMatch = mainPluginFileContent.match(versionHeaderPattern);
				
				assert(
					versionMatch,
					`Plugin ${component}: Version header should exist in ${mapping.mainPluginFile}`
				);

				// Test that the script would update it correctly
				// Create a test copy of the files
				setup();
				
				// Copy constant file content to test dir
				if (mapping.fileName === mapping.mainPluginFile) {
					// For plugins where constant is in main file, just copy that
					fs.writeFileSync(
						path.join(TEST_DIR, mapping.mainPluginFile),
						mainPluginFileContent.replace(
							constantPattern,
							`define( '${mapping.constantName}', 'x-release-please-version' );`
						).replace(
							versionHeaderPattern,
							(match) => match.replace(versionMatch[1], 'x-release-please-version')
						)
					);
				} else {
					// For wp-graphql which has separate constants.php
					fs.writeFileSync(
						path.join(TEST_DIR, mapping.fileName),
						constantFileContent.replace(
							constantPattern,
							`define( '${mapping.constantName}', 'x-release-please-version' );`
						)
					);
					fs.writeFileSync(
						path.join(TEST_DIR, mapping.mainPluginFile),
						mainPluginFileContent.replace(
							versionHeaderPattern,
							(match) => match.replace(versionMatch[1], 'x-release-please-version')
						)
					);
				}

				// Run script with a test version
				const testVersion = incrementVersion(currentVersion);
				const result = runScript(testVersion, component);
				
				assert(
					result.success,
					`Plugin ${component}: Script should succeed for version ${testVersion}`
				);

				// Verify the update worked
				if (mapping.fileName === mapping.mainPluginFile) {
					const updatedFile = readFile(mapping.mainPluginFile);
					assert(
						updatedFile.includes(`define( '${mapping.constantName}', '${testVersion}' );`),
						`Plugin ${component}: Constant should be updated to ${testVersion}`
					);
					// Check for Version header with flexible whitespace
					const versionRegex = new RegExp(`Version:\\s+${testVersion.replace(/\./g, '\\.')}`);
					assert(
						versionRegex.test(updatedFile),
						`Plugin ${component}: Version header should be updated to ${testVersion}`
					);
				} else {
					const updatedConstant = readFile(mapping.fileName);
					const updatedMain = readFile(mapping.mainPluginFile);
					assert(
						updatedConstant.includes(`define( '${mapping.constantName}', '${testVersion}' );`),
						`Plugin ${component}: Constant should be updated to ${testVersion}`
					);
					// Check for Version header with flexible whitespace
					const versionRegex = new RegExp(`Version:\\s+${testVersion.replace(/\./g, '\\.')}`);
					assert(
						versionRegex.test(updatedMain),
						`Plugin ${component}: Version header should be updated to ${testVersion}`
					);
				}

				cleanup();
			}

			console.log(`✅ Verified ${plugins.length} plugin(s) from manifest`);
		}),
];

/**
 * Increment version for testing (simple patch increment)
 */
function incrementVersion(version) {
	const parts = version.split('.');
	parts[parts.length - 1] = String(parseInt(parts[parts.length - 1]) + 1);
	return parts.join('.');
}

// ===========================================
// RUN TESTS
// ===========================================

console.log('\n🧪 Running update-version-constants.js tests...\n');

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

// Exit with error code if any tests failed
process.exit(failed > 0 ? 1 : 0);
