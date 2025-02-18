const { getCurrentVersions } = require('./version-management');

async function checkVersions() {
    // Dynamic import of chalk
    const { default: chalk } = await import('chalk');

    try {
        const versions = getCurrentVersions();

        console.log('\nCurrent versions across files:');
        console.log('=============================');

        // Display versions
        Object.entries(versions).forEach(([file, version]) => {
            const color = version ? chalk.green : chalk.red;
            console.log(`${chalk.bold(file.padEnd(12))}: ${color(version || 'NOT FOUND')}`);
        });

        // Check for mismatches
        const uniqueVersions = new Set(Object.values(versions).filter(Boolean));

        console.log('\nValidation:');
        console.log('===========');

        if (uniqueVersions.size > 1) {
            console.log(chalk.red('❌ Version mismatch detected!'));
            return process.exit(1);
        }

        // Check if any versions are missing
        const missingVersions = Object.entries(versions)
            .filter(([_, version]) => !version)
            .map(([file]) => file);

        if (missingVersions.length > 0) {
            console.log(chalk.red(`❌ Missing versions in: ${missingVersions.join(', ')}`));
            return process.exit(1);
        }

        console.log(chalk.green('✓ All versions match:'), chalk.bold([...uniqueVersions][0]));

        // Check if current version is beta
        if ([...uniqueVersions][0].includes('-beta')) {
            console.log(chalk.yellow('\nNote: Current version is a beta release'));
            if (versions.readme === versions.package) {
                console.log(chalk.red('❌ Warning: Stable tag should not match beta version'));
                return process.exit(1);
            }
            console.log(chalk.green('✓ Stable tag correctly maintained at:', versions.readme));
        }

        return process.exit(0);
    } catch (error) {
        const errorMessage = error.message || 'Unknown error';
        console.error('\n❌ Error checking versions:', errorMessage);
        return process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    checkVersions().catch(error => {
        console.error('Failed to check versions:', error);
        process.exit(1);
    });
}

module.exports = checkVersions;