#!/usr/bin/env node

/**
 * Script to update readme.txt with changelog entries
 * 
 * Usage:
 *   node scripts/update-readme.js
 *   node scripts/update-readme.js --version=1.2.3
 * 
 * Options:
 *   --version  Version to use for the changelog entry (defaults to version in constants.php)
 */

const fs = require('fs-extra');
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const glob = require('glob');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('version', {
    type: 'string',
    description: 'Version to use for the changelog entry'
  })
  .help()
  .argv;

/**
 * Get current version from constants.php
 * 
 * @returns {string} Current version
 */
function getCurrentVersion() {
  const constantsPath = path.join(process.cwd(), 'constants.php');
  const contents = fs.readFileSync(constantsPath, 'utf8');
  const match = contents.match(/define\('AUTOMATION_TESTS_VERSION', '([^']+)'\)/);
  
  if (!match) {
    throw new Error('Could not find version in constants.php');
  }
  
  return match[1];
}

/**
 * Read all changeset files
 * @returns {Array} Array of changeset objects
 */
function readChangesets() {
  let changesetDir = path.join(process.cwd(), '.changesets');
  const archiveDir = path.join(changesetDir, 'archive');
  
  if (!fs.existsSync(changesetDir)) {
    console.log('No .changesets directory found.');
    return [];
  }
  
  let changesetFiles = glob.sync('*.md', { cwd: changesetDir });
  
  // If no changesets in main directory, check archive
  if (changesetFiles.length === 0 && fs.existsSync(archiveDir)) {
    changesetFiles = glob.sync('*.md', { cwd: archiveDir });
    
    if (changesetFiles.length === 0) {
      console.log('No changesets found in main directory or archive.');
      return [];
    }
    
    // Use archive directory for reading
    changesetDir = archiveDir;
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
 * Group changesets by type
 * 
 * @param {Array} changesets Array of changeset objects
 * @returns {Object} Object with changesets grouped by type
 */
function groupChangesetsByType(changesets) {
  const groups = {
    breaking: [],
    feat: [],
    fix: [],
    docs: [],
    style: [],
    refactor: [],
    perf: [],
    test: [],
    build: [],
    ci: [],
    chore: [],
    revert: [],
    other: []
  };
  
  changesets.forEach(changeset => {
    const type = changeset.type || 'other';
    
    if (changeset.breaking === 'true') {
      groups.breaking.push(changeset);
    }
    
    if (groups[type]) {
      groups[type].push(changeset);
    } else {
      groups.other.push(changeset);
    }
  });
  
  return groups;
}

/**
 * Generate readme.txt changelog content from grouped changesets
 * 
 * @param {Object} groups Object with changesets grouped by type
 * @param {string} version Version for the changelog entry
 * @returns {string} Formatted changelog content for readme.txt
 */
function generateReadmeChangelogContent(groups, version) {
  let content = `= ${version} =\n`;
  
  // Add breaking changes first
  if (groups.breaking.length > 0) {
    content += '\n**BREAKING CHANGES**\n\n';
    groups.breaking.forEach(changeset => {
      // Remove the type prefix from the title for cleaner readme entries
      const title = changeset.title.replace(/^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)!?: /, '');
      content += `* ${title}\n`;
    });
  }
  
  // Add features
  if (groups.feat.length > 0) {
    content += '\n**New Features**\n\n';
    groups.feat.forEach(changeset => {
      const title = changeset.title.replace(/^feat!?: /, '');
      content += `* ${title}\n`;
    });
  }
  
  // Add bug fixes
  if (groups.fix.length > 0) {
    content += '\n**Bug Fixes**\n\n';
    groups.fix.forEach(changeset => {
      const title = changeset.title.replace(/^fix!?: /, '');
      content += `* ${title}\n`;
    });
  }
  
  // Add other change types if they exist
  const otherTypes = [
    { key: 'docs', title: 'Documentation' },
    { key: 'style', title: 'Styles' },
    { key: 'refactor', title: 'Code Refactoring' },
    { key: 'perf', title: 'Performance' },
    { key: 'test', title: 'Tests' },
    { key: 'build', title: 'Build' },
    { key: 'ci', title: 'CI' },
    { key: 'chore', title: 'Maintenance' },
    { key: 'revert', title: 'Reverts' },
    { key: 'other', title: 'Other Changes' }
  ];
  
  otherTypes.forEach(({ key, title }) => {
    if (groups[key] && groups[key].length > 0 && key !== 'breaking') {
      content += `\n**${title}**\n\n`;
      groups[key].forEach(changeset => {
        const cleanTitle = changeset.title.replace(new RegExp(`^${key}!?: `), '');
        content += `* ${cleanTitle}\n`;
      });
    }
  });
  
  return content;
}

/**
 * Generate upgrade notice content from grouped changesets
 * 
 * @param {Object} groups Object with changesets grouped by type
 * @param {string} version Version for the upgrade notice
 * @returns {string} Formatted upgrade notice content for readme.txt
 */
function generateUpgradeNoticeContent(groups, version) {
  // Only generate upgrade notices for versions with breaking changes
  if (groups.breaking.length === 0) {
    return '';
  }
  
  let content = `= ${version} =\n\n`;
  content += `**⚠️ BREAKING CHANGES**: This release contains breaking changes that may require updates to your code.\n\n`;
  
  // Add breaking changes
  groups.breaking.forEach(changeset => {
    const title = changeset.title;
    const prLink = changeset.pr ? `https://github.com/jasonbahl/automation-tests/pull/${changeset.pr}` : '';
    content += `* ${title}${prLink ? ` (${prLink})` : ''}\n`;
  });
  
  content += '\nPlease review these changes before upgrading.\n';
  
  return content;
}

/**
 * Update readme.txt with new changelog content
 * 
 * @param {string} newContent New changelog content to add
 * @param {string} version Version for the changelog entry
 * @param {string} upgradeNotice Upgrade notice content to add (optional)
 */
function updateReadme(newContent, version, upgradeNotice = '') {
  const readmePath = path.join(process.cwd(), 'readme.txt');
  
  // Create readme.txt if it doesn't exist
  if (!fs.existsSync(readmePath)) {
    const defaultReadme = `=== Automation Tests ===
Contributors: jasonbahl
Tags: testing, automation
Requires at least: 5.0
Tested up to: 6.2
Stable tag: ${version}
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to test automation workflows.

== Description ==

This is a test repository for experimenting with GitHub Workflows for WordPress plugin development and release management.

== Installation ==

1. Upload the plugin files to the \`/wp-content/plugins/automation-tests\` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress

== Frequently Asked Questions ==

= What is this plugin for? =

This plugin is a testing ground for GitHub Actions workflows before implementing them in production repositories.

== Upgrade Notice ==

${upgradeNotice}

== Changelog ==

`;
    fs.writeFileSync(readmePath, defaultReadme);
    console.log('Created new readme.txt file');
  }
  
  let readmeContent = fs.readFileSync(readmePath, 'utf8');
  
  // Update stable tag
  readmeContent = readmeContent.replace(
    /Stable tag: .+/,
    `Stable tag: ${version}`
  );
  
  // Update upgrade notice if provided
  if (upgradeNotice) {
    const upgradeNoticeMatch = readmeContent.match(/(== Upgrade Notice ==\n\n)([\s\S]*?)(\n\n==|$)/);
    
    if (upgradeNoticeMatch) {
      // Insert new upgrade notice at the top of the upgrade notice section
      readmeContent = readmeContent.replace(
        /(== Upgrade Notice ==\n\n)([\s\S]*?)(\n\n==|$)/,
        `$1${upgradeNotice}\n\n$2$3`
      );
    } else {
      // Add upgrade notice section if it doesn't exist
      readmeContent = readmeContent.replace(
        /(== Frequently Asked Questions ==[\s\S]*?)(\n\n==|$)/,
        `$1\n\n== Upgrade Notice ==\n\n${upgradeNotice}$2`
      );
    }
  }
  
  // Find the changelog section
  const changelogMatch = readmeContent.match(/(== Changelog ==\n\n)([\s\S]*)/);
  
  if (changelogMatch) {
    // Insert new changelog entry at the top of the changelog section
    const changelogHeader = changelogMatch[1];
    const existingChangelog = changelogMatch[2];
    
    readmeContent = readmeContent.replace(
      /(== Changelog ==\n\n)([\s\S]*)/,
      `$1${newContent}\n\n$2`
    );
  } else {
    // Add changelog section if it doesn't exist
    readmeContent += `\n== Changelog ==\n\n${newContent}\n`;
  }
  
  fs.writeFileSync(readmePath, readmeContent);
  console.log(`Updated readme.txt with changelog entries for v${version}`);
}

/**
 * Update readme.txt based on changesets
 */
function updateReadmeFromChangesets() {
  try {
    const version = argv.version || getCurrentVersion();
    const changesets = readChangesets();
    
    if (changesets.length === 0) {
      console.log('No changesets found. Skipping readme.txt update.');
      return;
    }
    
    const groupedChangesets = groupChangesetsByType(changesets);
    const readmeContent = generateReadmeChangelogContent(groupedChangesets, version);
    const upgradeNotice = generateUpgradeNoticeContent(groupedChangesets, version);
    
    updateReadme(readmeContent, version, upgradeNotice);
    
    console.log('readme.txt update complete!');
  } catch (err) {
    console.error('Error updating readme.txt:', err);
    process.exit(1);
  }
}

// Run the script
updateReadmeFromChangesets(); 