/**
 * Functions for generating and formatting the CHANGELOG.md file
 */

/**
 * Get new contributors from changesets
 *
 * @param {Array} changesets Array of changeset objects
 * @returns {Array} Array of new contributor objects with username and PR number
 */
function getNewContributors(changesets) {
    if (!changesets || !Array.isArray(changesets)) {
        return [];
    }

    return changesets
        .filter(changeset =>
            changeset.newContributor === true &&
            changeset.contributorUsername &&
            changeset.contributorUsername.trim() !== ''
        )
        .map(changeset => ({
            username: changeset.contributorUsername,
            pr: changeset.pr
        }));
}

/**
 * Format changelog with new contributors section
 *
 * @param {Array} changesets Array of changeset objects
 * @param {Object} options Options for formatting
 * @param {string} options.version Version number
 * @returns {string} Formatted changelog
 */
function formatChangelog(changesets, options = {}) {
    const { version = 'next' } = options;

    // Start with version header
    let changelog = `## ${version}\n\n`;

    // Group changes by type
    const features = changesets.filter(c => c.type === 'feat');
    const fixes = changesets.filter(c => c.type === 'fix');
    const other = changesets.filter(c => !['feat', 'fix'].includes(c.type));

    // Add features
    if (features.length > 0) {
        changelog += `### Features\n\n`;
        features.forEach(feature => {
            changelog += `- ${feature.summary} ([#${feature.pr}](https://github.com/wp-graphql/wp-graphql/pull/${feature.pr}))\n`;
        });
        changelog += '\n';
    }

    // Add fixes
    if (fixes.length > 0) {
        changelog += `### Bug Fixes\n\n`;
        fixes.forEach(fix => {
            changelog += `- ${fix.summary} ([#${fix.pr}](https://github.com/wp-graphql/wp-graphql/pull/${fix.pr}))\n`;
        });
        changelog += '\n';
    }

    // Add other changes
    if (other.length > 0) {
        changelog += `### Other Changes\n\n`;
        other.forEach(change => {
            changelog += `- ${change.summary} ([#${change.pr}](https://github.com/wp-graphql/wp-graphql/pull/${change.pr}))\n`;
        });
        changelog += '\n';
    }

    // Add new contributors section if any
    const newContributors = getNewContributors(changesets);
    if (newContributors.length > 0) {
        changelog += `### New Contributors\n\n`;
        newContributors.forEach(contributor => {
            changelog += `- @${contributor.username} made their first contribution in [#${contributor.pr}](https://github.com/wp-graphql/wp-graphql/pull/${contributor.pr})\n`;
        });
        changelog += '\n';
    }

    return changelog;
}

module.exports = {
    getNewContributors,
    formatChangelog
};