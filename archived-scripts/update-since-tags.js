const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

/**
 * Find files containing @since todo tags
 */
async function findSinceTodoFiles(pattern = 'src/**/*.php') {
    try {
        console.log('\nDebug: Current working directory:', process.cwd());
        console.log('Debug: Looking for files matching pattern:', pattern);

        // Use glob.sync instead of async glob for simpler handling
        const files = glob.sync(pattern, {
            ignore: [
                'node_modules/**',
                'vendor/**',
                'phpcs/**',
                '.github/**',
                '.wordpress-org/**',
                'bin/**',
                'build/**',
                'docker/**',
                'img/**',
                'phpstan/**',
                'docs/**'
            ],
            dot: false, // Ignore dot directories
            cwd: process.cwd()
        });

        console.log('Debug: Found files:', files);

        return files || [];
    } catch (error) {
        console.error('Error finding files:', error);
        return [];
    }
}

/**
 * Get all @since placeholders from a file
 */
function getSincePlaceholders(content) {
    // Update regex to match all our valid placeholders
    const regex = /@since\s+(todo|next-version|tbd)|@next-version/gi;
    const matches = content.match(regex);
    return matches ? matches.length : 0;
}

/**
 * Update @since placeholders in a file
 */
function updateSinceTags(filePath, version) {
    try {
        let content = fs.readFileSync(filePath, 'utf8');
        const originalContent = content;

        // Replace placeholders with the actual version
        content = content.replace(
            /@since\s+(todo|tbd|next-version)|@next-version/gi,
            `@since ${version}`  // Use the provided version instead of 'next-version'
        );

        // Only write if content changed
        if (content !== originalContent) {
            fs.writeFileSync(filePath, content);
            return true;
        }

        return false;
    } catch (error) {
        throw new Error(`Error updating ${filePath}: ${error.message}`);
    }
}

/**
 * Update all @since todo tags in the project
 */
async function updateAllSinceTags(version, pattern = '**/*.php') {
    const results = {
        updated: [],
        errors: []
    };

    try {
        const files = await findSinceTodoFiles(pattern);
        console.log('Debug: Processing files:', files);

        for (const file of files) {
            try {
                const content = fs.readFileSync(file, 'utf8');
                const count = getSincePlaceholders(content);
                console.log(`Debug: File ${file} has ${count} @since tags`);

                if (count > 0) {
                    const updated = updateSinceTags(file, version);
                    if (updated) {
                        results.updated.push(file);
                    }
                }
            } catch (error) {
                results.errors.push({ file, error: error.message });
            }
        }

        return results;
    } catch (error) {
        throw new Error(`Error updating @since tags: ${error.message}`);
    }
}

/**
 * CLI command to update @since tags
 */
async function main() {
    const { default: chalk } = await import('chalk');

    try {
        const version = process.argv[2];
        if (!version) {
            throw new Error('Version argument is required');
        }

        console.log(chalk.blue('\nUpdating @since todo tags...'));
        const results = await updateAllSinceTags(version);

        if (results.updated.length > 0) {
            console.log(chalk.green('\n✓ Updated files:'));
            results.updated.forEach(file => {
                console.log(chalk.gray(`  - ${path.relative(process.cwd(), file)}`));
            });
        } else {
            console.log(chalk.yellow('\nNo @since todo tags found'));
        }

        if (results.errors.length > 0) {
            console.log(chalk.red('\n❌ Errors:'));
            results.errors.forEach(({ file, error }) => {
                console.log(chalk.gray(`  - ${path.relative(process.cwd(), file)}: ${error}`));
            });
            process.exit(1);
        }

        process.exit(0);
    } catch (error) {
        console.error(chalk.red('\n❌ Error:'), error.message);
        process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    main();
}

/**
 * Get count of @since todo tags in content
 */
function getSinceTodoTags(content) {
    // Update to match all non-standard placeholders
    const regex = /@since\s+(todo|tbd)|@next-version/gi;
    const matches = content.match(regex);
    return matches ? matches.length : 0;
}

module.exports = {
    findSinceTodoFiles,
    getSincePlaceholders,
    updateSinceTags,
    updateAllSinceTags,
    getSinceTodoTags
};