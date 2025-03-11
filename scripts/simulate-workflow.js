#!/usr/bin/env node

const { createChangeset } = require('./generate-changeset');
const { updateVersions, getCurrentVersions, resetVersions } = require('./version-management');
const { updateAllSinceTags, findSinceTodoFiles, getSincePlaceholders } = require('./update-since-tags');
const { formatChangelogMd } = require('./changelog-formatters/changelog-md');
const { formatReadmeTxt } = require('./changelog-formatters/readme-txt');
const fs = require('fs');
const path = require('path');

// Initialize chalk at the start
let chalk;

/**
 * Initialize chalk
 */
async function initChalk() {
    const { default: chalkModule } = await import('chalk');
    chalk = chalkModule;
}

/**
 * Create test changesets for simulation
 */
function createTestChangesets() {
    const changesets = [
        {
            id: 'test-major-1',
            content: `---
"wp-graphql": major
---

<!-- pr: 123 -->
<!-- breaking: true -->
<!-- contributorUsername: "testuser" -->
<!-- newContributor: false -->

### feat!: Major breaking change

[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)

#### Description
This is a test major change.

#### Breaking Changes
This breaks the API in a significant way.

#### Upgrade Instructions
Follow these steps to upgrade.`
        },
        {
            id: 'test-minor-1',
            content: `---
"wp-graphql": minor
---

<!-- pr: 124 -->
<!-- breaking: false -->
<!-- contributorUsername: "testuser2" -->
<!-- newContributor: true -->

### feat: New feature

[PR #124](https://github.com/wp-graphql/wp-graphql/pull/124)

#### Description
This is a test minor change.`
        },
        {
            id: 'test-patch-1',
            content: `---
"wp-graphql": patch
---

<!-- pr: 125 -->
<!-- breaking: false -->
<!-- contributorUsername: "testuser" -->
<!-- newContributor: false -->

### fix: Bug fix

[PR #125](https://github.com/wp-graphql/wp-graphql/pull/125)

#### Description
This is a test patch change.`
        }
    ];

    // Create the changesets directory if it doesn't exist
    if (!fs.existsSync('.changeset')) {
        fs.mkdirSync('.changeset');
    }

    // Write the test changesets to files
    changesets.forEach(changeset => {
        fs.writeFileSync(`.changeset/${changeset.id}.md`, changeset.content);
    });

    return changesets;
}

/**
 * Calculate next version based on current version and changesets
 */
function calculateNextVersion(currentVersion, changesets) {
    const [major, minor, patch] = currentVersion.split('.').map(Number);

    // Check for breaking changes with null checks
    const hasBreakingChanges = changesets.some(
        changeset => changeset.breaking || changeset.breaking_changes ||
                    (changeset.type && changeset.type === 'major')
    );

    if (hasBreakingChanges) {
        return `${major + 1}.0.0`;
    }

    // Check for features (minor changes) with null checks
    const hasFeatures = changesets.some(
        changeset => (changeset.type && changeset.type === 'minor') ||
                    (changeset.summary && changeset.summary.startsWith('feat:'))
    );

    if (hasFeatures) {
        return `${major}.${minor + 1}.0`;
    }

    // Otherwise, it's a patch
    return `${major}.${minor}.${patch + 1}`;
}

/**
 * Get the next version number
 */
function getNextVersion(currentVersion, forceVersion = false) {
    if (forceVersion) {
        // For forced version, increment the patch number
        const [major, minor, patch] = currentVersion.split('.').map(Number);
        return `${major}.${minor}.${patch + 1}`;
    }

    // Read changesets and calculate version based on changes
    const changesets = fs.existsSync('.changeset')
        ? fs.readdirSync('.changeset').filter(file => file.endsWith('.md') && file !== 'README.md')
        : [];

    if (changesets.length === 0) {
        // No changes, increment patch
        const [major, minor, patch] = currentVersion.split('.').map(Number);
        return `${major}.${minor}.${patch + 1}`;
    }

    return calculateNextVersion(currentVersion, changesets);
}

/**
 * Simulate changelog generation
 */
async function simulateChangelog(version, options = {}) {
    const currentVersion = getCurrentVersions().package;
    const nextVersion = version || getNextVersion(currentVersion);

    console.log('\nSimulating Changelog Generation:');
    console.log('Current Version:', currentVersion);
    console.log('Next Version:', nextVersion);
    console.log('Options:', options);

    // Get changesets
    let changesets = [];
    if (options.useTestData) {
        // Use test data
        createTestChangesets();
        changesets = await readActualChangesets();
    } else {
        // Use actual changesets
        changesets = await readActualChangesets();
    }

    // Debug: Show the changesets we found
    console.log(`\nFound ${changesets.length} changesets:`);
    changesets.forEach((changeset, index) => {
        console.log(`\nChangeset ${index + 1}:`);
        console.log(`  File: ${changeset.file || 'unknown'}`);
        console.log(`  PR: ${changeset.pr || 'unknown'}`);
        console.log(`  Breaking: ${changeset.breaking || false}`);
        console.log(`  Summary: ${changeset.summary || 'unknown'}`);
    });

    // Generate changelog for CHANGELOG.md
    const { formatChangelogMd } = require('./changelog-formatters/changelog-md');
    const changelogMd = formatChangelogMd(nextVersion, changesets);

    console.log('\nCHANGELOG.md preview:');
    console.log('----------------------------------------');
    console.log(changelogMd);
    console.log('----------------------------------------');

    // Generate changelog for readme.txt
    const { formatReadmeTxt } = require('./changelog-formatters/readme-txt');
    const readmeTxt = formatReadmeTxt(nextVersion, changesets);

    console.log('\nreadme.txt changelog preview:');
    console.log('----------------------------------------');
    console.log(readmeTxt);
    console.log('----------------------------------------');

    // Write the files if requested
    if (options.write) {
        const { updateChangelogMd } = require('./changelog-formatters/changelog-md');
        const { updateReadmeTxt } = require('./changelog-formatters/readme-txt');

        await updateChangelogMd({ newVersion: nextVersion, changesets }, options);
        await updateReadmeTxt({ newVersion: nextVersion, changesets }, options);

        console.log('\nChangelog files updated.');
    }

    return {
        changelogMd,
        readmeTxt,
        changesets
    };
}

/**
 * Read actual changesets from the .changeset directory
 */
async function readActualChangesets() {
    try {
        const changesetDir = path.join(process.cwd(), '.changeset');
        const files = fs.readdirSync(changesetDir)
            .filter(file => file.endsWith('.md') && file !== 'README.md');

        const changesets = [];

        // Import the parseChangeset function directly
        const { parseChangeset } = require('./changelog-formatters/changelog-md');

        // Make sure parseChangeset is available
        if (typeof parseChangeset !== 'function') {
            console.error('parseChangeset is not a function. Check the exports in changelog-md.js');
            return [];
        }

        for (const file of files) {
            const filePath = path.join(changesetDir, file);
            const content = fs.readFileSync(filePath, 'utf8');

            // Parse the changeset
            const changeset = parseChangeset(content);

            if (changeset) {
                // Add the file name for debugging
                changeset.file = file;
                changesets.push(changeset);
            } else {
                console.warn(`Failed to parse changeset: ${file}`);
            }
        }

        return changesets;
    } catch (error) {
        console.error('Error reading changesets:', error);
        return [];
    }
}

/**
 * Simulate PR merge and changeset generation
 */
async function simulatePRMerge(options = {}) {
    const pr = {
        title: options.title || 'feat: Test feature',
        body: options.body || `What does this implement/fix? Explain your changes.
---
This is a test feature

## Breaking Changes
${options.breaking || ''}

## Upgrade Instructions
${options.upgrade || ''}`,
        prNumber: options.prNumber || '999',
        prUrl: options.prUrl || 'https://github.com/wp-graphql/wp-graphql/pull/999'
    };

    console.log(chalk.blue('\nSimulating PR Merge:'));
    console.log('Title:', pr.title);
    console.log('PR Number:', pr.prNumber);

    try {
        const result = await createChangeset(pr);
        console.log(chalk.green('\n✓ Changeset created successfully:'));
        console.log(JSON.stringify(result, null, 2));
        return result;
    } catch (error) {
        console.error(chalk.red('\n❌ Error creating changeset:'), error.message);
        throw error;
    }
}

/**
 * Simulate version update
 */
async function simulateVersionUpdate() {
    console.log('\nSimulating Version Update:');

    try {
        // Get current version from package.json
        let currentVersion = '1.0.0'; // Default fallback version
        try {
            const packageJsonPath = path.join(process.cwd(), 'package.json');
            if (fs.existsSync(packageJsonPath)) {
                const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
                currentVersion = packageJson.version || currentVersion;
            } else {
                console.warn('package.json not found, using default version');
            }
        } catch (error) {
            console.warn('Error reading package.json:', error.message);
        }

        // Determine next version
        const forceVersion = process.env.FORCE_VERSION === '1';

        // Get changesets with error handling
        let changesets = [];
        try {
            if (fs.existsSync('.changeset')) {
                const changesetFiles = fs.readdirSync('.changeset')
                    .filter(file => file.endsWith('.md') && file !== 'README.md');

                if (changesetFiles.length > 0) {
                    changesets = await readActualChangesets();
                }
            }
        } catch (error) {
            console.warn('Error reading changesets:', error.message);
        }

        const nextVersion = getNextVersion(currentVersion, forceVersion);

        console.log('Current Version:', currentVersion);
        console.log('Next Version:  ', nextVersion);
        console.log('Version Jump:  ', `${currentVersion} → ${nextVersion}`);
        console.log('Reason:', forceVersion ? 'Forced version bump' : getVersionBumpReason(changesets));

        console.log('\nScanning for @since tags to update...');

        // Use the functions from update-since-tags.js
        try {
            const files = await findSinceTodoFiles();
            console.log('\nProcessing files for @since tags...');

            const results = [];
            for (const file of (files || [])) {
                try {
                    const content = fs.readFileSync(file, 'utf8');
                    const count = getSincePlaceholders(content);
                    if (count > 0) {
                        results.push({ file, count });
                        console.log(`Found ${count} @since tags in ${file}`);
                    }
                } catch (error) {
                    console.error(`Error processing file ${file}:`, error.message);
                }
            }

            if (results.length > 0) {
                console.log('\nFound files with @since tags to update:');
                results.forEach(({ file, count }) => {
                    console.log(`- ${file} (${count} tags)`);
                });
            } else {
                console.log('\nNo files found with @since tags to update');
            }

            const totalTags = results.reduce((sum, { count }) => sum + count, 0);
            console.log(`Total tags to update: ${totalTags}\n`);

        } catch (error) {
            console.error('Error scanning for @since tags:', error);
        }

        // Show current versions
        console.log('\nCurrent versions:');
        console.log('php:', currentVersion);
        console.log('constants:', currentVersion);
        console.log('package:', currentVersion);
        console.log('readme:', currentVersion);

        // Simulate updating versions
        console.log('\n✓ Version numbers updated');

        // Show updated versions
        console.log('\nUpdated versions:');
        console.log('php:', nextVersion);
        console.log('constants:', nextVersion);
        console.log('package:', nextVersion);
        console.log('readme:', nextVersion);
    } catch (error) {
        console.error('Error in simulateVersionUpdate:', error);
        throw error;
    }
}

/**
 * Get a human-readable reason for the version bump
 */
function getVersionBumpReason(changesets) {
    const breakingChanges = changesets.filter(c =>
        c.breaking || c.breaking_changes || (c.type && c.type === 'major')
    );

    const features = changesets.filter(c =>
        (c.type && c.type === 'minor') || (c.summary && c.summary.startsWith('feat:'))
    );

    const fixes = changesets.filter(c =>
        (c.type && c.type === 'patch') || (c.summary && c.summary.startsWith('fix:'))
    );

    if (breakingChanges.length > 0) {
        return chalk.red(`Major version bump due to ${breakingChanges.length} breaking change(s)`);
    }
    if (features.length > 0) {
        return chalk.yellow(`Minor version bump due to ${features.length} new feature(s)`);
    }
    if (fixes.length > 0) {
        return chalk.green(`Patch version bump due to ${fixes.length} fix(es)`);
    }
    return chalk.gray('No changes detected');
}

/**
 * CLI interface
 */
async function main() {
    // Initialize chalk first
    await initChalk();

    const args = process.argv.slice(2);
    const command = args[0];

    try {
        switch (command) {
            case 'pr':
                await simulatePRMerge({
                    title: args[1],
                    prNumber: args[2],
                    breaking: args[3],
                    upgrade: args[4]
                });
                break;

            case 'version':
                await simulateVersionUpdate();
                break;

            case 'changelog':
                await simulateChangelog(args[1], {
                    beta: args.includes('--beta'),
                    dryRun: !args.includes('--write'),
                    write: args.includes('--write'),
                    useTestData: args.includes('--test-data')
                });
                break;

            case 'reset':
                const resetVersion = args[1] || '2.1.0';
                console.log(chalk.blue('\nResetting version files to:', resetVersion));
                const versions = resetVersions(resetVersion);
                console.log(chalk.green('\n✓ Files reset successfully'));
                console.log('\nCurrent versions:');
                Object.entries(versions).forEach(([file, ver]) => {
                    console.log(`${file}: ${ver}`);
                });
                break;

            default:
                console.log(`
Usage:
  Simulate PR merge:
    npm run simulate pr "feat: New feature" 123 "Breaking change" "Upgrade steps"

  Simulate version update:
    npm run simulate version 2.1.0 [--beta]

  Simulate changelog:
    npm run simulate changelog 2.1.0 [--beta] [--write]

  Reset version files:
    npm run simulate reset [version]

Examples:
  # Simulate regular feature PR
  npm run simulate pr "feat: Add new feature" 123

  # Simulate breaking change PR
  npm run simulate pr "feat!: Breaking change" 124 "This breaks something" "Follow these steps"

  # Simulate minor version update
  npm run simulate version 2.1.0

  # Simulate beta release
  npm run simulate version 2.1.0-beta.1 --beta

  # Preview changelog changes
  npm run simulate changelog 2.1.0

  # Actually write changelog changes
  npm run simulate changelog 2.1.0 --write

  # Reset version files to known good state
  npm run simulate reset [version]
`);
                process.exit(1);
        }
    } catch (error) {
        console.error(chalk.red('\nError:'), error.message);
        process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    main().catch(error => {
        console.error('Failed to run simulation:', error);
        process.exit(1);
    });
}

module.exports = {
    simulatePRMerge,
    simulateVersionUpdate,
    simulateChangelog
};