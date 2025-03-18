const fs = require('fs');
const path = require('path');
const { updateAllSinceTags } = require('./update-since-tags');

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
 * Default version to use when resetting
 */
const DEFAULT_VERSION = '2.1.0';

/**
 * Validate version string format
 */
function isValidVersion(version) {
    // Match standard version (2.1.0) or beta version (2.1.0-beta.1)
    return /^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/.test(version);
}

/**
 * Get current versions from all files
 */
function getCurrentVersions(skipReset = false) {
    const versions = {
        php: null,
        constants: null,
        package: null,
        readme: null
    };

    try {
        // Get version from wp-graphql.php
        if (fs.existsSync(VERSION_FILES.php)) {
            const phpContent = fs.readFileSync(VERSION_FILES.php, 'utf8');
            const phpMatch = phpContent.match(/Version:\s*([0-9.]+(-[a-zA-Z0-9.]+)?)/);
            versions.php = phpMatch ? phpMatch[1] : null;
        }

        // Get version from constants.php
        if (fs.existsSync(VERSION_FILES.constants)) {
            const constantsContent = fs.readFileSync(VERSION_FILES.constants, 'utf8');
            const constantsMatch = constantsContent.match(/WPGRAPHQL_VERSION',\s*'([0-9.]+(-[a-zA-Z0-9.]+)?)'/);
            versions.constants = constantsMatch ? constantsMatch[1] : null;
        }

        // Get version from package.json
        if (fs.existsSync(VERSION_FILES.package)) {
            const packageContent = JSON.parse(fs.readFileSync(VERSION_FILES.package, 'utf8'));
            versions.package = packageContent.version;
        }

        // Get stable tag from readme.txt
        if (fs.existsSync(VERSION_FILES.readme)) {
            const readmeContent = fs.readFileSync(VERSION_FILES.readme, 'utf8');
            const readmeMatch = readmeContent.match(/Stable tag:\s*([0-9.]+(-[a-zA-Z0-9.]+)?)/);
            versions.readme = readmeMatch ? readmeMatch[1] : null;
        }

        // Check if any versions are invalid or missing
        Object.keys(versions).forEach(key => {
            const version = versions[key];
            if (!version || !isValidVersion(version)) {
                console.warn(`Warning: Invalid or missing version in ${key}: ${version}`);
                versions[key] = DEFAULT_VERSION;
            }
        });

        return versions;
    } catch (error) {
        console.error('Error reading version files:', error);
        console.error('Current working directory:', process.cwd());
        console.error('Files checked:', VERSION_FILES);

        // Return default versions on error
        return {
            php: DEFAULT_VERSION,
            constants: DEFAULT_VERSION,
            package: DEFAULT_VERSION,
            readme: DEFAULT_VERSION
        };
    }
}

/**
 * Reset all version numbers to a known good state
 */
function resetVersions(version = DEFAULT_VERSION) {
    if (!isValidVersion(version)) {
        throw new Error(`Invalid version format: ${version}`);
    }

    try {
        // Update wp-graphql.php version
        if (fs.existsSync(VERSION_FILES.php)) {
            let content = fs.readFileSync(VERSION_FILES.php, 'utf8');
            content = content.replace(/(Version:\s*).+/, `$1${version}`);
            fs.writeFileSync(VERSION_FILES.php, content);
        } else {
            console.warn(`${VERSION_FILES.php} not found, skipping`);
        }

        // Update constants.php version
        if (fs.existsSync(VERSION_FILES.constants)) {
            let content = fs.readFileSync(VERSION_FILES.constants, 'utf8');
            content = content.replace(/(WPGRAPHQL_VERSION',\s*').+(')/, `$1${version}$2`);
            fs.writeFileSync(VERSION_FILES.constants, content);
        } else {
            console.warn(`${VERSION_FILES.constants} not found, skipping`);
        }

        // Update package.json version
        if (fs.existsSync(VERSION_FILES.package)) {
            let packageJson = JSON.parse(fs.readFileSync(VERSION_FILES.package, 'utf8'));
            packageJson.version = version;
            fs.writeFileSync(VERSION_FILES.package, JSON.stringify(packageJson, null, 2) + '\n');
        } else {
            console.warn(`${VERSION_FILES.package} not found, skipping`);
        }

        // Update readme.txt stable tag
        if (fs.existsSync(VERSION_FILES.readme)) {
            let content = fs.readFileSync(VERSION_FILES.readme, 'utf8');
            content = content.replace(/(Stable tag:\s*).+/, `$1${version}`);
            fs.writeFileSync(VERSION_FILES.readme, content);
        } else {
            console.warn(`${VERSION_FILES.readme} not found, skipping`);
        }

        // Return default versions if no files exist
        return {
            php: version,
            constants: version,
            package: version,
            readme: version
        };
    } catch (error) {
        throw new Error(`Error resetting versions: ${error.message}`);
    }
}

/**
 * Validate that versions match across files
 */
function validateVersions(versions) {
    const versionSet = new Set(Object.values(versions).filter(Boolean));

    // For beta releases, readme.txt stable tag should not match other versions
    const isBeta = versions.package && versions.package.includes('-');
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
 * Validate version jump isn't too large
 */
function validateVersionJump(currentVersion, newVersion) {
    const [currentMajor, currentMinor, currentPatch] = currentVersion.split('.').map(Number);
    const [newMajor, newMinor, newPatch] = newVersion.split('.').map(Number);

    // Don't allow jumping more than one major version
    if (newMajor > currentMajor + 1) {
        throw new Error(`Version jump too large: ${currentVersion} → ${newVersion}. Cannot increment major version by more than 1.`);
    }

    // If same major, don't allow jumping more than one minor version
    if (newMajor === currentMajor && newMinor > currentMinor + 1) {
        throw new Error(`Version jump too large: ${currentVersion} → ${newVersion}. Cannot increment minor version by more than 1.`);
    }

    // If same major and minor, don't allow jumping more than one patch version
    if (newMajor === currentMajor && newMinor === currentMinor && newPatch > currentPatch + 1) {
        throw new Error(`Version jump too large: ${currentVersion} → ${newVersion}. Cannot increment patch version by more than 1.`);
    }

    return true;
}

/**
 * Update version numbers across all files
 */
async function updateVersions(newVersion, isBeta = false) {
    try {
        // Get current versions
        const currentVersions = getCurrentVersions();

        // Validate version jump
        validateVersionJump(currentVersions.package, newVersion);

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

        // Update @since tags
        await updateAllSinceTags(newVersion);

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
    updateVersions,
    resetVersions,
    isValidVersion
};