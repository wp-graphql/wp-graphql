#!/usr/bin/env node

/**
 * Script to update CHANGELOG.md based on changesets
 * 
 * Usage:
 *   node scripts/update-changelog.js
 *   node scripts/update-changelog.js --version=1.2.3
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
 * Generate changelog content from grouped changesets
 * 
 * @param {Object} groups Object with changesets grouped by type
 * @param {string} version Version for the changelog entry
 * @returns {string} Formatted changelog content
 */
function generateChangelogContent(groups, version) {
  const date = new Date().toISOString().split('T')[0];
  let content = `## v${version} - ${date}\n\n`;
  
  // Add breaking changes first
  if (groups.breaking.length > 0) {
    content += '### BREAKING CHANGES\n\n';
    groups.breaking.forEach(changeset => {
      content += `- ${changeset.title} (#${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  // Add features
  if (groups.feat.length > 0) {
    content += '### New Features\n\n';
    groups.feat.forEach(changeset => {
      content += `- ${changeset.title} (#${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  // Add bug fixes
  if (groups.fix.length > 0) {
    content += '### Bug Fixes\n\n';
    groups.fix.forEach(changeset => {
      content += `- ${changeset.title} (#${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  // Add other change types
  const otherTypes = [
    { key: 'docs', title: 'Documentation' },
    { key: 'style', title: 'Styles' },
    { key: 'refactor', title: 'Code Refactoring' },
    { key: 'perf', title: 'Performance Improvements' },
    { key: 'test', title: 'Tests' },
    { key: 'build', title: 'Build System' },
    { key: 'ci', title: 'Continuous Integration' },
    { key: 'chore', title: 'Chores' },
    { key: 'revert', title: 'Reverts' },
    { key: 'other', title: 'Other Changes' }
  ];
  
  otherTypes.forEach(({ key, title }) => {
    if (groups[key] && groups[key].length > 0 && key !== 'breaking') {
      content += `### ${title}\n\n`;
      groups[key].forEach(changeset => {
        content += `- ${changeset.title} (#${changeset.pr})\n`;
      });
      content += '\n';
    }
  });
  
  return content;
}

/**
 * Update the CHANGELOG.md file with new content
 * @param {string} newContent - The new changelog content to add
 * @param {string} version - The version being released
 */
function updateChangelog(newContent, version) {
  const changelogPath = path.join(process.cwd(), 'CHANGELOG.md');
  
  // Create changelog if it doesn't exist
  if (!fs.existsSync(changelogPath)) {
    fs.writeFileSync(changelogPath, '# Changelog\n\n');
  }
  
  const existingContent = fs.readFileSync(changelogPath, 'utf8');
  
  // Extract the header (everything before the first ## heading)
  const headerMatch = existingContent.match(/^([\s\S]*?)(?=##|$)/);
  const header = headerMatch ? headerMatch[1] : '';
  const rest = existingContent.substring(header.length);
  
  // Combine header, new content, and existing entries
  const updatedContent = header + newContent + rest;
  
  fs.writeFileSync(changelogPath, updatedContent);
  console.log(`Updated CHANGELOG.md with new entries for v${version}`);
}

/**
 * Move processed changesets to archive directory
 */
function archiveChangesets() {
  const changesetDir = path.join(process.cwd(), '.changesets');
  const archiveDir = path.join(changesetDir, 'archive');
  
  if (!fs.existsSync(archiveDir)) {
    fs.mkdirSync(archiveDir, { recursive: true });
  }
  
  const changesetFiles = glob.sync('*.md', { cwd: changesetDir });
  
  changesetFiles.forEach(file => {
    const sourcePath = path.join(changesetDir, file);
    const destPath = path.join(archiveDir, file);
    
    fs.moveSync(sourcePath, destPath, { overwrite: true });
  });
  
  console.log(`Moved ${changesetFiles.length} changesets to archive directory`);
}

/**
 * Update changelog based on changesets
 */
function updateChangelogFromChangesets() {
  try {
    const version = argv.version || getCurrentVersion();
    const changesets = readChangesets();
    
    if (changesets.length === 0) {
      console.log('No changesets found. Skipping changelog update.');
      return;
    }
    
    const groupedChangesets = groupChangesetsByType(changesets);
    const changelogContent = generateChangelogContent(groupedChangesets, version);
    
    updateChangelog(changelogContent, version);
    archiveChangesets();
    
    console.log('Changelog update complete!');
  } catch (err) {
    console.error('Error updating changelog:', err);
    process.exit(1);
  }
}

// Run the script
updateChangelogFromChangesets(); 