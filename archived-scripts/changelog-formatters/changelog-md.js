const path = require('path');
const fs = require('fs');

/**
 * Group changes by their type
 */
function groupChanges(changesets) {
    const groups = {
        breaking: [],
        features: [],
        fixes: [],
        docs: [],
        other: []
    };

    changesets.forEach(changeset => {
        // Check for breaking changes in multiple ways
        const hasBreakingContent = changeset.content && changeset.content.includes('#### Breaking Changes');

        if (changeset.breaking || hasBreakingContent) {
            groups.breaking.push(changeset);
            return;
        }

        // Check the summary for conventional commit prefixes
        const summary = changeset.summary || '';

        if (summary.startsWith('feat:')) {
            groups.features.push(changeset);
        } else if (
            summary.startsWith('fix:') ||
            summary.startsWith('chore:') ||
            summary.startsWith('ci:')
        ) {
            groups.fixes.push(changeset);
        } else if (summary.startsWith('docs:')) {
            groups.docs.push(changeset);
        } else {
            groups.other.push(changeset);
        }
    });

    return groups;
}

/**
 * Format a PR link
 */
function formatPRLink(pr_number) {
    return `[#${pr_number}](https://github.com/wp-graphql/wp-graphql/pull/${pr_number})`;
}

/**
 * Format a single change entry
 */
function formatChangeEntry(changeset) {
    // Keep the prefix (feat:, fix:, etc.) in the summary
    const summary = changeset.summary || '';

    // Format the PR link if PR number is available
    const prLink = changeset.pr ? formatPRLink(changeset.pr) : '';

    // Put the PR link before the description
    return `- ${prLink}: ${summary}`;
}

/**
 * Format breaking changes section
 */
function formatBreakingChanges(changesets) {
    if (!changesets.length) return '';

    let content = '### ⚠ BREAKING CHANGES\n\n';

    changesets.forEach(changeset => {
        // Format the PR link if PR number is available
        const prLink = changeset.pr ? formatPRLink(changeset.pr) : '';

        // Include the PR link and summary at the top of each breaking change
        if (prLink && changeset.summary) {
            content += `- ${prLink}: ${changeset.summary}\n\n`;
        }

        // Extract breaking changes from content if not directly available
        let breakingChanges = '';
        if (changeset.content) {
            const match = changeset.content.match(/#### Breaking Changes\n([\s\S]*?)(?=\n####|$)/);
            if (match) {
                breakingChanges = match[1].trim();
            }
        } else if (changeset.breaking_changes) {
            breakingChanges = changeset.breaking_changes;
        }

        if (breakingChanges) {
            content += `  ${breakingChanges}\n\n`;
        }

        // Extract upgrade instructions if available
        let upgradeInstructions = '';
        if (changeset.content) {
            const match = changeset.content.match(/#### Upgrade Instructions\n([\s\S]*?)(?=\n####|$)/);
            if (match) {
                upgradeInstructions = match[1].trim();
                content += '  #### Upgrade Instructions:\n';
                content += `  ${upgradeInstructions}\n\n`;
            }
        } else if (changeset.upgrade_instructions) {
            content += '  #### Upgrade Instructions:\n';
            content += `  ${changeset.upgrade_instructions}\n\n`;
        }
    });

    return content;
}

/**
 * Format the complete changelog for CHANGELOG.md
 */
function formatChangelogMd(version, changesets) {
    const groups = groupChanges(changesets);
    let content = `## [${version}]\n\n`;

    // Add breaking changes first if any
    if (groups.breaking.length) {
        content += formatBreakingChanges(groups.breaking);
    }

    // Add other changes grouped by type
    if (groups.features.length) {
        content += `### New Features\n\n${groups.features.map(formatChangeEntry).join('\n')}\n\n`;
    }

    if (groups.fixes.length) {
        content += `### Chores / Bugfixes\n\n${groups.fixes.map(formatChangeEntry).join('\n')}\n\n`;
    }

    if (groups.docs.length) {
        content += `### Documentation\n\n${groups.docs.map(formatChangeEntry).join('\n')}\n\n`;
    }

    if (groups.other.length) {
        content += `### Other Changes\n\n${groups.other.map(formatChangeEntry).join('\n')}\n\n`;
    }

    return content;
}

/**
 * Update CHANGELOG.md with new release
 */
async function updateChangelogMd(release, options) {
    const changelogPath = path.join(process.cwd(), 'CHANGELOG.md');
    let content = fs.existsSync(changelogPath) ? fs.readFileSync(changelogPath, 'utf8') : '# Changelog\n\n';

    const newContent = formatChangelogMd(release.newVersion, release.changesets);

    // Insert after the first line (title)
    const lines = content.split('\n');
    lines.splice(2, 0, newContent);
    content = lines.join('\n');

    fs.writeFileSync(changelogPath, content);
}

function parseChangeset(content) {
    // Extract frontmatter between --- markers
    const frontmatterMatch = content.match(/---\n([\s\S]*?)\n---/);
    if (!frontmatterMatch) return null;

    const frontmatter = frontmatterMatch[1];

    // Parse YAML frontmatter
    const metadata = {};
    frontmatter.split('\n').forEach(line => {
        const [key, value] = line.split(':').map(part => part.trim());
        if (key && value) {
            // Convert boolean strings to actual booleans
            if (value === 'true') metadata[key] = true;
            else if (value === 'false') metadata[key] = false;
            else if (!isNaN(value)) metadata[key] = Number(value);
            else metadata[key] = value;
        }
    });

    // Extract content after frontmatter
    const contentMatch = content.match(/---\n[\s\S]*?\n---\n\n([\s\S]*)/);
    let changeContent = contentMatch ? contentMatch[1] : '';

    // Extract metadata from HTML comments
    const prMatch = changeContent.match(/<!--\s*pr:\s*(\d+)\s*-->/);
    if (prMatch) metadata.pr = parseInt(prMatch[1], 10);

    const breakingMatch = changeContent.match(/<!--\s*breaking:\s*(true|false)\s*-->/);
    if (breakingMatch) metadata.breaking = breakingMatch[1] === 'true';

    const usernameMatch = changeContent.match(/<!--\s*contributorUsername:\s*"([^"]*)"\s*-->/);
    if (usernameMatch) metadata.contributorUsername = usernameMatch[1];

    const newContribMatch = changeContent.match(/<!--\s*newContributor:\s*(true|false)\s*-->/);
    if (newContribMatch) metadata.newContributor = newContribMatch[1] === 'true';

    // Remove HTML comments from the content
    changeContent = changeContent.replace(/<!--\s*pr:\s*\d+\s*-->\n?/g, '')
                                .replace(/<!--\s*breaking:\s*(?:true|false)\s*-->\n?/g, '')
                                .replace(/<!--\s*contributorUsername:\s*".*"\s*-->\n?/g, '')
                                .replace(/<!--\s*newContributor:\s*(?:true|false)\s*-->\n?/g, '')
                                .trim();

    // Extract summary from the content (first line or first heading)
    let summary = '';
    const summaryMatch = changeContent.match(/^###?\s+(.+)$/m) || changeContent.match(/^(.+)$/m);
    if (summaryMatch) {
        summary = summaryMatch[1].trim();
    }

    // Check if summary starts with a conventional commit type
    const typeMatch = summary.match(/^(feat|fix|chore|docs|perf|refactor|revert|style|test|ci|build)(\!)?:/);
    if (typeMatch) {
        metadata.type = typeMatch[1];
        if (typeMatch[2] === '!') {
            metadata.breaking = true;
        }
    }

    return {
        ...metadata,
        summary: summary,
        content: changeContent
    };
}

const formatChangelog = (changesets, options = {}) => {
    const { version, previousVersion } = options;
    const groups = groupChanges(changesets);

    let changelog = '';

    // Add Breaking Changes section if there are any
    if (groups.breaking.length > 0) {
        changelog += '### Breaking Changes\n\n';
        groups.breaking.forEach((changeset) => {
            const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';
            changelog += `- ${prLink ? `${prLink}: ` : ''}${changeset.summary}\n`;

            // Add breaking changes details if available
            if (changeset.breakingChanges) {
                const breakingChangesLines = changeset.breakingChanges.split('\n');
                breakingChangesLines.forEach(line => {
                    if (line.trim()) {
                        changelog += `  ${line.trim()}\n`;
                    }
                });
            }

            // Add upgrade instructions if available
            if (changeset.upgradeInstructions) {
                changelog += `  **Upgrade Instructions**: ${changeset.upgradeInstructions}\n`;
            }
        });
        changelog += '\n';
    }

    // Add Features section if there are any
    if (groups.features.length > 0) {
        changelog += '### New Features\n\n';
        groups.features.forEach((changeset) => {
            const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';
            changelog += `- ${prLink ? `${prLink}: ` : ''}${changeset.summary}\n`;

            // Add description if available and not empty
            if (changeset.description && changeset.description.trim()) {
                const descriptionLines = changeset.description.split('\n');
                descriptionLines.forEach(line => {
                    if (line.trim()) {
                        changelog += `  ${line.trim()}\n`;
                    }
                });
            }
        });
        changelog += '\n';
    }

    // Add Fixes section if there are any
    if (groups.fixes.length > 0) {
        changelog += '### Chores / Bugfixes\n\n';
        groups.fixes.forEach((changeset) => {
            const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';
            changelog += `- ${prLink ? `${prLink}: ` : ''}${changeset.summary}\n`;

            // Add description if available and not empty
            if (changeset.description && changeset.description.trim()) {
                const descriptionLines = changeset.description.split('\n');
                descriptionLines.forEach(line => {
                    if (line.trim()) {
                        changelog += `  ${line.trim()}\n`;
                    }
                });
            }
        });
        changelog += '\n';
    }

    // Add Documentation section if there are any
    if (groups.docs.length > 0) {
        changelog += '### Documentation\n\n';
        groups.docs.forEach((changeset) => {
            const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';
            changelog += `- ${prLink ? `${prLink}: ` : ''}${changeset.summary}\n`;
        });
        changelog += '\n';
    }

    // Add Other section if there are any
    if (groups.other.length > 0) {
        changelog += '### Other Changes\n\n';
        groups.other.forEach((changeset) => {
            const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';
            changelog += `- ${prLink ? `${prLink}: ` : ''}${changeset.summary}\n`;
        });
        changelog += '\n';
    }

    // Process new contributors
    const newContributors = getNewContributors(changesets);
    if (newContributors.length > 0) {
        changelog += '### New Contributors\n\n';
        newContributors.forEach(contributor => {
            changelog += `- @${contributor.username} made their first contribution in [#${contributor.pr}](https://github.com/wp-graphql/wp-graphql/pull/${contributor.pr})\n`;
        });
        changelog += '\n';
    }

    return changelog.trim();
};

/**
 * Extract new contributors from changesets
 *
 * @param {Array} changesets Array of changeset objects
 * @returns {Array} Array of new contributor objects with username and PR number
 */
function getNewContributors(changesets) {
    const contributors = [];

    // Extract contributor information from changesets
    changesets.forEach(changeset => {
        if (changeset.pr && changeset.newContributor && changeset.contributorUsername) {
            contributors.push({
                username: changeset.contributorUsername,
                pr: changeset.pr
            });
        }
    });

    return contributors;
}

// Custom changelog formatter for WP-GraphQL
// This is used by changesets to format the changelog entries

/**
 * Format a changelog entry for markdown
 *
 * @param {Object} release The release object from changesets
 * @param {Object} options Formatting options
 * @returns {string} Formatted changelog entry
 */
function getReleaseLine(release, options = {}) {
  const { commit, summary } = release;
  const { repo } = options;

  return `\n\n${summary ? summary : ""}\n\n`;
}

module.exports = {
    formatChangelogMd,
    groupChanges, // Export for testing
    formatPRLink, // Export for testing
    formatChangeEntry, // Export for testing
    updateChangelogMd,
    getNewContributors, // Add this export for the tests
    formatChangelog, // Add this export for the tests
    parseChangeset, // Export the parseChangeset function
    getReleaseLine,
};