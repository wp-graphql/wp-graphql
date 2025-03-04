const fs = require('fs');
const { glob } = require('glob');

/**
 * Scan file for @since todo tags
 */
function scanFileForSinceTags(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const regex = /@since\s+(todo|next-version|tbd)|@next-version/g;
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
    try {
        // Use glob.sync for synchronous operation
        const files = await glob(pattern);
        const results = [];

        // Ensure files is treated as an array
        const fileArray = Array.isArray(files) ? files : [files];

        for (const file of fileArray) {
            const count = scanFileForSinceTags(file);
            if (count > 0) {
                results.push({ file, count });
            }
        }

        return results;
    } catch (error) {
        console.error('Error finding files:', error);
        return [];
    }
}

/**
 * Generate metadata about files containing @since tags
 */
async function generateSinceTagsMetadata() {
    try {
        const files = await findFilesWithSinceTags();
        return {
            sinceFiles: files.map(({ file }) => file),
            totalTags: files.reduce((sum, { count }) => sum + count, 0)
        };
    } catch (error) {
        console.error('Error generating metadata:', error);
        return {
            sinceFiles: [],
            totalTags: 0
        };
    }
}

module.exports = {
    scanFileForSinceTags,
    findFilesWithSinceTags,
    generateSinceTagsMetadata
};