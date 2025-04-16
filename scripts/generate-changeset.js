#!/usr/bin/env node

/**
 * Script to generate a changeset file from PR information
 *
 * Usage:
 *   node scripts/generate-changeset.js --pr=123 --title="feat: Add new feature" --author="username" --body="Description of the change"
 *   node scripts/generate-changeset.js --pr=123 --title="feat!: Add breaking feature" --author="username" --body="Description of the change"
 *   node scripts/generate-changeset.js --pr=123 --title="BREAKING CHANGE: Refactor API" --author="username" --body="Description of the change"
 *
 * Options:
 *   --pr         PR number
 *   --title      PR title
 *   --author     PR author
 *   --body       PR description
 *   --breaking   Explicitly mark as breaking change (true/false)
 *
 * Breaking Change Detection:
 *   Breaking changes are automatically detected from:
 *   1. Conventional commit syntax with ! (e.g., "feat!: Add breaking feature")
 *   2. Title prefixed with "BREAKING CHANGE:" or "BREAKING-CHANGE:"
 *   3. Description containing "BREAKING CHANGE:" or "BREAKING-CHANGE:"
 *   4. Explicit --breaking=true flag
 */

const fs = require('fs-extra');
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const { getEnvVar } = require('./utils/env');
const chalk = require('chalk');
const yaml = require('js-yaml');

// Get GitHub token from environment variables
// This will work with both GitHub Actions (GITHUB_TOKEN) and local .env file
const githubToken = getEnvVar('GITHUB_TOKEN', '');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('pr', {
    type: 'number',
    description: 'PR number',
    demandOption: true
  })
  .option('title', {
    type: 'string',
    description: 'PR title',
    demandOption: true
  })
  .option('author', {
    type: 'string',
    description: 'PR author',
    demandOption: true
  })
  .option('body', {
    type: 'string',
    description: 'PR description',
    default: ''
  })
  .option('breaking', {
    type: 'boolean',
    description: 'Whether the PR indicates a breaking change',
    default: false
  })
  .option('branchRef', {
    type: 'string',
    description: 'Branch reference',
    default: process.env.GITHUB_REF_NAME
  })
  .help()
  .argv;

/**
 * Extract change type from PR title (feat, fix, etc.)
 *
 * @param {string} title PR title
 * @returns {string} Change type
 */
function extractChangeType(title) {
  // Updated regex to handle optional ! for breaking changes
  const match = title.match(/^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(!)?:/);
  return match ? match[1] : 'other';
}

/**
 * Check if PR indicates a breaking change
 *
 * @param {string} title PR title
 * @param {string} body PR description
 * @returns {boolean} Whether the PR indicates a breaking change
 */
function isBreakingChange(title, body) {
  // Check for explicit breaking flag in command line args
  if (argv.breaking === true || argv.breaking === 'true') {
    return true;
  }

  // Check for conventional commit breaking change indicator (!)
  if (title.includes('!:')) {
    return true;
  }

  // Check for "BREAKING CHANGE:" or "BREAKING-CHANGE:" prefix in title (case insensitive)
  const breakingPrefix = /^(BREAKING CHANGE|BREAKING-CHANGE):/i;
  if (breakingPrefix.test(title)) {
    return true;
  }

  // Check for "BREAKING CHANGE:" or "BREAKING-CHANGE:" in body
  if (body.includes('BREAKING CHANGE:') || body.includes('BREAKING-CHANGE:')) {
    return true;
  }

  return false;
}

/**
 * Get milestone name from branch reference
 *
 * @param {string} branchRef Branch reference
 * @returns {string|null} Milestone name or null if no milestone
 */
const getMilestoneName = (branchRef) => {
  if (branchRef && branchRef.startsWith('milestone/')) {
    return branchRef.replace('milestone/', '');
  }
  return null;
};

/**
 * Generate a changeset file
 */
const generateChangeset = async ({
  pr,
  title,
  author,
  body,
  branchRef
}) => {
  // Create .changesets directory if it doesn't exist
  const changesetDir = path.join(process.cwd(), '.changesets');
  await fs.ensureDir(changesetDir);

  // Extract PR information
  const changeType = extractChangeType(title);
  const breaking = isBreakingChange(title, body);
  const milestone = getMilestoneName(branchRef);

  // Sanitize the inputs to handle special characters
  const sanitizedTitle = title.replace(/`/g, '\\`');
  const sanitizedBody = body.replace(/`/g, '\\`');

  const changesetData = {
    title: sanitizedTitle,
    pr,
    author,
    type: changeType,
    breaking,
    ...(milestone && { milestone }),
  };

  // Manually format the YAML to avoid js-yaml's automatic block scalar formatting
  const yamlContent = Object.entries(changesetData)
    .map(([key, value]) => {
      // For string values that contain special characters, wrap in quotes
      const formattedValue = typeof value === 'string' ?
        `"${value.replace(/"/g, '\\"')}"` :
        value;
      return `${key}: ${formattedValue}`;
    })
    .join('\n');

  const content = `---
${yamlContent}
---

${sanitizedBody || sanitizedTitle}
`;

  // Generate unique filename with timestamp and PR number
  const timestamp = new Date().toISOString().replace(/[:.]/g, '').split('T')[0];
  const filename = path.join(changesetDir, `${timestamp}-pr-${pr}.md`);

  // Write changeset file
  await fs.promises.writeFile(filename, content, 'utf8');
  console.log(`Changeset created: ${filename}`);
  return filename;
};

// Run the script
generateChangeset(argv).catch(err => {
  console.error('Error generating changeset:', err);
  process.exit(1);
});