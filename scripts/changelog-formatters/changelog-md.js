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
        if (changeset.breaking || changeset.breaking_changes) {
            groups.breaking.push(changeset);
            return;
        }

        if (changeset.summary.startsWith('feat:')) {
            groups.features.push(changeset);
        } else if (changeset.summary.startsWith('fix:')) {
            groups.fixes.push(changeset);
        } else if (changeset.summary.startsWith('docs:')) {
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
    const summary = changeset.summary.split(':')[1].trim();
    return `- ${summary} (${formatPRLink(changeset.pr_number)})`;
}

/**
 * Format breaking changes section
 */
function formatBreakingChanges(changesets) {
    if (!changesets.length) return '';

    let content = '### ⚠ BREAKING CHANGES\n\n';

    changesets.forEach(changeset => {
        content += `${changeset.breaking_changes}\n\n`;
        if (changeset.upgrade_instructions) {
            content += '#### Upgrade Instructions\n\n';
            content += `${changeset.upgrade_instructions}\n\n`;
        }
        content += `(${formatPRLink(changeset.pr_number)})\n\n`;
    });

    return content;
}

/**
 * Format changes section
 */
function formatChangesSection(title, changes) {
    if (!changes.length) return '';

    return `### ${title}\n\n${changes.map(formatChangeEntry).join('\n')}\n\n`;
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
    content += formatChangesSection('Features', groups.features);
    content += formatChangesSection('Bug Fixes', groups.fixes);
    content += formatChangesSection('Documentation', groups.docs);

    if (groups.other.length) {
        content += formatChangesSection('Other Changes', groups.other);
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

module.exports = {
    formatChangelogMd,
    groupChanges, // Export for testing
    formatPRLink, // Export for testing
    formatChangeEntry, // Export for testing
    updateChangelogMd
};