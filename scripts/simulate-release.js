require('dotenv').config();
const { getCurrentVersions, updateVersions } = require('./version-management');
const { updateAllSinceTags } = require('./update-since-tags');
const { getReadmeTxtChangelog } = require('./changelog-formatters/readme-txt');
const chalk = require('chalk');

async function simulateRelease(newVersion, options = { beta: false }) {
    try {
        // 1. Check current versions
        console.log(chalk.blue('\n1. Checking current versions...'));
        const currentVersions = getCurrentVersions();
        console.log('Current versions:', currentVersions);

        // 2. Update versions
        console.log(chalk.blue('\n2. Updating version numbers...'));
        await updateVersions(newVersion, options.beta);
        console.log(chalk.green('✓ Version numbers updated'));

        // 3. Update @since tags
        console.log(chalk.blue('\n3. Updating @since todo tags...'));
        const sinceResults = await updateAllSinceTags(newVersion);
        if (sinceResults.updated.length > 0) {
            console.log(chalk.green(`✓ Updated ${sinceResults.updated.length} files with @since tags`));
        } else {
            console.log(chalk.yellow('No @since todo tags found'));
        }

        // 4. Generate changelog
        console.log(chalk.blue('\n4. Generating changelog entries...'));
        const mockRelease = {
            newVersion,
            changesets: [
                // Mock changeset for testing
                {
                    summary: 'feat: Test feature',
                    commit: 'test-commit'
                }
            ]
        };
        const changelog = await getReadmeTxtChangelog(mockRelease, {
            repo: 'wp-graphql/wp-graphql'
        });
        console.log('Changelog preview:', changelog);

        // 5. Final validation
        console.log(chalk.blue('\n5. Validating final state...'));
        const finalVersions = getCurrentVersions();
        console.log('Final versions:', finalVersions);

        console.log(chalk.green('\n✓ Release simulation completed successfully!'));
        return true;
    } catch (error) {
        console.error(chalk.red('\n❌ Release simulation failed:'), error.message);
        throw error;
    }
}

// CLI interface
async function main() {
    try {
        const version = process.argv[2];
        const isBeta = process.argv.includes('--beta');

        if (!version) {
            throw new Error('Version argument is required. Usage: npm run simulate-release <version> [--beta]');
        }

        // Check for GitHub token
        if (!process.env.GITHUB_TOKEN) {
            console.log(chalk.yellow('\nWarning: No GITHUB_TOKEN found in environment. PR links will not be included in changelog.'));
        }

        await simulateRelease(version, { beta: isBeta });
    } catch (error) {
        console.error(error.message);
        process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    main();
}

module.exports = simulateRelease;