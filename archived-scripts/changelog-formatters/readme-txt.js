const { getInfo } = require('@changesets/get-github-info');
const fs = require('fs');
const path = require('path');

/**
 * Updates the stable tag in readme.txt for stable releases
 */
function updateStableTag(version) {
    const readmePath = path.join(process.cwd(), 'readme.txt');
    let content = fs.readFileSync(readmePath, 'utf8');

    // Only update stable tag for stable releases (no pre-release suffix)
    if (!version.includes('-')) {
        content = content.replace(
            /Stable tag: .+/,
            `Stable tag: ${version}`
        );
        fs.writeFileSync(readmePath, content);
    }
}

/**
 * Generate upgrade notice entry for readme.txt
 */
async function getUpgradeNoticeEntry(release, options) {
    const { newVersion, changesets } = release;

    // Skip upgrade notices for patch versions without breaking changes
    if (newVersion.match(/\d+\.\d+\.\d+$/) && !changesets.some(c => c.breaking)) {
        // Only skip if it's not a minor version (x.y.0)
        if (!newVersion.endsWith('.0')) {
            return '';
        }
    }

    let notice = `= ${newVersion} =\n`;

    // Check for breaking changes
    const breakingChanges = changesets.filter(c => c.breaking);
    if (breakingChanges.length > 0) {
        notice += '**BREAKING CHANGE UPDATE**\n\n';
        for (const changeset of breakingChanges) {
            // Include PR link and summary
            if (changeset.pr && changeset.summary) {
                notice += `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr}): ${changeset.summary}\n\n`;
            }

            if (changeset.breaking_changes) {
                notice += changeset.breaking_changes + '\n\n';
            }

            if (changeset.upgrade_instructions) {
                notice += changeset.upgrade_instructions + '\n\n';
            }
        }
    } else if (newVersion.match(/\d+\.\d+\.0$/)) {
        // Standard notice for minor versions
        notice += 'While there are no known breaking changes in this release, as this is a minor version update, we recommend testing on staging servers before updating production environments.\n';
    }

    return notice.trim() + '\n';
}

/**
 * Updates readme.txt with changelog and upgrade notice
 */
async function updateReadmeTxt(release, options) {
    const readmePath = path.join(process.cwd(), 'readme.txt');
    let content = fs.readFileSync(readmePath, 'utf8');

    // Generate changelog
    const changelog = formatReadmeTxt(release.newVersion, release.changesets);

    // Update changelog section
    content = content.replace(
        /(== Changelog ==\n\n)/,
        `$1${changelog}`
    );

    // Generate upgrade notice if needed
    const upgradeNotice = await getUpgradeNoticeEntry(release, options);
    if (upgradeNotice) {
        if (content.includes('== Upgrade Notice ==')) {
            content = content.replace(
                /(== Upgrade Notice ==\n\n)/,
                `$1${upgradeNotice}\n\n`
            );
        } else {
            content += `\n\n== Upgrade Notice ==\n\n${upgradeNotice}\n`;
        }
    }

    // Update stable tag if needed
    if (!release.newVersion.includes('-')) {
        updateStableTag(release.newVersion);
    }

    fs.writeFileSync(readmePath, content);
}

/**
 * Get a single line for the readme.txt changelog
 */
async function getReadmeTxtReleaseLine(changeset, releaseType, options) {
    // Skip if no summary
    if (!changeset || !changeset.summary) {
        return '';
    }

    // Format the PR link
    const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';

    // Format the line with the PR link before the full summary
    return `* ${prLink}: ${changeset.summary}`;
}

/**
 * Groups changes by type (Features/Bugfixes)
 */
function getDependencyReleaseLine() {
    return '';
}

async function getReadmeTxtChangelog(release, options) {
    const heading = `= ${release.newVersion} =\n`;

    // Update stable tag for stable releases
    updateStableTag(release.newVersion);

    const features = [];
    const bugfixes = [];
    const other = [];

    for (const changeset of release.changesets) {
        const line = await getReadmeTxtReleaseLine(changeset, 'patch', options);

        // Skip empty lines
        if (!line) continue;

        // Only try to categorize if summary exists
        if (changeset && changeset.summary) {
            if (changeset.summary.startsWith('feat')) {
                features.push(line);
            } else if (changeset.summary.startsWith('fix')) {
                bugfixes.push(line);
            } else {
                other.push(line);
            }
        } else {
            // If no summary, add to other changes
            other.push(line);
        }
    }

    const sections = [];

    if (features.length) {
        sections.push('**New Features**\n\n' + features.join('\n'));
    }

    if (bugfixes.length) {
        sections.push('**Chores / Bugfixes**\n\n' + bugfixes.join('\n'));
    }

    if (other.length) {
        sections.push('**Other Changes**\n\n' + other.join('\n'));
    }

    return heading + '\n' + sections.join('\n\n') + '\n\n';
}

/**
 * Group changes by their type for readme.txt
 */
function groupChanges(changesets) {
    const groups = {
        breaking: [],
        features: [],
        fixes: [],
        other: []
    };

    changesets.forEach(changeset => {
        // Check for breaking changes in multiple ways
        const hasBreakingContent = changeset.content && changeset.content.includes('#### Breaking Changes');

        if (changeset.breaking || hasBreakingContent) {
            groups.breaking.push(changeset);
            return;
        }

        // Skip documentation changes in readme.txt
        if (changeset.summary && changeset.summary.startsWith('docs:')) {
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
        } else {
            groups.other.push(changeset);
        }
    });

    return groups;
}

/**
 * Format a single change entry for readme.txt
 */
function formatChangeEntry(changeset) {
    // Get the full summary including prefix
    const summary = changeset.summary || '';

    // Format PR link using markdown format (same as CHANGELOG.md)
    let prLink = '';
    if (changeset.pr) {
        prLink = `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})`;
    }

    // Put the PR link before the description
    return `* ${prLink ? prLink + ': ' : ''}${summary}`;
}

/**
 * Format breaking changes section for readme.txt
 */
function formatBreakingChanges(changesets) {
    if (!changesets.length) return '';

    let content = '**BREAKING CHANGES**\n\n';

    changesets.forEach(changeset => {
        // Format PR link using markdown format
        const prLink = changeset.pr ? `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})` : '';

        // Include the PR link and summary at the top of each breaking change
        if (prLink && changeset.summary) {
            content += `* ${prLink}: ${changeset.summary}\n\n`;
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
                content += '  **Upgrade Instructions:**\n';
                content += `  ${upgradeInstructions}\n\n`;
            }
        } else if (changeset.upgrade_instructions) {
            content += '  **Upgrade Instructions:**\n';
            content += `  ${changeset.upgrade_instructions}\n\n`;
        }
    });

    return content;
}

/**
 * Format changes section for readme.txt
 */
function formatChangesSection(title, changes) {
    if (!changes.length) return '';

    return `**${title}**\n\n${changes.map(formatChangeEntry).join('\n')}\n\n`;
}

/**
 * Format upgrade notice for readme.txt
 */
function formatUpgradeNotice(version, changesets) {
    const breakingChanges = changesets.filter(c => c.breaking || c.breaking_changes);

    if (!breakingChanges.length) {
        // For minor versions, add a standard notice
        if (version.match(/\d+\.\d+\.0$/)) {
            return `= ${version} =\n` +
                   'While there are no breaking changes in this release, as this is a minor version update, ' +
                   'we recommend testing on staging servers before updating production environments.\n\n';
        }
        return '';
    }

    let notice = `= ${version} =\n**BREAKING CHANGE UPDATE**\n\n`;

    breakingChanges.forEach(changeset => {
        // Format PR link and include summary
        if (changeset.pr && changeset.summary) {
            const prLink = `[#${changeset.pr}](https://github.com/wp-graphql/wp-graphql/pull/${changeset.pr})`;
            notice += `${prLink}: ${changeset.summary}\n\n`;
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
            notice += `${breakingChanges}\n\n`;
        }

        // Extract upgrade instructions if available
        let upgradeInstructions = '';
        if (changeset.content) {
            const match = changeset.content.match(/#### Upgrade Instructions\n([\s\S]*?)(?=\n####|$)/);
            if (match) {
                upgradeInstructions = match[1].trim();
                notice += `${upgradeInstructions}\n\n`;
            }
        } else if (changeset.upgrade_instructions) {
            notice += `${changeset.upgrade_instructions}\n\n`;
        }
    });

    return notice;
}

/**
 * Format the complete changelog for readme.txt
 */
function formatReadmeTxt(version, changesets) {
    const groups = groupChanges(changesets);
    let content = `= ${version} =\n\n`;

    // Add breaking changes first if any
    if (groups.breaking.length) {
        content += formatBreakingChanges(groups.breaking);
    }

    // Add other changes grouped by type
    if (groups.features.length) {
        content += `**New Features**\n\n${groups.features.map(formatChangeEntry).join('\n')}\n\n`;
    }

    if (groups.fixes.length) {
        content += `**Chores / Bugfixes**\n\n${groups.fixes.map(formatChangeEntry).join('\n')}\n\n`;
    }

    if (groups.other.length) {
        content += `**Other Changes**\n\n${groups.other.map(formatChangeEntry).join('\n')}\n\n`;
    }

    return content;
}

// If the file uses a function to parse changesets, we need to update it
// to handle the new format. Look for functions that read changeset files
// and extract metadata from them.

// Example update (actual implementation will depend on the current code):
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

module.exports = {
    getReadmeTxtChangelog,
    getDependencyReleaseLine,
    updateStableTag,
    getUpgradeNoticeEntry,
    updateReadmeTxt,
    formatReadmeTxt,
    groupChanges,
    formatChangeEntry,
    formatUpgradeNotice,
    parseChangeset
};