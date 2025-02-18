const fs = require('fs');
const { glob } = require('glob');

/**
 * Scan file for @since todo tags
 */
function scanFileForSinceTags(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const regex = /@since\s+todo/g;
        const matches = content.match(regex);
        return matches ? matches.length : 0;
    } catch (error) {
        console.error(`Error scanning ${filePath}:`, error.message);
        return 0;
    }
}

/**
 * Find all files with @since todo tags
 */
async function findFilesWithSinceTags(pattern = 'src/**/*.php') {
    const files = await glob(pattern, { ignore: 'node_modules/**' });
    const results = [];

    for (const file of files) {
        const count = scanFileForSinceTags(file);
        if (count > 0) {
            results.push({ file, count });
        }
    }

    return results;
}

/**
 * Generate changeset metadata for @since todo files
 */
async function generateSinceTagsMetadata() {
    const files = await findFilesWithSinceTags();
    return {
        sinceFiles: files.map(({ file }) => file),
        totalTags: files.reduce((sum, { count }) => sum + count, 0)
    };
}

module.exports = {
    scanFileForSinceTags,
    findFilesWithSinceTags,
    generateSinceTagsMetadata
};