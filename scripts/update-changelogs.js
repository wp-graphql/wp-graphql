#!/usr/bin/env node

/**
 * Script to update both CHANGELOG.md and readme.txt with changeset contents
 * 
 * Usage:
 *   node scripts/update-changelogs.js --new-version=1.2.3
 * 
 * Options:
 *   --new-version  Version to use in the changelog (defaults to current version in constants.php)
 */

const fs = require('fs-extra');
const path = require('path');
const glob = require('glob');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('new-version', {
    type: 'string',
    description: 'Version to add to changelog',
    demandOption: true
  })
  .option('notes-file', {
    type: 'string',
    description: 'Path to release notes file',
    demandOption: false
  })
  .version(false)
  .help()
  .argv;

/**
 * Get current version from constants.php
 * @returns {string} Current version
 */
function getCurrentVersion() {
  const constantsPath = path.join(process.cwd(), 'constants.php');
  
  if (!fs.existsSync(constantsPath)) {
    console.error('constants.php not found');
    process.exit(1);
  }
  
  const contents = fs.readFileSync(constantsPath, 'utf8');
  const versionMatch = contents.match(/define\s*\(\s*['"]AUTOMATION_TESTS_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/);
  
  if (!versionMatch) {
    console.error('Version constant not found in constants.php');
    process.exit(1);
  }
  
  return versionMatch[1];
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
    
    const frontMatterContent = frontMatter[1];
    const data = {};
    
    // Parse front matter
    frontMatterContent.split('\n').forEach(line => {
      const [key, ...valueParts] = line.split(':');
      if (key && valueParts.length) {
        let value = valueParts.join(':').trim();
        
        // Handle quoted values
        if (value.startsWith('"') && value.endsWith('"')) {
          value = value.slice(1, -1);
        }
        
        // Handle pipe syntax for multiline
        if (value === '|') {
          const descriptionMatch = content.match(/---\n[\s\S]*?\n---\n\n([\s\S]*)/);
          if (descriptionMatch) {
            data.description = descriptionMatch[1].trim();
          }
        } else {
          data[key.trim()] = value;
        }
      }
    });
    
    // Add filename for reference
    data.file = file;
    
    return data;
  }).filter(Boolean);
}

/**
 * Group changesets by type (breaking, feature, fix, etc.)
 * @param {Array} changesets Array of changeset objects
 * @returns {Object} Grouped changesets
 */
function groupChangesetsByType(changesets) {
  const groups = {
    breaking: [],
    feat: [],
    fix: [],
    other: []
  };
  
  changesets.forEach(changeset => {
    if (changeset.breaking === 'true') {
      groups.breaking.push(changeset);
    } else if (changeset.type === 'feat') {
      groups.feat.push(changeset);
    } else if (changeset.type === 'fix') {
      groups.fix.push(changeset);
    } else {
      groups.other.push(changeset);
    }
  });
  
  return groups;
}

/**
 * Generate GitHub-style changelog content from grouped changesets
 * @param {Object} groups Grouped changesets
 * @param {string} version Version to use in the changelog
 * @returns {string} Changelog content
 */
function generateGitHubChangelogContent(groups, version) {
  const date = new Date().toISOString().split('T')[0];
  let content = `## v${version} - ${date}\n\n`;
  
  // If there are breaking changes, add a prominent warning
  if (groups.breaking.length > 0) {
    content += '> ⚠️ **BREAKING CHANGES**: This release contains breaking changes. Please review before upgrading.\n\n';
  }
  
  if (groups.breaking.length > 0) {
    content += '### Breaking Changes\n\n';
    groups.breaking.forEach(changeset => {
      const prLink = `[#${changeset.pr}](https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})`;
      content += `- ${changeset.title} (${prLink})\n`;
    });
    content += '\n';
  }
  
  if (groups.feat.length > 0) {
    content += '### New Features\n\n';
    groups.feat.forEach(changeset => {
      const prLink = `[#${changeset.pr}](https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})`;
      content += `- ${changeset.title} (${prLink})\n`;
    });
    content += '\n';
  }
  
  if (groups.fix.length > 0) {
    content += '### Bug Fixes\n\n';
    groups.fix.forEach(changeset => {
      const prLink = `[#${changeset.pr}](https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})`;
      content += `- ${changeset.title} (${prLink})\n`;
    });
    content += '\n';
  }
  
  if (groups.other.length > 0) {
    content += '### Other Changes\n\n';
    groups.other.forEach(changeset => {
      const prLink = `[#${changeset.pr}](https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})`;
      content += `- ${changeset.title} (${prLink})\n`;
    });
    content += '\n';
  }
  
  return content;
}

/**
 * Generate WordPress.org readme.txt changelog content from grouped changesets
 * @param {Object} groups Grouped changesets
 * @param {string} version Version to use in the changelog
 * @returns {string} Readme changelog content
 */
function generateWordPressChangelogContent(groups, version) {
  let content = `= ${version} =\n\n`;
  
  // If there are breaking changes, add a prominent warning
  if (groups.breaking.length > 0) {
    content += '**⚠️ BREAKING CHANGES**: This release contains breaking changes. Please review before upgrading.\n\n';
  }
  
  if (groups.breaking.length > 0) {
    content += '**Breaking Changes**\n\n';
    groups.breaking.forEach(changeset => {
      content += `* ${changeset.title} (https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  if (groups.feat.length > 0) {
    content += '**New Features**\n\n';
    groups.feat.forEach(changeset => {
      content += `* ${changeset.title} (https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  if (groups.fix.length > 0) {
    content += '**Bug Fixes**\n\n';
    groups.fix.forEach(changeset => {
      content += `* ${changeset.title} (https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  if (groups.other.length > 0) {
    content += '**Other Changes**\n\n';
    groups.other.forEach(changeset => {
      content += `* ${changeset.title} (https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})\n`;
    });
    content += '\n';
  }
  
  return content;
}

/**
 * Generate upgrade notice for readme.txt
 * @param {Object} groups Grouped changesets
 * @param {string} version Version to use in the upgrade notice
 * @returns {string} Upgrade notice content or empty string if no breaking changes
 */
function generateUpgradeNotice(groups, version) {
  if (groups.breaking.length === 0) {
    return '';
  }
  
  let content = `= ${version} =\n\n`;
  content += '**⚠️ BREAKING CHANGES**: This release contains breaking changes that may require updates to your code.\n\n';
  
  // List all breaking changes with links
  groups.breaking.forEach(changeset => {
    content += `* ${changeset.title} (https://github.com/jasonbahl/automation-tests/pull/${changeset.pr})\n`;
  });
  
  content += '\nPlease review these changes before upgrading.\n\n';
  
  return content;
}

/**
 * Update the CHANGELOG.md file with new content
 * @param {string} newContent The new changelog content to add
 * @param {string} version The version being released
 */
function updateGitHubChangelog(newContent, version) {
  const changelogPath = path.join(process.cwd(), 'CHANGELOG.md');
  
  // Create changelog if it doesn't exist
  if (!fs.existsSync(changelogPath)) {
    fs.writeFileSync(changelogPath, '# Changelog\n\n');
  }
  
  const existingContent = fs.readFileSync(changelogPath, 'utf8');
  
  // Check if this version already exists in the changelog
  const versionRegex = new RegExp(`## v${version} - \\d{4}-\\d{2}-\\d{2}`);
  const versionExists = versionRegex.test(existingContent);
  
  if (versionExists) {
    // Extract the existing entry for this version
    const existingEntryRegex = new RegExp(`(## v${version} - \\d{4}-\\d{2}-\\d{2}[\\s\\S]*?)(?=## v|$)`);
    const existingEntryMatch = existingContent.match(existingEntryRegex);
    const existingEntry = existingEntryMatch ? existingEntryMatch[1].trim() : '';
    
    // Compare with new content to see if it's different
    if (existingEntry.trim() === newContent.trim()) {
      console.log(`CHANGELOG.md already contains up-to-date entries for v${version}. Skipping update.`);
      return;
    }
    
    // If different, replace the existing entry
    console.log(`Updating existing entries for v${version} in CHANGELOG.md...`);
    const updatedContent = existingContent.replace(existingEntryRegex, newContent);
    fs.writeFileSync(changelogPath, updatedContent);
    console.log(`Updated existing entries for v${version} in CHANGELOG.md`);
    return;
  }
  
  // Find the position after the "# Changelog" header
  const headerMatch = existingContent.match(/^# Changelog\n+/);
  if (!headerMatch) {
    // If no header found, prepend it
    const updatedContent = '# Changelog\n\n' + newContent + existingContent;
    fs.writeFileSync(changelogPath, updatedContent);
  } else {
    // Insert new content after the header
    const headerEndPos = headerMatch[0].length;
    const updatedContent = 
      existingContent.substring(0, headerEndPos) + 
      newContent + 
      (existingContent.substring(headerEndPos).trim() ? '\n\n' + existingContent.substring(headerEndPos) : '');
    fs.writeFileSync(changelogPath, updatedContent);
  }
  
  console.log(`Added new entries for v${version} to CHANGELOG.md`);
}

/**
 * Update the readme.txt file with new content
 * @param {string} newContent The new changelog content to add
 * @param {string} version The version being released
 * @param {Object} groups Grouped changesets for generating upgrade notices
 */
function updateWordPressReadme(newContent, version, groups) {
  const readmePath = path.join(process.cwd(), 'readme.txt');
  
  if (!fs.existsSync(readmePath)) {
    console.error('Error: readme.txt not found. This file is required for WordPress plugins.');
    process.exit(1);
  }
  
  const existingContent = fs.readFileSync(readmePath, 'utf8');
  
  // Find the changelog section
  const changelogMatch = existingContent.match(/(== Changelog ==\n\n)([\s\S]*)/);
  
  if (!changelogMatch) {
    console.error('Error: Changelog section not found in readme.txt');
    process.exit(1);
  }
  
  // Check if this version already exists in the changelog
  const versionRegex = new RegExp(`= ${version} =`);
  const versionExists = versionRegex.test(existingContent);
  
  let updatedContent = existingContent;
  
  if (versionExists) {
    // Extract the existing entry for this version
    const existingEntryRegex = new RegExp(`(= ${version} =[\\s\\S]*?)(?== \\d|$)`);
    const existingEntryMatch = existingContent.match(existingEntryRegex);
    const existingEntry = existingEntryMatch ? existingEntryMatch[1].trim() : '';
    
    // Compare with new content to see if it's different
    if (existingEntry.trim() === newContent.trim()) {
      console.log(`readme.txt already contains up-to-date entries for v${version}. Skipping update.`);
    } else {
      // If different, replace the existing entry
      console.log(`Updating existing entries for v${version} in readme.txt...`);
      updatedContent = existingContent.replace(existingEntryRegex, newContent);
    }
  } else {
    // Update the changelog section with new content
    updatedContent = existingContent.replace(
      changelogMatch[0],
      `${changelogMatch[1]}${newContent}${changelogMatch[2].includes('=') ? changelogMatch[2].substring(changelogMatch[2].indexOf('=')) : ''}`
    );
    console.log(`Added new entries for v${version} to readme.txt`);
  }
  
  // Add upgrade notice if there are breaking changes
  const upgradeNotice = generateUpgradeNotice(groups, version);
  if (upgradeNotice) {
    // Check if upgrade notice section exists
    const upgradeNoticeMatch = updatedContent.match(/(== Upgrade Notice ==\n\n)([\s\S]*?)(?==|$)/);
    
    if (upgradeNoticeMatch) {
      // Check if this version already has an upgrade notice
      const versionNoticeRegex = new RegExp(`= ${version} =`);
      const versionNoticeExists = versionNoticeRegex.test(upgradeNoticeMatch[2]);
      
      if (versionNoticeExists) {
        // Replace existing upgrade notice for this version
        const existingNoticeRegex = new RegExp(`(= ${version} =[\\s\\S]*?)(?== \\d|$)`);
        updatedContent = updatedContent.replace(existingNoticeRegex, upgradeNotice);
      } else {
        // Add new upgrade notice at the beginning of the section
        updatedContent = updatedContent.replace(
          upgradeNoticeMatch[0],
          `${upgradeNoticeMatch[1]}${upgradeNotice}${upgradeNoticeMatch[2]}`
        );
      }
    } else {
      // Add upgrade notice section if it doesn't exist
      const upgradeNoticeSection = `== Upgrade Notice ==\n\n${upgradeNotice}`;
      
      // Find position to insert upgrade notice (before changelog)
      const changelogSectionPos = updatedContent.indexOf('== Changelog ==');
      if (changelogSectionPos !== -1) {
        updatedContent = 
          updatedContent.substring(0, changelogSectionPos) + 
          upgradeNoticeSection + 
          '\n\n' + 
          updatedContent.substring(changelogSectionPos);
      }
    }
    
    console.log(`Added upgrade notice for v${version} to readme.txt`);
  }
  
  fs.writeFileSync(readmePath, updatedContent);
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
 * Clean up duplicate entries in CHANGELOG.md and readme.txt
 */
function cleanupDuplicateEntries() {
  // Clean up CHANGELOG.md
  const changelogPath = path.join(process.cwd(), 'CHANGELOG.md');
  if (fs.existsSync(changelogPath)) {
    let content = fs.readFileSync(changelogPath, 'utf8');
    
    // Find all version headers
    const versionHeaders = content.match(/## v\d+\.\d+\.\d+ - \d{4}-\d{2}-\d{2}/g) || [];
    const uniqueVersions = new Set();
    const duplicateVersions = [];
    
    // Identify duplicate versions
    versionHeaders.forEach(header => {
      const version = header.match(/v(\d+\.\d+\.\d+)/)[1];
      if (uniqueVersions.has(version)) {
        duplicateVersions.push(version);
      } else {
        uniqueVersions.add(version);
      }
    });
    
    // Remove duplicate entries for each version
    duplicateVersions.forEach(version => {
      console.log(`Cleaning up duplicate entries for v${version} in CHANGELOG.md`);
      
      // Find all entries for this version
      const regex = new RegExp(`(## v${version} - \\d{4}-\\d{2}-\\d{2}[\\s\\S]*?)(?=## v|$)`, 'g');
      const matches = [...content.matchAll(regex)];
      
      if (matches.length > 1) {
        // Keep only the first entry
        const firstEntry = matches[0][0];
        
        // Remove all entries for this version
        content = content.replace(regex, '');
        
        // Add back the first entry after the header
        const headerMatch = content.match(/^([\s\S]*?)(?=##|$)/);
        const header = headerMatch ? headerMatch[1] : '';
        const rest = content.substring(header.length);
        
        content = header + firstEntry + rest;
      }
    });
    
    fs.writeFileSync(changelogPath, content);
  }
  
  // Clean up readme.txt
  const readmePath = path.join(process.cwd(), 'readme.txt');
  if (fs.existsSync(readmePath)) {
    let content = fs.readFileSync(readmePath, 'utf8');
    
    // Handle Changelog section
    const changelogSection = content.match(/== Changelog ==([\s\S]*?)(?=\n==|$)/);
    if (changelogSection) {
      const changelogContent = changelogSection[1];
      const changelogVersions = changelogContent.match(/= \d+\.\d+\.\d+ =/g) || [];
      const uniqueChangelogVersions = new Set();
      const duplicateChangelogVersions = [];
      
      // Identify duplicate versions within Changelog
      changelogVersions.forEach(header => {
        const version = header.match(/= (\d+\.\d+\.\d+) =/)[1];
        if (uniqueChangelogVersions.has(version)) {
          duplicateChangelogVersions.push(version);
        } else {
          uniqueChangelogVersions.add(version);
        }
      });
      
      // Remove duplicate entries within Changelog
      duplicateChangelogVersions.forEach(version => {
        console.log(`Cleaning up duplicate entries for v${version} in Changelog section`);
        
        const regex = new RegExp(`(= ${version} =[\\s\\S]*?)(?=\\n= \\d|$)`, 'g');
        const matches = [...changelogContent.matchAll(regex)];
        
        if (matches.length > 1) {
          // Keep only the first entry
          const firstEntry = matches[0][0];
          let updatedSection = changelogContent.replace(regex, '');
          updatedSection = '== Changelog ==\n\n' + firstEntry + updatedSection.replace('== Changelog ==\n\n', '');
          content = content.replace(/== Changelog ==[\s\S]*?(?=\n==|$)/, updatedSection);
        }
      });
    }
    
    // Handle Upgrade Notice section
    const upgradeSection = content.match(/== Upgrade Notice ==([\s\S]*?)(?=\n==|$)/);
    if (upgradeSection) {
      const upgradeContent = upgradeSection[1];
      const upgradeVersions = upgradeContent.match(/= \d+\.\d+\.\d+ =/g) || [];
      const uniqueUpgradeVersions = new Set();
      const duplicateUpgradeVersions = [];
      
      // Identify duplicate versions within Upgrade Notice
      upgradeVersions.forEach(header => {
        const version = header.match(/= (\d+\.\d+\.\d+) =/)[1];
        if (uniqueUpgradeVersions.has(version)) {
          duplicateUpgradeVersions.push(version);
        } else {
          uniqueUpgradeVersions.add(version);
        }
      });
      
      // Remove duplicate entries within Upgrade Notice
      duplicateUpgradeVersions.forEach(version => {
        console.log(`Cleaning up duplicate entries for v${version} in Upgrade Notice section`);
        
        const regex = new RegExp(`(= ${version} =[\\s\\S]*?)(?=\\n= \\d|$)`, 'g');
        const matches = [...upgradeContent.matchAll(regex)];
        
        if (matches.length > 1) {
          // Keep only the first entry
          const firstEntry = matches[0][0];
          let updatedSection = upgradeContent.replace(regex, '');
          updatedSection = '== Upgrade Notice ==\n\n' + firstEntry + updatedSection.replace('== Upgrade Notice ==\n\n', '');
          content = content.replace(/== Upgrade Notice ==[\s\S]*?(?=\n==|$)/, updatedSection);
        }
      });
    }
    
    fs.writeFileSync(readmePath, content);
  }
}

/**
 * Update both changelog files based on changesets
 */
function updateChangelogs() {
  try {
    // First, clean up any duplicate entries
    cleanupDuplicateEntries();
    
    const version = argv['new-version'] || getCurrentVersion();
    const changesets = readChangesets();
    
    if (changesets.length === 0) {
      console.log('No changesets found. Skipping changelog updates.');
      return;
    }
    
    const groupedChangesets = groupChangesetsByType(changesets);
    
    // Update GitHub changelog
    const githubChangelogContent = generateGitHubChangelogContent(groupedChangesets, version);
    updateGitHubChangelog(githubChangelogContent, version);
    
    // Update WordPress readme
    const wordpressChangelogContent = generateWordPressChangelogContent(groupedChangesets, version);
    const upgradeNotice = generateUpgradeNotice(groupedChangesets, version);
    updateWordPressReadme(wordpressChangelogContent, version, groupedChangesets);
    
    // Archive changesets
    archiveChangesets();
    
    console.log('Changelog updates complete!');
  } catch (err) {
    console.error('Error updating changelogs:', err);
    process.exit(1);
  }
}

// Run the script
updateChangelogs(); 