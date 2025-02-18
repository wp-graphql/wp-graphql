const fs = require('fs');
const path = require('path');

/**
 * Files that contain version numbers
 */
const VERSION_FILES = {
    php: 'wp-graphql.php',
    constants: 'constants.php',
    package: 'package.json',
    readme: 'readme.txt'
};

/**
 * Get current versions from all files
 */
function getCurrentVersions() {
    const versions = {};

    try {
        // Get version from wp-graphql.php
        const phpContent = fs.readFileSync(VERSION_FILES.php, 'utf8');
        const phpMatch = phpContent.match(/Version:\s*(.+)/);
        versions.php = phpMatch ? phpMatch[1] : null;

        // Get version from constants.php
        const constantsContent = fs.readFileSync(VERSION_FILES.constants, 'utf8');
        const constantsMatch = constantsContent.match(/WPGRAPHQL_VERSION',\s*'(.+)'/);
        versions.constants = constantsMatch ? constantsMatch[1] : null;

        // Get version from package.json
        const packageContent = JSON.parse(fs.readFileSync(VERSION_FILES.package, 'utf8'));
        versions.package = packageContent.version;

        // Get stable tag from readme.txt
        const readmeContent = fs.readFileSync(VERSION_FILES.readme, 'utf8');
        const readmeMatch = readmeContent.match(/Stable tag:\s*(.+)/);
        versions.readme = readmeMatch ? readmeMatch[1] : null;

    } catch (error) {
        throw new Error(`Error reading version files: ${error.message}`);
    }

    return versions;
}

/**
 * Validate that versions match across files
 */
function validateVersions(versions) {
    const versionSet = new Set(Object.values(versions).filter(Boolean));

    // For beta releases, readme.txt stable tag should not match other versions
    const isBeta = versions.package && versions.package.includes('-beta');
    const versionsToCompare = isBeta ?
        { php: versions.php, constants: versions.constants, package: versions.package } :
        versions;

    const nonReadmeVersions = new Set(Object.entries(versionsToCompare)
        .filter(([key]) => key !== 'readme')
        .map(([_, value]) => value)
        .filter(Boolean)
    );

    if (nonReadmeVersions.size > 1) {
        throw new Error('Version mismatch across files: ' + JSON.stringify(versions, null, 2));
    }

    if (isBeta && versions.readme === versions.package) {
        throw new Error('Stable tag should not be updated for beta releases');
    }

    return true;
}

/**
 * Update version numbers across all files
 */
function updateVersions(newVersion, isBeta = false) {
    try {
        // Update wp-graphql.php
        let content = fs.readFileSync(VERSION_FILES.php, 'utf8');
        content = content.replace(/(Version:\s*).+/, `$1${newVersion}`);
        fs.writeFileSync(VERSION_FILES.php, content);

        // Update constants.php
        content = fs.readFileSync(VERSION_FILES.constants, 'utf8');
        content = content.replace(/(WPGRAPHQL_VERSION',\s*').+(')/, `$1${newVersion}$2`);
        fs.writeFileSync(VERSION_FILES.constants, content);

        // Update package.json
        const packageJson = JSON.parse(fs.readFileSync(VERSION_FILES.package, 'utf8'));
        packageJson.version = newVersion;
        fs.writeFileSync(VERSION_FILES.package, JSON.stringify(packageJson, null, 2) + '\n');

        // Update readme.txt stable tag (only for non-beta releases)
        if (!isBeta) {
            content = fs.readFileSync(VERSION_FILES.readme, 'utf8');
            content = content.replace(/(Stable tag:\s*).+/, `$1${newVersion}`);
            fs.writeFileSync(VERSION_FILES.readme, content);
        }

        // Validate the updates
        const versions = getCurrentVersions();
        validateVersions(versions);

        return true;
    } catch (error) {
        throw new Error(`Error updating versions: ${error.message}`);
    }
}

module.exports = {
    getCurrentVersions,
    validateVersions,
    updateVersions
};