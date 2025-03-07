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
    // Get the package name
    let packageName = 'wp-graphql';
    try {
        const packageJsonPath = path.join(process.cwd(), 'package.json');
        if (fs.existsSync(packageJsonPath)) {
            const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
            packageName = packageJson.name || packageName;
        }
    } catch (error) {
        console.warn('Error reading package.json:', error.message);
    }

    return [
        {
            type: 'minor',
            pr: 123,
            breaking: false,
            summary: 'feat: Add new GraphQL field',
            content: `### feat: Add new GraphQL field

[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)

#### Description
Adds a new GraphQL field \`customField\` to the Post type that exposes custom meta data.`,
            packageName: packageName
        },
        {
            type: 'major',
            pr: 124,
            breaking: true,
            summary: 'feat!: Breaking API change',
            content: `### feat!: Breaking API change

[PR #124](https://github.com/wp-graphql/wp-graphql/pull/124)

#### Description
Changes the way mutations handle input validation.

#### Breaking Changes
This changes the way mutations handle input validation. The previous approach of passing validation options as a second argument is no longer supported.

#### Upgrade Instructions
Update your mutation input to include the new required fields and remove any separate validation options.`,
            packageName: packageName
        },
        {
            type: 'patch',
            pr: 125,
            breaking: false,
            summary: 'fix: Resolve N+1 query issue',
            content: `### fix: Resolve N+1 query issue

[PR #125](https://github.com/wp-graphql/wp-graphql/pull/125)

#### Description
Fixes a performance issue with nested queries.`,
            packageName: packageName
        }
    ];
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
    // Get current version
    const currentVersions = getCurrentVersions();
    const currentVersion = currentVersions.package;

    // Get changesets (either from test data or actual .changeset directory)
    const changesets = options.useTestData ? createTestChangesets() : await readActualChangesets();

    // Calculate next version if not provided
    const nextVersion = version || calculateNextVersion(currentVersion, changesets);

    console.log(chalk.blue('\nSimulating Changelog Generation:'));
    console.log('Current Version:', currentVersion);
    console.log('Next Version:', nextVersion);
    console.log('Options:', options);

    try {
        // Generate CHANGELOG.md content
        console.log(chalk.blue('\nCHANGELOG.md preview:'));
        console.log(chalk.yellow('----------------------------------------'));
        const mdContent = formatChangelogMd(nextVersion, changesets);
        console.log(mdContent);
        console.log(chalk.yellow('----------------------------------------'));

        // Generate readme.txt content
        console.log(chalk.blue('\nreadme.txt changelog preview:'));
        console.log(chalk.yellow('----------------------------------------'));
        const txtContent = formatReadmeTxt(nextVersion, changesets);
        console.log(txtContent);
        console.log(chalk.yellow('----------------------------------------'));

        // Generate upgrade notice content
        const { formatUpgradeNotice } = require('./changelog-formatters/readme-txt');
        const upgradeNotice = formatUpgradeNotice(nextVersion, changesets);

        if (upgradeNotice) {
            console.log(chalk.blue('\nreadme.txt upgrade notice preview:'));
            console.log(chalk.yellow('----------------------------------------'));
            console.log(upgradeNotice);
            console.log(chalk.yellow('----------------------------------------'));
        }

        // Show what would be updated
        if (!options.dryRun) {
            console.log(chalk.blue('\nFiles that would be updated:'));
            console.log(chalk.gray('- CHANGELOG.md'));
            console.log(chalk.gray('- readme.txt'));

            if (options.write) {
                // Actually write the files if --write flag is used
                const changelogPath = path.join(process.cwd(), 'CHANGELOG.md');
                const readmePath = path.join(process.cwd(), 'readme.txt');

                let changelogContent = fs.existsSync(changelogPath)
                    ? fs.readFileSync(changelogPath, 'utf8')
                    : '# Changelog\n\n';

                // Insert new content after the title
                const lines = changelogContent.split('\n');
                lines.splice(2, 0, mdContent);
                fs.writeFileSync(changelogPath, lines.join('\n'));

                // Update readme.txt
                let readmeContent = fs.readFileSync(readmePath, 'utf8');
                readmeContent = readmeContent.replace(
                    /(== Changelog ==\n\n)/,
                    `$1${txtContent}`
                );

                // Add or update upgrade notice if needed
                if (upgradeNotice) {
                    if (readmeContent.includes('== Upgrade Notice ==')) {
                        readmeContent = readmeContent.replace(
                            /(== Upgrade Notice ==\n\n)/,
                            `$1${upgradeNotice}\n\n`
                        );
                    } else {
                        readmeContent += `\n\n== Upgrade Notice ==\n\n${upgradeNotice}\n`;
                    }
                }

                fs.writeFileSync(readmePath, readmeContent);

                console.log(chalk.green('\n✓ Files updated successfully'));
            }
        }

        return true;
    } catch (error) {
        console.error(chalk.red('\n❌ Error generating changelog:'), error.message);
        throw error;
    }
}

/**
 * Read actual changesets from the .changeset directory
 */
async function readActualChangesets() {
    const changesetDir = path.join(process.cwd(), '.changeset');
    const changesets = [];

    if (fs.existsSync(changesetDir)) {
        const files = fs.readdirSync(changesetDir);
        for (const file of files) {
            if (file.endsWith('.md') && file !== 'README.md') {
                const content = fs.readFileSync(path.join(changesetDir, file), 'utf8');

                // Extract frontmatter between --- markers
                const frontmatterMatch = content.match(/---\n([\s\S]*?)\n---/);
                if (frontmatterMatch) {
                    const frontmatter = frontmatterMatch[1];

                    // Check if it's JSON or YAML format
                    let changeset;
                    if (frontmatter.trim().startsWith('{')) {
                        // JSON format (old)
                        try {
                            changeset = JSON.parse(frontmatter);
                        } catch (e) {
                            console.error(`Error parsing JSON in ${file}:`, e.message);
                            continue;
                        }
                    } else {
                        // YAML format (new)
                        changeset = {};

                        // Parse the YAML frontmatter
                        const lines = frontmatter.split('\n');
                        for (const line of lines) {
                            if (line.includes(':')) {
                                // Handle package name to bump type mapping
                                if (line.includes('"') && line.includes('major') || line.includes('minor') || line.includes('patch')) {
                                    // This is a package name to bump type mapping
                                    const packageMatch = line.match(/"([^"]+)":\s*(\w+)/);
                                    if (packageMatch) {
                                        // Store the bump type
                                        changeset.type = packageMatch[2];
                                    }
                                } else {
                                    // Handle other metadata
                                    const [key, value] = line.split(':').map(part => part.trim());
                                    if (key && value !== undefined) {
                                        // Convert boolean strings to actual booleans
                                        if (value === 'true') changeset[key] = true;
                                        else if (value === 'false') changeset[key] = false;
                                        else if (!isNaN(value)) changeset[key] = Number(value);
                                        else changeset[key] = value;
                                    }
                                }
                            }
                        }

                        // Extract content after frontmatter for summary
                        const contentMatch = content.match(/---\n[\s\S]*?\n---\n\n([\s\S]*)/);
                        const changeContent = contentMatch ? contentMatch[1] : '';
                        changeset.content = changeContent;

                        // Extract summary from the first line
                        const summaryLine = changeContent.split('\n')[0];
                        changeset.summary = summaryLine.replace(/^### /, '');

                        // Try to extract PR number from content if not in frontmatter
                        if (!changeset.pr && changeContent) {
                            const prMatch = changeContent.match(/\[PR #(\d+)\]/i) ||
                                           changeContent.match(/\[#(\d+)\]/i) ||
                                           changeContent.match(/pull\/(\d+)/i);
                            if (prMatch) {
                                changeset.pr = parseInt(prMatch[1], 10);
                            }
                        }

                        // Extract breaking changes if present
                        const breakingChangesMatch = changeContent.match(/#### Breaking Changes\n([\s\S]*?)(?=\n####|$)/);
                        if (breakingChangesMatch) {
                            changeset.breaking_changes = breakingChangesMatch[1].trim();
                            changeset.breaking = true;
                        }

                        // Extract upgrade instructions if present
                        const upgradeInstructionsMatch = changeContent.match(/#### Upgrade Instructions\n([\s\S]*?)(?=\n####|$)/);
                        if (upgradeInstructionsMatch) {
                            changeset.upgrade_instructions = upgradeInstructionsMatch[1].trim();
                        }
                    }

                    changesets.push(changeset);
                }
            }
        }
    }

    return changesets;
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