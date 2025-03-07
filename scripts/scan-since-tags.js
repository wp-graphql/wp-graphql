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
        const files = await glob(pattern);

        // Log the files found
        // console.log('Files found:', files);

        // Ensure files is an array
        if (!Array.isArray(files)) {
            // console.error('Expected files to be an array, but got:', files);
            return []; // Return an empty array if files is not iterable
        }

        const results = [];

        for (const file of files) {
            const count = scanFileForSinceTags(file);
            if (count > 0) {
                results.push({ file, count });
            }
        }

        return results;
    } catch (error) {
        console.error('Error finding files:', error);
        return []; // Return an empty array on error
    }
}

/**
 * Generate metadata about files containing @since tags
 */
async function generateSinceTagsMetadata() {
    try {
        const files = await findFilesWithSinceTags();
        const metadata = {
            sinceFiles: files.map(({ file }) => file),
            totalTags: files.reduce((sum, { count }) => sum + count, 0)
        };

        if (files.length > 0) {
            console.log('\nFound files with @since tags to update:');
            files.forEach(({ file, count }) => {
                console.log(`- ${file} (${count} tags)`);
            });
        } else {
            console.log('\nNo files found with @since tags to update');
        }

        console.log(`Total tags to update: ${metadata.totalTags}\n`);

        return metadata;
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