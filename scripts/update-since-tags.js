const fs = require('fs');
const path = require('path');
const { glob } = require('glob');
const chalk = require('chalk');

/**
 * Find files containing @since and deprecated version placeholder tags
 */
async function findSinceTodoFiles(pattern = '**/*.php') {
    try {
        console.log(chalk.blue('\nScanning for `@since` and deprecated version placeholder tags...'));
        
        // Define specific directories to scan
        const includePaths = [
            '*.php',           // Root PHP files
            'src/**/*.php',    // All PHP files in src directory
            'tests/**/*.php'   // All PHP files in tests directory
        ];

        // Define directories to always ignore
        const ignorePaths = [
            'vendor/**',           // Third-party dependencies
            'node_modules/**',     // NPM dependencies
            'wp-content/**',       // WordPress content directory
            '.wordpress-org/**',   // WordPress.org assets
            '.git/**',            // Git directory
            '.github/**',         // GitHub specific files
            'bin/**',             // Binary files
            'build/**',           // Build artifacts
            'dist/**',            // Distribution files
            'assets/**',          // Asset files
            'docs/**',            // Documentation
            'languages/**',       // Translation files
            'logs/**',            // Log files
            'temp/**',            // Temporary files
            'tmp/**',             // Temporary files
            'cache/**'            // Cache files
        ];

        console.log(chalk.gray('Scanning directories:', includePaths.join(', ')));
        console.log(chalk.gray('Ignoring directories:', ignorePaths.join(', ')));

        // Get files from each include path
        const allFiles = [];
        for (const includePath of includePaths) {
            const files = glob.sync(includePath, {
                ignore: ignorePaths,
                dot: false,
                cwd: process.cwd(),
                nodir: true, // Don't include directories in the results
                absolute: false // Get relative paths initially
            });
            allFiles.push(...files);
        }

        // Remove duplicates and convert to absolute paths
        const uniqueFiles = [...new Set(allFiles)].map(file => path.resolve(process.cwd(), file));
        
        console.log(chalk.gray(`Found ${uniqueFiles.length} PHP files to scan`));
        console.log(chalk.gray('Files found:', uniqueFiles));
        
        return uniqueFiles;
    } catch (error) {
        console.error(chalk.red('Error finding files:', error.message));
        return [];
    }
}

/**
 * Get all @since and deprecated version placeholders from a file
 */
function getSincePlaceholders(content) {
    // Look for both @since placeholders and standalone @next-version
    const sinceRegex = /@since\s+(todo|next-version|tbd)/gi;
    const nextVersionRegex = /@next-version/gi;
    
    const sinceMatches = content.match(sinceRegex) || [];
    const nextVersionMatches = content.match(nextVersionRegex) || [];
    
    return sinceMatches.length + nextVersionMatches.length;
}

/**
 * Update @since and deprecated version placeholders in a file
 */
function updateSinceTags(filePath, version, dryRun = false) {
    try {
        let content = fs.readFileSync(filePath, 'utf8');
        const originalContent = content;
        const placeholderCount = getSincePlaceholders(content);

        if (placeholderCount === 0) {
            return { updated: false, count: 0 };
        }

        if (dryRun) {
            // In dry run mode, just return what would be updated
            return { updated: true, count: placeholderCount };
        }

        // First replace @since placeholders
        content = content.replace(
            /@since\s+(todo|tbd|next-version)/gi,
            `@since ${version}`
        );

        // Then replace all standalone @next-version occurrences
        content = content.replace(
            /@next-version/gi,
            version
        );

        if (content !== originalContent) {
            fs.writeFileSync(filePath, content);
            return { updated: true, count: placeholderCount };
        }

        return { updated: false, count: 0 };
    } catch (error) {
        throw new Error(`Error updating ${filePath}: ${error.message}`);
    }
}

/**
 * Update all @since and deprecated version placeholder tags in the project
 */
async function updateAllSinceTags(version, pattern = '**/*.php', dryRun = false) {
    const results = {
        updated: [],
        errors: [],
        totalUpdated: 0
    };

    try {
        if (!version) {
            throw new Error('Version argument is required');
        }

        if (!version.match(/^\d+\.\d+\.\d+(?:-[\w.-]+)?$/)) {
            throw new Error('Invalid version format. Expected format: x.y.z or x.y.z-beta.n');
        }

        const files = await findSinceTodoFiles(pattern);
        
        // Debug logging
        console.log(chalk.blue('\nProcessing files:'));
        console.log(files);

        for (const file of files) {
            try {
                const { updated, count } = updateSinceTags(file, version, dryRun);
                if (updated) {
                    console.log(chalk.gray(`${dryRun ? 'Will update' : 'File updated'}: ${file} (${count} update${count === 1 ? '' : 's'})`));
                    results.updated.push({ file, count });
                    results.totalUpdated += count;
                }
            } catch (error) {
                console.error(chalk.red(`Error ${dryRun ? 'checking' : 'updating'} ${file}:`, error.message));
                results.errors.push({ file, error: error.message });
            }
        }

        return results;
    } catch (error) {
        throw new Error(`Error ${dryRun ? 'checking' : 'updating'} version placeholders: ${error.message}`);
    }
}

/**
 * Generate a summary for release notes
 */
function generateReleaseNotesSummary(results, isDryRun = false) {
    if (results.totalUpdated === 0) {
        return '';
    }

    // Only generate one section based on whether this is a dry run or not
    let summary = isDryRun ? 
        '### 🔄 Pending `@since` Tag / Deprecation Placeholder Updates\n\n' :
        '### `@since` Tag / Deprecation Placeholder Updates\n\n';

    summary += isDryRun ?
        `The following ${results.totalUpdated} version placeholder${results.totalUpdated === 1 ? '' : 's'} will be updated during release:\n\n` :
        `Updated ${results.totalUpdated} version placeholder${results.totalUpdated === 1 ? '' : 's'} in the following files:\n\n`;

    // Debug logging
    console.log(chalk.blue('\nGenerating summary for files:'));
    console.log(results.updated);

    results.updated.forEach(({ file, count }) => {
        // Get the relative path from the project root
        const relativePath = path.relative(process.cwd(), file);
        console.log(chalk.gray(`Processing file: ${file}`));
        console.log(chalk.gray(`Relative path: ${relativePath}`));
        summary += `* \`${relativePath}\` (${count} update${count === 1 ? '' : 's'})\n`;
    });

    if (results.errors.length > 0) {
        summary += '\n#### Errors\n\n';
        results.errors.forEach(({ file, error }) => {
            const relativePath = path.relative(process.cwd(), file);
            summary += `* Failed to ${isDryRun ? 'check' : 'update'} \`${relativePath}\`: ${error}\n`;
        });
    }

    // Debug logging
    console.log(chalk.blue('\nGenerated summary:'));
    console.log(summary);

    return summary;
}

/**
 * CLI command to update @since and deprecated version placeholder tags
 */
async function main() {
    try {
        const version = process.argv[2];
        const dryRun = process.argv.includes('--dry-run');

        if (!version) {
            throw new Error('Version argument is required');
        }

        console.log(chalk.blue(`\n${dryRun ? 'Checking' : 'Updating'} @since and deprecated version placeholders...`));
        const results = await updateAllSinceTags(version, '**/*.php', dryRun);

        if (results.updated.length > 0) {
            console.log(chalk.green(`\n✓ ${dryRun ? 'Files to update' : 'Updated files'}:`));
            results.updated.forEach(({ file, count }) => {
                console.log(chalk.gray(`  - ${path.relative(process.cwd(), file)} (${count} update${count === 1 ? '' : 's'})`));
            });
            console.log(chalk.green(`\nTotal placeholders ${dryRun ? 'to update' : 'updated'}: ${results.totalUpdated}`));
        } else {
            console.log(chalk.yellow('\nNo @since or deprecated version placeholder tags found'));
        }

        if (results.errors.length > 0) {
            console.log(chalk.red('\n❌ Errors:'));
            results.errors.forEach(({ file, error }) => {
                console.log(chalk.gray(`  - ${path.relative(process.cwd(), file)}: ${error}`));
            });
            process.exit(1);
        }

        // Generate release notes summary
        const summary = generateReleaseNotesSummary(results, dryRun);
        if (summary) {
            // Save summary to a temporary file for the workflow to use
            const summaryPath = '/tmp/since-tags-summary.md';
            fs.writeFileSync(summaryPath, summary);
            console.log(chalk.blue('\nSummary saved to:', summaryPath));
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

// Export functions for testing and reuse
module.exports = {
    findSinceTodoFiles,
    getSincePlaceholders,
    updateSinceTags,
    updateAllSinceTags,
    generateReleaseNotesSummary
};