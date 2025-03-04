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
            if (changeset.breakingChanges) {
                notice += changeset.breakingChanges + '\n';
            }
            if (changeset.pr) {
                notice += `In <a href="${options.repo}/pull/${changeset.pr}">#${changeset.pr}</a>\n`;
            }
            if (changeset.upgradeInstructions) {
                notice += '\nUpgrade Instructions:\n' + changeset.upgradeInstructions + '\n';
            }
        }
    } else if (newVersion.match(/\d+\.\d+\.0$/)) {
        // Standard notice for minor versions
        notice += 'While there are no known breaking changes, as this is a minor version update, we recommend testing on staging servers before updating production environments.\n';
    }

    return notice;
}

/**
 * Updates readme.txt with changelog and upgrade notice
 */
async function updateReadmeTxt(release, options) {
    const readmePath = path.join(process.cwd(), 'readme.txt');
    let content = fs.readFileSync(readmePath, 'utf8');

    // Generate changelog
    const changelog = await getReadmeTxtChangelog(release, options);

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
 * Formats changelog entries for WordPress.org readme.txt
 */
async function getReadmeTxtReleaseLine(changeset, type, options) {
    if (!changeset || !changeset.summary) {
        return '';
    }

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

module.exports = {
    getReadmeTxtChangelog,
    getDependencyReleaseLine,
    updateStableTag,
    getUpgradeNoticeEntry,
    updateReadmeTxt
};