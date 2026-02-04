#!/usr/bin/env node

/**
 * Versions a WordPress plugin.
 *
 * Ported over from FaustJS
 * @link https://github.com/wpengine/faustjs/blob/canary/scripts/versionPlugin.js
 */
const fs = require( 'fs/promises' );
const path = require( 'node:path' );

const readFile = ( filename ) => fs.readFile( filename, { encoding: 'utf8' } );
const writeFile = fs.writeFile;

/**
 * Runs all WordPress plugin versioning operations for this plugin
 * including version bumps and readme.txt changelog updates.
 */
async function versionPlugin() {
	const pluginPath = path.join( __dirname, '../' );
	const pluginFile = path.join( pluginPath, 'wpgraphql-ide.php' );
	const readmeTxt = path.join( pluginPath, 'readme.txt' );
	const changelog = path.join( pluginPath, 'CHANGELOG.md' );

	const version = await getNewVersion( pluginPath );

	if ( version ) {
		await bumpPluginHeader( pluginFile, version );
		await bumpStableTag( readmeTxt, version );
		await bumpVersionConstant( pluginFile, version );
		await generateReadmeChangelog( readmeTxt, changelog );
	}
}

/**
 * Updates the version number found in the header comment of a given
 * WordPress plugin's main PHP file.
 *
 * @param {string} pluginFile Full path to a file containing a WordPress
 *                            plugin header comment.
 * @param {string} version    The new version number.
 */
async function bumpPluginHeader( pluginFile, version ) {
	return bumpVersion(
		pluginFile,
		/^\s*\*\s*Version:\s*([0-9.]+)\s*$/gm,
		version
	);
}

/**
 * Updates the stable tag found in a given WordPress plugin's readme.txt file.
 *
 * @param {string} readmeTxt Full path to a file containing a WordPress
 *                           readme.txt file.
 * @param {string} version   The new version number.
 */
async function bumpStableTag( readmeTxt, version ) {
	return bumpVersion( readmeTxt, /^Stable tag:\s*([0-9.]+)$/gm, version );
}

/**
 * Updates the version constant found in the wpgraphql-ide.php file.
 *
 * @param {string} pluginFile Full path to a file containing PHP constants.
 * @param {string} version    The new version number.
 */
async function bumpVersionConstant( pluginFile, version ) {
	return bumpVersion(
		pluginFile,
		/^\s*define\(\s*'WPGRAPHQL_IDE_VERSION',\s*'([0-9.]+)'\s*\);/gm,
		version
	);
}

/**
 * Replaces the version number in the first line of a file matching the given
 * regular expression.
 *
 * Note that this function depends on a properly formatted regular expression.
 * The given regex should meet the following criteria:
 *
 *   1. Begins with ^ and ends with $ so that we can match an entire line.
 *   2. Contains one and only one capturing group that matches only the version
 *      number portion of the line. For example, in the line " * Version: 1.0.0"
 *      capturing group 1 of the regex must resolve to "1.0.0".
 *
 * @param {string} file    Full path to the file to update.
 * @param {RegExp} regex   A valid regular expression as noted above.
 * @param {string} version The new version number.
 */
async function bumpVersion( file, regex, version ) {
	try {
		let data = await readFile( file );
		const matches = regex.exec( data );

		if ( ! matches ) {
			throw new Error( `Version string does not exist in ${ file }` );
		}

		// Replace the version number in the captured line.
		const versionString = matches[ 0 ].replace( matches[ 1 ], version );

		// Replace the captured line with the new version string.
		data = data.replace( matches[ 0 ], versionString );

		return writeFile( file, data );
	} catch ( e ) {
		console.warn( e );
	}
}

/**
 * Get the current version number from a plugin's package.json file.
 *
 * @param {string} pluginPath Full path to the directory containing the plugin's
 *                            package.json file.
 * @return {string|undefined} The version number string found in the plugin's package.json.
 */
async function getNewVersion( pluginPath ) {
	const packageJsonFile = path.join( pluginPath, 'package.json' );

	try {
		const packageJson = await readFile( packageJsonFile );

		return JSON.parse( packageJson )?.version;
	} catch ( e ) {
		if ( e instanceof SyntaxError ) {
			e.message = `${ e.message } in ${ packageJsonFile }.\n`;
		}

		console.warn( e );
	}
}

/**
 * Updates the plugin's readme.txt changelog with the latest 3 releases
 * found in the plugin's CHANGELOG.md file.
 *
 * @param {string} readmeTxtFile Full path to the plugin's readme.txt file.
 * @param {string} changelog     Full path to the plugin's CHANGELOG.md file.
 */
async function generateReadmeChangelog( readmeTxtFile, changelog ) {
	let output = '';

	try {
		const readmeTxt = await readFile( readmeTxtFile );
		let changelogContent = await readFile( changelog );

		// Remove the "# Changelog" header if it exists in CHANGELOG.md
		changelogContent = changelogContent.replace( '# Changelog', '' );

		// Split the contents by new line
		const changelogLines = changelogContent.split( /\r?\n/ );
		const processedLines = [];
		let versionCount = 0;

		// Process all lines in current version
		changelogLines.every( ( line ) => {
			// Version numbers in CHANGELOG.md are h2
			if ( line.startsWith( '## ' ) ) {
				if ( versionCount == 3 ) {
					return false; // Stop processing after 3 versions
				}
				// Format version number for WordPress
				line = line.replace( '## ', '= ' ) + ' =';
				versionCount++;
			}

			processedLines.push( line );

			return true; // Continue processing
		} );

		changelogContent = processedLines.join( '\n' );

		const changelogStart = readmeTxt.indexOf( '== Changelog ==' );
		const readmeTxtBeforeChangelog = readmeTxt.substring(
			0,
			changelogStart + '== Changelog =='.length
		);

		// Combine the original part of readme.txt up to the changelog section with the new changelog content
		output = readmeTxtBeforeChangelog + changelogContent;
		output +=
			'\n[View the full changelog](https://github.com/wp-graphql/wpgraphql-ide/blob/main/CHANGELOG.md)';

		return writeFile( readmeTxtFile, output );
	} catch ( e ) {
		console.warn( e );
	}
}

versionPlugin();
