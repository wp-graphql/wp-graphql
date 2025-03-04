#!/usr/bin/env node

const { createChangeset } = require('./generate-changeset');
const simulateRelease = require('./simulate-release');
const chalk = require('chalk');
const fs = require('fs');
const path = require('path');

/**
 * Simulate PR merge and changeset generation
 */
async function simulatePRMerge(options = {}) {
    const pr = {
        title: options.title || 'feat: Test feature',
        body: options.body || `What does this implement/fix? Explain your changes.
---
This is a test feature

### Breaking Changes
${options.breaking || ''}

### Upgrade Instructions
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
 * Simulate release preparation
 */
async function simulateRelease(version, options = {}) {
    console.log(chalk.blue('\nSimulating Release:'));
    console.log('Version:', version);
    console.log('Options:', options);

    try {
        await simulateRelease(version, options);
        console.log(chalk.green('\n✓ Release simulation completed'));
    } catch (error) {
        console.error(chalk.red('\n❌ Error simulating release:'), error.message);
        throw error;
    }
}

/**
 * CLI interface
 */
async function main() {
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

            case 'release':
                // Simulate release
                await simulateRelease(args[1], {
                    beta: args.includes('--beta')
                });
                break;

            default:
                console.log(`
Usage:
  Simulate PR merge:
    node scripts/simulate-workflow.js pr "feat: New feature" 123 "Breaking change" "Upgrade steps"

  Simulate release:
    node scripts/simulate-workflow.js release 2.1.0 [--beta]

Examples:
  # Simulate regular feature PR
  node scripts/simulate-workflow.js pr "feat: Add new feature" 123

  # Simulate breaking change PR
  node scripts/simulate-workflow.js pr "feat!: Breaking change" 124 "This breaks something" "Follow these steps"

  # Simulate minor release
  node scripts/simulate-workflow.js release 2.1.0

  # Simulate beta release
  node scripts/simulate-workflow.js release 2.1.0-beta.1 --beta
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
    simulateRelease
};