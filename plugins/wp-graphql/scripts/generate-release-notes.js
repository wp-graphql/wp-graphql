#!/usr/bin/env node

/**
 * Script to generate release notes from changesets
 *
 * Usage:
 *   node scripts/generate-release-notes.js [--format=json|markdown] [--repo-url=https://github.com/org/repo] [--token=github_token]
 *
 * Options:
 *   --format     Output format (json or markdown, default: markdown)
 *   --repo-url   Repository URL to use for PR links (overrides package.json)
 *   --token      GitHub token for API requests (optional, helps avoid rate limits)
 *
 * Environment Variables:
 *   REPO_URL     Repository URL to use for PR links (can be used instead of --repo-url)
 *   GITHUB_TOKEN GitHub token for API requests (can be used instead of --token)
 */

const fs = require('fs-extra');
const path = require('path');
const glob = require('glob');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const https = require('https');
const chalk = require('chalk');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('format', {
    type: 'string',
    description: 'Output format (json or markdown)',
    default: 'markdown',
    choices: ['json', 'markdown']
  })
  .option('repo-url', {
    type: 'string',
    description: 'Repository URL to use for PR links (overrides package.json)',
    default: ''
  })
  .option('token', {
    type: 'string',
    description: 'GitHub token for API requests (optional, helps avoid rate limits)',
    default: ''
  })
  .help()
  .argv;

/**
 * Read all changesets from the .changesets directory
 *
 * @returns {Array} Array of changeset objects
 */
function readChangesets() {
  const changesetDir = path.join(process.cwd(), '.changesets');

  if (!fs.existsSync(changesetDir)) {
    console.log('No .changesets directory found. Creating one...');
    fs.mkdirSync(changesetDir, { recursive: true });
    return [];
  }

  const changesetFiles = glob.sync('*.md', { cwd: changesetDir });

  if (changesetFiles.length === 0) {
    console.log('No changesets found.');
    return [];
  }

  return changesetFiles.map(file => {
    const content = fs.readFileSync(path.join(changesetDir, file), 'utf8');
    const frontMatter = content.match(/---\n([\s\S]*?)\n---/);

    if (!frontMatter) {
      console.warn(`Invalid changeset format in ${file}. Skipping.`);
      return null;
    }

    try {
      // Parse the YAML-like front matter
      const lines = frontMatter[1].split('\n');
      const changeset = {};

      lines.forEach(line => {
        if (line.trim() === '' || line.startsWith('description:')) return;

        const [key, ...valueParts] = line.split(':');
        const value = valueParts.join(':').trim();

        // Remove quotes if present
        changeset[key.trim()] = value.replace(/^"(.*)"$/, '$1');
      });

      // Extract description
      const descriptionMatch = content.match(/description: \|\n([\s\S]*?)(\n---|\n$)/);
      if (descriptionMatch) {
        changeset.description = descriptionMatch[1].trim();
      }

      return changeset;
    } catch (err) {
      console.warn(`Error parsing changeset ${file}:`, err);
      return null;
    }
  }).filter(Boolean);
}

/**
 * Get the repository URL from package.json
 *
 * @returns {string} Repository URL
 */
function getRepositoryUrl() {
  // Check command line argument first
  if (argv['repo-url']) {
    return argv['repo-url'];
  }

  // Then check environment variable
  if (process.env.REPO_URL) {
    return process.env.REPO_URL;
  }

  try {
    const packageJson = require(path.join(process.cwd(), 'package.json'));
    if (packageJson.repository) {
      if (typeof packageJson.repository === 'string') {
        return packageJson.repository;
      } else if (packageJson.repository.url) {
        // Convert git URL to HTTPS URL if needed
        const url = packageJson.repository.url
          .replace(/^git\+/, '')
          .replace(/\.git$/, '');
        return url;
      }
    }

    // Check if this might be a fork and try to determine the upstream repo
    if (packageJson.name && packageJson.name.includes('/')) {
      // This might be an organization/repo format
      return `https://github.com/${packageJson.name}`;
    } else if (packageJson.name) {
      // Default to GitHub URL based on package name
      return `https://github.com/wp-graphql/${packageJson.name}`;
    }

    console.warn('Could not determine repository URL from package.json');
    return 'https://github.com/wp-graphql/automation-tests';
  } catch (err) {
    console.warn('Could not determine repository URL from package.json:', err.message);
    return 'https://github.com/wp-graphql/automation-tests';
  }
}

/**
 * Get GitHub token from command line or environment
 *
 * @returns {string} GitHub token
 */
function getGitHubToken() {
  // Check command line argument first
  if (argv.token) {
    return argv.token;
  }

  // Then check environment variable
  if (process.env.GITHUB_TOKEN) {
    return process.env.GITHUB_TOKEN;
  }

  return '';
}

/**
 * Extract owner and repo from repository URL
 *
 * @param {string} repoUrl Repository URL
 * @returns {Object} Object with owner and repo properties
 */
function extractRepoInfo(repoUrl) {
  const match = repoUrl.match(/github\.com\/([^\/]+)\/([^\/]+)/);
  if (match) {
    return {
      owner: match[1],
      repo: match[2]
    };
  }
  return null;
}

/**
 * Make a request to the GitHub API
 *
 * @param {string} path API path
 * @param {Object} options Request options
 * @returns {Promise<Object>} Response data
 */
function githubApiRequest(path, options = {}) {
  return new Promise((resolve, reject) => {
    const requestOptions = {
      hostname: 'api.github.com',
      path,
      headers: {
        'User-Agent': 'generate-release-notes-script',
        'Accept': 'application/vnd.github.v3+json'
      },
      ...options
    };

    // Add authorization header if token is provided
    const token = getGitHubToken();
    if (token) {
      requestOptions.headers['Authorization'] = `token ${token}`;
    }

    const req = https.get(requestOptions, (res) => {
      let data = '';

      res.on('data', (chunk) => {
        data += chunk;
      });

      res.on('end', () => {
        if (res.statusCode >= 400) {
          console.warn(`GitHub API request failed: ${res.statusCode} ${res.statusMessage}`);
          console.warn(`Response: ${data}`);
          resolve(null);
          return;
        }

        try {
          resolve(JSON.parse(data));
        } catch (err) {
          console.warn('Error parsing GitHub API response:', err);
          resolve(null);
        }
      });
    });

    req.on('error', (err) => {
      console.warn('Error making GitHub API request:', err);
      reject(err);
    });

    req.end();
  });
}

/**
 * Check if a user is a first-time contributor
 *
 * @param {string} username GitHub username
 * @param {Object} repoInfo Repository info (owner and repo)
 * @returns {Promise<boolean>} Whether the user is a first-time contributor
 */
async function isFirstTimeContributor(username, repoInfo) {
  try {
    // Search for commits by the user
    const searchPath = `/search/commits?q=author:${username}+repo:${repoInfo.owner}/${repoInfo.repo}`;
    const searchResult = await githubApiRequest(searchPath);

    if (!searchResult) {
      console.warn(`Could not determine if ${username} is a first-time contributor.`);
      return false;
    }

    // If total_count is 1 and we're generating release notes for that commit, they're a first-time contributor
    // We'll consider them a first-time contributor if they have 3 or fewer commits
    return searchResult.total_count <= 3;
  } catch (err) {
    console.warn(`Error checking if ${username} is a first-time contributor:`, err);
    return false;
  }
}

/**
 * Get contributor information
 *
 * @param {Array} changesets Array of changeset objects
 * @returns {Promise<Object>} Object with contributors and firstTimeContributors arrays
 */
async function getContributorInfo(changesets) {
  // Extract unique authors
  const authors = [...new Set(changesets.map(changeset => changeset.author))].filter(Boolean);

  if (authors.length === 0) {
    return {
      contributors: [],
      firstTimeContributors: []
    };
  }

  const repoUrl = getRepositoryUrl();
  const repoInfo = extractRepoInfo(repoUrl);

  if (!repoInfo) {
    console.warn('Could not extract repository info from URL:', repoUrl);
    return {
      contributors: authors,
      firstTimeContributors: []
    };
  }

  // Check if each author is a first-time contributor
  const firstTimeContributors = [];

  // Only check for first-time contributors if we have a token
  const token = getGitHubToken();
  if (token) {
    for (const author of authors) {
      const isFirstTimer = await isFirstTimeContributor(author, repoInfo);
      if (isFirstTimer) {
        firstTimeContributors.push(author);
      }
    }
  } else {
    console.warn('No GitHub token provided. Skipping first-time contributor check.');
  }

  return {
    contributors: authors,
    firstTimeContributors
  };
}

/**
 * Format PR link based on repository URL and PR number
 *
 * @param {string} prNumber PR number
 * @returns {string} Formatted PR link
 */
function formatPrLink(prNumber) {
  const repoUrl = getRepositoryUrl();
  if (!repoUrl) {
    return `#${prNumber}`;
  }

  return `${repoUrl}/pull/${prNumber}`;
}

/**
 * Format GitHub user link
 *
 * @param {string} username GitHub username
 * @returns {string} Formatted user link
 */
function formatUserLink(username) {
  return `[@${username}](https://github.com/${username})`;
}

/**
 * Check if a changeset indicates a breaking change
 *
 * @param {Object} changeset Changeset object
 * @returns {boolean} Whether the changeset indicates a breaking change
 */
function isBreakingChange(changeset) {
  // If breaking is explicitly set to false, respect that regardless of other indicators
  if (changeset.breaking === 'false' || changeset.breaking === false) {
    return false;
  }

  // Check explicit breaking flag
  if (changeset.breaking === 'true' || changeset.breaking === true) {
    return true;
  }

  // Check for conventional commit breaking change indicator (!)
  if (changeset.title && changeset.title.includes('!:')) {
    return true;
  }

  // Check for "BREAKING CHANGE:" or "BREAKING-CHANGE:" prefix in title (case insensitive)
  if (changeset.title) {
    const breakingPrefix = /^(BREAKING CHANGE|BREAKING-CHANGE):/i;
    if (breakingPrefix.test(changeset.title)) {
      return true;
    }
  }

  return false;
}

/**
 * Categorize changesets
 *
 * @param {Array} changesets Array of changeset objects
 * @returns {Object} Categorized changesets and bump type
 */
function categorizeChangesets(changesets) {
  if (changesets.length === 0) {
    return {
      bumpType: 'none',
      breakingChanges: [],
      features: [],
      fixes: [],
      other: []
    };
  }

  const breakingChanges = changesets.filter(changeset => isBreakingChange(changeset));

  const features = changesets.filter(changeset =>
    changeset.type === 'feat' && !isBreakingChange(changeset)
  );

  const fixes = changesets.filter(changeset =>
    changeset.type === 'fix' && !isBreakingChange(changeset)
  );

  const other = changesets.filter(changeset =>
    !isBreakingChange(changeset) && changeset.type !== 'feat' && changeset.type !== 'fix'
  );

  let bumpType = 'patch';

  if (breakingChanges.length > 0) {
    bumpType = 'major';
  } else if (features.length > 0) {
    bumpType = 'minor';
  }

  return {
    bumpType,
    breakingChanges,
    features,
    fixes,
    other
  };
}

/**
 * Add milestone grouping function
 *
 * @param {Array} changesets Array of changeset objects
 * @returns {Object} Object with changesets grouped by milestone
 */
const groupByMilestone = (changesets) => {
  return changesets.reduce((acc, changeset) => {
    if (changeset.milestone) {
      if (!acc[changeset.milestone]) {
        acc[changeset.milestone] = [];
      }
      acc[changeset.milestone].push(changeset);
    }
    return acc;
  }, {});
};

/**
 * Generate markdown release notes
 *
 * @param {Object} categories Categorized changesets
 * @param {Object} contributorInfo Contributor information
 * @returns {string} Markdown release notes
 */
function generateMarkdownReleaseNotes(categories, contributorInfo) {
  const { bumpType, breakingChanges, features, fixes, other } = categories;
  const { contributors, firstTimeContributors } = contributorInfo;

  if (bumpType === 'none') {
    return 'No changes to release at this time.';
  }

  let markdown = '## Changelog\n\n';
  markdown += `**Bump Type:** ${bumpType}\n\n`;

  // Add Milestones section
  const milestoneGroups = groupByMilestone(breakingChanges);
  if (Object.keys(milestoneGroups).length > 0) {
    markdown += '### 🎯 Completed Milestones\n';
    Object.keys(milestoneGroups)
      .sort()
      .forEach(milestone => {
        const prs = milestoneGroups[milestone];
        // Link to the milestone PR that was merged
        markdown += `- **${milestone}** (#${prs[0].pr})\n`;
      });
    markdown += '\n';
  }

  if (breakingChanges.length > 0) {
    markdown += '### ⚠️ BREAKING CHANGES\n';
    breakingChanges.forEach(change => {
      const prLink = formatPrLink(change.pr);
      markdown += `- ${change.title} ([#${change.pr}](${prLink}))\n`;
    });
    markdown += '\n';
  }

  if (features.length > 0) {
    markdown += '### ✨ New Features\n';
    features.forEach(feature => {
      const prLink = formatPrLink(feature.pr);
      markdown += `- ${feature.title} ([#${feature.pr}](${prLink}))\n`;
    });
    markdown += '\n';
  }

  if (fixes.length > 0) {
    markdown += '### 🐛 Bug Fixes\n';
    fixes.forEach(fix => {
      const prLink = formatPrLink(fix.pr);
      markdown += `- ${fix.title} ([#${fix.pr}](${prLink}))\n`;
    });
    markdown += '\n';
  }

  if (other.length > 0) {
    markdown += '### 🔄 Other Changes\n';
    other.forEach(change => {
      const prLink = formatPrLink(change.pr);
      markdown += `- ${change.title} ([#${change.pr}](${prLink}))\n`;
    });
    markdown += '\n';
  }

  // Add contributors section if there are any
  if (contributors.length > 0) {
    markdown += '### 👏 Contributors\n\n';
    markdown += 'Thanks to the following contributors for making this release possible:\n\n';

    contributors.forEach(contributor => {
      const userLink = formatUserLink(contributor);
      markdown += `- ${userLink}\n`;
    });

    // Add special recognition for first-time contributors
    if (firstTimeContributors.length > 0) {
      markdown += '\n### 🎉 First-time Contributors\n\n';
      markdown += 'Special thanks to the following first-time contributors:\n\n';

      firstTimeContributors.forEach(contributor => {
        const userLink = formatUserLink(contributor);
        markdown += `- ${userLink}\n`;
      });
    }

    markdown += '\n';
  }

  return markdown;
}

/**
 * Generate JSON release notes
 *
 * @param {Object} categories Categorized changesets
 * @param {Object} contributorInfo Contributor information
 * @returns {string} JSON release notes
 */
function generateJsonReleaseNotes(categories, contributorInfo) {
  const { bumpType, breakingChanges, features, fixes, other } = categories;
  const { contributors, firstTimeContributors } = contributorInfo;

  const result = {
    bumpType,
    changes: {
      breakingChanges: breakingChanges.map(change => ({
        title: change.title,
        pr: change.pr,
        prUrl: formatPrLink(change.pr),
        author: change.author
      })),
      features: features.map(feature => ({
        title: feature.title,
        pr: feature.pr,
        prUrl: formatPrLink(feature.pr),
        author: feature.author
      })),
      fixes: fixes.map(fix => ({
        title: fix.title,
        pr: fix.pr,
        prUrl: formatPrLink(fix.pr),
        author: fix.author
      })),
      other: other.map(change => ({
        title: change.title,
        pr: change.pr,
        prUrl: formatPrLink(change.pr),
        author: change.author,
        type: change.type
      }))
    },
    contributors: {
      all: contributors.map(contributor => ({
        username: contributor,
        url: `https://github.com/${contributor}`
      })),
      firstTime: firstTimeContributors.map(contributor => ({
        username: contributor,
        url: `https://github.com/${contributor}`
      }))
    }
  };

  return JSON.stringify(result, null, 2);
}

/**
 * Main function
 */
async function main() {
  try {
    const changesets = readChangesets();
    const categories = categorizeChangesets(changesets);
    const contributorInfo = await getContributorInfo(changesets);

    if (argv.format === 'json') {
      console.log(generateJsonReleaseNotes(categories, contributorInfo));
    } else {
      console.log(generateMarkdownReleaseNotes(categories, contributorInfo));
    }
  } catch (err) {
    console.error('Error generating release notes:', err);
    process.exit(1);
  }
}

// Run the script
main();