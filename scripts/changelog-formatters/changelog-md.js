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

        if (changeset.breaking || changeset.breaking_changes || hasBreakingContent) {
            groups.breaking.push(changeset);
            return;
        }

        if (changeset.summary && changeset.summary.startsWith('feat:')) {
            groups.features.push(changeset);
        } else if (changeset.summary && changeset.summary.startsWith('fix:')) {
            groups.fixes.push(changeset);
        } else if (changeset.summary && changeset.summary.startsWith('docs:')) {
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
    const changeContent = contentMatch ? contentMatch[1] : '';

    return {
        ...metadata,
        content: changeContent
    };
}

module.exports = {
    formatChangelogMd,
    groupChanges, // Export for testing
    formatPRLink, // Export for testing
    formatChangeEntry, // Export for testing
    updateChangelogMd
};