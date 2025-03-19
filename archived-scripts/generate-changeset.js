const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const { generateSinceTagsMetadata } = require('./scan-since-tags');
const fetch = require('node-fetch');

// Define allowed types
const ALLOWED_TYPES = ['feat', 'fix', 'chore', 'docs', 'perf', 'refactor', 'revert', 'style', 'test', 'ci', 'build'];

/**
 * Parse PR title for type and breaking changes
 */
function parseTitle(title) {
    // Match: type(scope)!: description or type!: description
    const typeMatch = title.match(/^(feat|fix|build|chore|ci|docs|perf|refactor|revert|style|test)(?:\([^)]+\))?(!)?:/);
    if (!typeMatch) {
        throw new Error(`PR title does not follow conventional commit format. Must start with type: ${ALLOWED_TYPES.join(', ')}`);
    }

    const type = typeMatch[1];
    const isBreaking = Boolean(typeMatch[2] || title.includes('BREAKING CHANGE'));

    if (!ALLOWED_TYPES.includes(type)) {
        throw new Error(`Invalid type "${type}". Must be one of: ${ALLOWED_TYPES.join(', ')}`);
    }

    return {
        type,
        isBreaking
    };
}

/**
 * Format the summary with type prefix
 */
function formatSummary(type, isBreaking, description) {
    return `${type}${isBreaking ? '!' : ''}: ${description.trim()}`;
}

/**
 * Extract sections from PR body
 */
function parsePRBody(body) {
    const sections = {
        description: body.match(/What does this implement\/fix\? Explain your changes\.\s*-+\s*([\s\S]*?)(?=##|$)/)?.[1]?.trim() || '',
        breaking: body.match(/#{2,3}\s*Breaking Changes\s*([\s\S]*?)(?=##|$)/)?.[1]?.trim() || '',
        upgrade: body.match(/#{2,3}\s*Upgrade Instructions\s*([\s\S]*?)(?=##|$)/)?.[1]?.trim() || ''
    };

    // Clean up any "N/A" or similar placeholders
    Object.keys(sections).forEach(key => {
        if (sections[key].toLowerCase() === 'n/a' || sections[key].toLowerCase() === 'none') {
            sections[key] = '';
        }
    });

    // Check if breaking changes section is just a template placeholder
    if (sections.breaking) {
        // Remove HTML comments
        const withoutComments = sections.breaking.replace(/<!--[\s\S]*?-->/g, '').trim();

        // Check if it's empty or contains only template text
        if (!withoutComments ||
            withoutComments.includes('Does this PR introduce breaking changes?') ||
            withoutComments.includes('If there are no breaking changes, delete this comment')) {
            sections.breaking = '';
        }
    }

    // Check if upgrade instructions section is just a template placeholder
    if (sections.upgrade) {
        // Remove HTML comments
        const withoutComments = sections.upgrade.replace(/<!--[\s\S]*?-->/g, '').trim();

        // Check if it's empty or contains only template text
        if (!withoutComments ||
            withoutComments.includes('If you indicated breaking changes above') ||
            withoutComments.includes('If there are no breaking changes, you can delete this comment')) {
            sections.upgrade = '';
        }
    }

    return sections;
}

/**
 * Check if a GitHub user is a first-time contributor
 *
 * @param {string} username GitHub username to check
 * @param {number} prNumber PR number
 * @returns {Promise<boolean>} True if this is their first contribution
 */
async function isNewContributor(username, prNumber) {
    try {
        // Skip check if no username or PR number
        if (!username || !prNumber) {
            return false;
        }

        // Use GitHub API to search for PRs by this author that were merged before this one
        const token = process.env.GITHUB_TOKEN;
        const headers = token ? { 'Authorization': `token ${token}` } : {};

        const searchUrl = `https://api.github.com/search/issues?q=repo:wp-graphql/wp-graphql+type:pr+author:${username}+is:merged+-is:draft+number:<${prNumber}`;

        const response = await fetch(searchUrl, { headers });
        const data = await response.json();

        // If total_count is 0, this is their first contribution
        return data.total_count === 0;
    } catch (error) {
        console.error('Error checking for new contributor:', error);
        return false;
    }
}

/**
 * Extract GitHub username from PR data
 *
 * @param {Object} prData PR data from GitHub API
 * @returns {string|null} GitHub username or null if not found
 */
function extractGitHubUsername(prData) {
    try {
        // Try to extract from PR body first (more reliable)
        if (prData.user && prData.user.login) {
            return prData.user.login;
        }

        // Fallback: try to extract from PR URL if it's in the format
        if (prData.html_url) {
            const match = prData.html_url.match(/github\.com\/([^/]+)/);
            if (match && match[1]) {
                return match[1];
            }
        }

        return null;
    } catch (error) {
        console.error('Error extracting GitHub username:', error);
        return null;
    }
}

/**
 * Get PR data from GitHub API
 *
 * @param {number} prNumber PR number
 * @returns {Promise<Object>} PR data from GitHub API
 */
async function getPRData(prNumber) {
    try {
        if (!prNumber) {
            return null;
        }

        const token = process.env.GITHUB_TOKEN;
        const headers = token ? { 'Authorization': `token ${token}` } : {};

        const url = `https://api.github.com/repos/wp-graphql/wp-graphql/pulls/${prNumber}`;
        const response = await fetch(url, { headers });

        if (!response.ok) {
            throw new Error(`Failed to fetch PR data: ${response.statusText}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Error fetching PR data:', error);
        return null;
    }
}

/**
 * Create changeset using @changesets/cli
 */
async function createChangeset({ title, body, prNumber }) {
    // Get PR data if we have a PR number
    let prData = null;
    let username = null;
    let isFirstTimeContributor = false;

    if (prNumber) {
        prData = await getPRData(prNumber);
        if (prData) {
            username = extractGitHubUsername(prData);
            if (username) {
                isFirstTimeContributor = await isNewContributor(username, prNumber);
            }
        }
    }

    // Parse PR title and body
    const { type, isBreaking } = parseTitle(title);
    const sections = parsePRBody(body);

    // Validate breaking changes have upgrade instructions
    const hasBreakingChanges = isBreaking || (sections.breaking && sections.breaking.length > 0);
    if (hasBreakingChanges) {
        if (isBreaking && !sections.breaking) {
            throw new Error('Breaking changes indicated in PR title must be documented in the PR description');
        }
        if (sections.breaking && !sections.upgrade) {
            throw new Error('Breaking changes must include upgrade instructions');
        }
    }

    // Get description from PR body or fallback to title
    const description = sections.description || title.split(':')[1].trim();

    // Format the summary
    const summary = formatSummary(type, isBreaking, description);

    // Determine bump type
    const bumpType = hasBreakingChanges
        ? 'major'  // Breaking changes are major
        : (type === 'feat'
            ? 'minor'  // New features are minor
            : 'patch'  // Everything else is patch
        );

    // Get @since tags metadata
    const sinceMetadata = await generateSinceTagsMetadata();

    // Create a unique ID for the changeset
    const changesetId = `pr-${prNumber}-${Date.now()}`;
    const changesetDir = path.join(process.cwd(), '.changeset');
    const changesetPath = path.join(changesetDir, `${changesetId}.md`);

    // Get package name from package.json
    const packageName = getPackageName();

    // Ensure .changeset directory exists
    if (!fs.existsSync(changesetDir)) {
        fs.mkdirSync(changesetDir, { recursive: true });
    }

    // Create the changeset file content with YAML frontmatter (not JSON)
    let fileContent = '---\n';
    fileContent += `"${packageName}": ${bumpType}\n`;
    fileContent += '---\n\n';
    fileContent += `<!-- pr: ${prNumber} -->\n`;
    fileContent += `<!-- breaking: ${hasBreakingChanges ? 'true' : 'false'} -->\n`;
    fileContent += `<!-- contributorUsername: "${username || ''}" -->\n`;
    fileContent += `<!-- newContributor: ${isFirstTimeContributor} -->\n\n`;
    fileContent += `### ${summary}\n\n`;
    fileContent += `[PR #${prNumber}](https://github.com/wp-graphql/wp-graphql/pull/${prNumber})\n\n`;

    if (sections.description) {
        fileContent += `#### Description\n${sections.description}\n\n`;
    }

    if (sections.breaking) {
        fileContent += `#### Breaking Changes\n${sections.breaking}\n\n`;
    }

    if (sections.upgrade) {
        fileContent += `#### Upgrade Instructions\n${sections.upgrade}\n\n`;
    }

    if (sinceMetadata.sinceFiles.length > 0) {
        fileContent += `#### Files with @since next-version\n`;
        sinceMetadata.sinceFiles.forEach(file => {
            fileContent += `- ${file}\n`;
        });
    }

    // Write the changeset file
    fs.writeFileSync(changesetPath, fileContent);

    return {
        type: bumpType,
        breaking: isBreaking,
        pr: Number(prNumber),
        sinceFiles: sinceMetadata.sinceFiles,
        totalSinceTags: sinceMetadata.totalTags,
        changesetId
    };
}

/**
 * Create a changeset file
 */
function createChangesetFile(changeset) {
    const packageName = getPackageName();
    const changesetId = `pr-${changeset.pr}-${Date.now()}`;
    const changesetPath = path.join(process.cwd(), '.changeset', `${changesetId}.md`);

    // Create the .changeset directory if it doesn't exist
    if (!fs.existsSync(path.join(process.cwd(), '.changeset'))) {
        fs.mkdirSync(path.join(process.cwd(), '.changeset'));
    }

    // Create the frontmatter in the format expected by @changesets/cli
    // This should be a mapping of package names to bump types
    const frontmatter = `---
"${packageName}": ${changeset.type}
---

<!-- pr: ${changeset.pr} -->
<!-- breaking: ${changeset.breaking} -->
<!-- contributorUsername: "${changeset.contributorUsername || ''}" -->
<!-- newContributor: ${changeset.newContributor || false} -->

${changeset.content}`;

    fs.writeFileSync(changesetPath, frontmatter);
    return changesetPath;
}

/**
 * Get the package name from package.json
 */
function getPackageName() {
    try {
        const packageJsonPath = path.join(process.cwd(), 'package.json');
        const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
        return packageJson.name || 'wp-graphql'; // Default to wp-graphql if name is not found
    } catch (error) {
        console.error('Error reading package.json:', error);
        return 'wp-graphql'; // Default fallback
    }
}

// When run directly from command line
if (require.main === module) {
    const title = process.env.PR_TITLE;
    const body = process.env.PR_BODY;
    const prNumber = process.env.PR_NUMBER;

    if (!title || !body || !prNumber) {
        console.error('Missing required environment variables');
        process.exit(1);
    }

    createChangeset({ title, body, prNumber })
        .then(result => {
            console.log('Changeset created successfully:', result);
        })
        .catch(error => {
            console.error('Error creating changeset:', error);
            process.exit(1);
        });
}

module.exports = {
    parseTitle,
    parsePRBody,
    createChangeset,
    formatSummary,
    ALLOWED_TYPES
};