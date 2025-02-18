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
 * Formats changelog entries for WordPress.org readme.txt
 */
async function getReadmeTxtReleaseLine(changeset, type, options) {
    const [firstLine] = changeset.summary.split('\n');
    let links;
    try {
        if (process.env.GITHUB_TOKEN) {
            const info = await getInfo({
                repo: options.repo,
                commit: changeset.commit
            });
            links = info.links;
        }
    } catch (error) {
        console.warn('Warning: Could not fetch GitHub info. PR links will not be included.');
    }

    // Format: = [version] =\n\n* Change description ([PR #123](link))
    return `* ${firstLine}${links ? ` (${links.pull})` : ''}`;
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

        if (changeset.summary.startsWith('feat')) {
            features.push(line);
        } else if (changeset.summary.startsWith('fix')) {
            bugfixes.push(line);
        } else {
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

module.exports = {
    getReadmeTxtChangelog,
    getDependencyReleaseLine,
    updateStableTag // Export for testing
};