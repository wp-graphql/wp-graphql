#!/usr/bin/env node

const { createChangeset } = require('./generate-changeset');
const { updateVersions, getCurrentVersions } = require('./version-management');
const { updateAllSinceTags } = require('./update-since-tags');
const fs = require('fs');
const path = require('path');

// Import chalk dynamically
let chalk;
(async () => {
    const { default: chalkModule } = await import('chalk');
    chalk = chalkModule;
})();

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
    console.log(chalk.blue('\nSimulating Version Update:'));
    console.log('New Version:', version);
    console.log('Options:', options);

    try {
        // Show current versions
        console.log(chalk.blue('\nCurrent versions:'));
        const beforeVersions = getCurrentVersions();
        Object.entries(beforeVersions).forEach(([file, ver]) => {
            console.log(`${file}: ${ver}`);
        });

        // Update versions
        await updateVersions(version, options.beta);
        console.log(chalk.green('\n✓ Version numbers updated'));

        // Show new versions
        console.log(chalk.blue('\nUpdated versions:'));
        const afterVersions = getCurrentVersions();
        Object.entries(afterVersions).forEach(([file, ver]) => {
            const changed = beforeVersions[file] !== ver;
            console.log(`${file}: ${chalk[changed ? 'green' : 'gray'](ver)}`);
        });

        // Show @since tag updates
        const sinceResults = await updateAllSinceTags(version);
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
 * CLI interface
 */
async function main() {
    const { default: chalk } = await import('chalk');
    const args = process.argv.slice(2);
    const command = args[0];

    try {
        switch (command) {
            case 'pr':
                // Simulate PR merge
                await simulatePRMerge({
                    title: args[1],
                    prNumber: args[2],
                    breaking: args[3],
                    upgrade: args[4]
                });
                break;

            case 'version':
                // Simulate version update
                await simulateVersionUpdate(args[1], {
                    beta: args.includes('--beta')
                });
                break;

            default:
                console.log(`
Usage:
  Simulate PR merge:
    npm run simulate pr "feat: New feature" 123 "Breaking change" "Upgrade steps"

  Simulate version update:
    npm run simulate version 2.1.0 [--beta]

Examples:
  # Simulate regular feature PR
  npm run simulate pr "feat: Add new feature" 123

  # Simulate breaking change PR
  npm run simulate pr "feat!: Breaking change" 124 "This breaks something" "Follow these steps"

  # Simulate minor version update
  npm run simulate version 2.1.0

  # Simulate beta release
  npm run simulate version 2.1.0-beta.1 --beta
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
    main();
}

module.exports = {
    simulatePRMerge,
    simulateVersionUpdate
};