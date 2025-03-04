#!/usr/bin/env node

const { createChangeset } = require('./generate-changeset');
const { updateVersions, getCurrentVersions, resetVersions } = require('./version-management');
const { updateAllSinceTags } = require('./update-since-tags');
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
    return [
        {
            summary: 'feat: Add new GraphQL field',
            type: 'minor',
            pr: 123,
            pr_number: '123',
            pr_url: 'https://github.com/wp-graphql/wp-graphql/pull/123',
            breaking: false,
            breaking_changes: '',
            upgrade_instructions: '',
            releases: [{ name: 'wp-graphql', type: 'minor' }]
        },
        {
            summary: 'feat!: Breaking API change',
            type: 'major',
            pr: 124,
            pr_number: '124',
            pr_url: 'https://github.com/wp-graphql/wp-graphql/pull/124',
            breaking: true,
            breaking_changes: 'This changes the way mutations handle input validation',
            upgrade_instructions: 'Update your mutation input to include the new required fields',
            releases: [{ name: 'wp-graphql', type: 'major' }]
        },
        {
            summary: 'fix: Resolve N+1 query issue',
            type: 'patch',
            pr: 125,
            pr_number: '125',
            pr_url: 'https://github.com/wp-graphql/wp-graphql/pull/125',
            breaking: false,
            breaking_changes: '',
            upgrade_instructions: '',
            releases: [{ name: 'wp-graphql', type: 'patch' }]
        },
        {
            summary: 'docs: Update API documentation',
            type: 'patch',
            pr: 126,
            pr_number: '126',
            pr_url: 'https://github.com/wp-graphql/wp-graphql/pull/126',
            breaking: false,
            breaking_changes: '',
            upgrade_instructions: '',
            releases: [{ name: 'wp-graphql', type: 'patch' }]
        }
    ];
}

/**
 * Calculate next version based on current version and changesets
 */
function calculateNextVersion(currentVersion, changesets) {
    const [major, minor, patch] = currentVersion.split('.').map(Number);

    // Check for breaking changes
    const hasBreakingChanges = changesets.some(
        changeset => changeset.breaking || changeset.breaking_changes || changeset.type === 'major'
    );
    if (hasBreakingChanges) {
        return `${major + 1}.0.0`;
    }

    // Check for features (minor changes)
    const hasFeatures = changesets.some(
        changeset => changeset.type === 'minor' || changeset.summary.startsWith('feat:')
    );
    if (hasFeatures) {
        return `${major}.${minor + 1}.0`;
    }

    // Otherwise, it's a patch
    return `${major}.${minor}.${patch + 1}`;
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
        console.log(chalk.blue('\nreadme.txt preview:'));
        console.log(chalk.yellow('----------------------------------------'));
        const txtContent = formatReadmeTxt(nextVersion, changesets);
        console.log(txtContent);
        console.log(chalk.yellow('----------------------------------------'));

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
                const [, frontmatter] = content.split('---\n');
                if (frontmatter) {
                    const changeset = JSON.parse(frontmatter);
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
async function simulateVersionUpdate(version, options = {}) {
    try {
        // Get current version
        const beforeVersions = getCurrentVersions();
        const currentVersion = beforeVersions.package;

        // Get changesets and calculate next version if not provided
        const changesets = await readActualChangesets();
        const nextVersion = version || calculateNextVersion(currentVersion, changesets);

        console.log(chalk.blue('\nSimulating Version Update:'));
        console.log('Current Version:', currentVersion);
        console.log('Next Version:', nextVersion);
        console.log('Reason:', getVersionBumpReason(changesets));
        console.log('Options:', options);

        // Show current versions
        console.log(chalk.blue('\nCurrent versions:'));
        Object.entries(beforeVersions).forEach(([file, ver]) => {
            console.log(`${file}: ${ver}`);
        });

        // Update versions
        await updateVersions(nextVersion, options.beta);
        console.log(chalk.green('\n✓ Version numbers updated'));

        // Show new versions
        console.log(chalk.blue('\nUpdated versions:'));
        const afterVersions = getCurrentVersions();
        Object.entries(afterVersions).forEach(([file, ver]) => {
            const changed = beforeVersions[file] !== ver;
            console.log(`${file}: ${chalk[changed ? 'green' : 'gray'](ver)}`);
        });

        // Show @since tag updates
        const sinceResults = await updateAllSinceTags(nextVersion);
        if (sinceResults.updated.length > 0) {
            console.log(chalk.green(`\n✓ Updated ${sinceResults.updated.length} files with @since tags:`));
            sinceResults.updated.forEach(file => {
                console.log(chalk.gray(`  - ${file}`));
            });
        }

        return true;
    } catch (error) {
        console.error(chalk.red('\n❌ Error updating versions:'), error.message);
        throw error;
    }
}

/**
 * Get a human-readable reason for the version bump
 */
function getVersionBumpReason(changesets) {
    const breakingChanges = changesets.filter(c => c.breaking || c.breaking_changes || c.type === 'major');
    const features = changesets.filter(c => c.type === 'minor' || c.summary.startsWith('feat:'));
    const fixes = changesets.filter(c => c.type === 'patch' || c.summary.startsWith('fix:'));

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
                await simulateVersionUpdate(args[1], {
                    beta: args.includes('--beta')
                });
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