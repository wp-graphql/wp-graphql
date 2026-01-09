#!/usr/bin/env node

/**
 * Script to update the upgrade notice section in readme.txt based on breaking changes
 * 
 * Usage:
 *   node scripts/update-upgrade-notice.js --version=1.0.0 --notes-file=release_notes.md
 * 
 * Options:
 *   --version     Version number for the upgrade notice
 *   --notes-file  Path to the release notes file to extract breaking changes from
 */

const fs = require('fs-extra');
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('new-version', {
    type: 'string',
    description: 'Version to check for breaking changes',
    demandOption: true
  })
  .option('notes-file', {
    type: 'string',
    description: 'Path to release notes file',
    demandOption: true
  })
  .version(false)
  .help()
  .argv;

/**
 * Extract breaking changes from release notes
 * 
 * @param {string} notesPath Path to the release notes file
 * @returns {Array} Array of breaking changes with PR links
 */
function extractBreakingChanges(notesPath) {
  try {
    const notesContent = fs.readFileSync(notesPath, 'utf8');
    
    // Check if there are breaking changes
    if (!notesContent.includes('### Breaking Changes') && !notesContent.includes('### ⚠️ BREAKING CHANGES')) {
      console.log('No breaking changes found in release notes.');
      return [];
    }
    
    // Extract the breaking changes section
    const breakingSection = notesContent.match(/### (?:⚠️ )?BREAKING CHANGES\n([\s\S]*?)(?=###|$)/);
    
    if (!breakingSection || !breakingSection[1]) {
      console.log('Breaking changes section found but no changes extracted.');
      return [];
    }
    
    // Extract each breaking change line with PR links
    const breakingChanges = [];
    const lines = breakingSection[1].trim().split('\n');
    
    for (const line of lines) {
      if (line.startsWith('- ')) {
        // Extract PR number if it exists
        const prMatch = line.match(/\[#(\d+)\]\(.*?\)/);
        const prNumber = prMatch ? prMatch[1] : null;
        
        // Remove markdown formatting but keep PR reference
        const cleanLine = line
          .replace(/^- /, '')
          .replace(/\[#\d+\]\(.*?\)/, prNumber ? `[#${prNumber}](https://github.com/wp-graphql/wp-graphql/pull/${prNumber})` : '');
        
        breakingChanges.push({ text: cleanLine, pr: prNumber });
      }
    }
    
    console.log(`Extracted ${breakingChanges.length} breaking changes from release notes.`);
    return breakingChanges;
  } catch (err) {
    console.error('Error extracting breaking changes:', err);
    return [];
  }
}

/**
 * Update the upgrade notice section in readme.txt
 * 
 * @param {string} version Version number
 * @param {Array} breakingChanges Array of breaking changes with PR links
 */
function updateUpgradeNotice(version, breakingChanges) {
  try {
    const readmePath = path.join(process.cwd(), 'readme.txt');
    
    if (!fs.existsSync(readmePath)) {
      console.error('readme.txt not found.');
      return;
    }
    
    let readmeContent = fs.readFileSync(readmePath, 'utf8');
    
    // Format the upgrade notice
    let upgradeNotice = `= ${version} =\n\n`;
    upgradeNotice += '**BREAKING CHANGES**: This release contains breaking changes that may require updates to your code.\n\n';
    
    // Add each breaking change with PR link
    breakingChanges.forEach(({ text, pr }) => {
      upgradeNotice += `* ${text}${pr ? ` ([#${pr}](https://github.com/wp-graphql/wp-graphql/pull/${pr}))` : ''}\n`;
    });
    
    upgradeNotice += '\nPlease review these changes before upgrading.\n';
    
    // Find the upgrade notice section
    const upgradeNoticeMatch = readmeContent.match(/(== Upgrade Notice ==\n\n)([\s\S]*?)(?=\n==|$)/);
    
    if (upgradeNoticeMatch) {
      // Check if this version's notice already exists
      const versionNoticeRegex = new RegExp(`= ${version} =([\\s\\S]*?)(?=\\n= |$)`);
      const hasVersionNotice = versionNoticeRegex.test(upgradeNoticeMatch[2]);
      
      if (!hasVersionNotice) {
        // Add new notice at the top, preserving existing notices
        const updatedNotices = `${upgradeNotice}\n\n${upgradeNoticeMatch[2]}`;
        readmeContent = readmeContent.replace(
          /(== Upgrade Notice ==\n\n)([\s\S]*?)(?=\n==|$)/,
          `$1${updatedNotices}`
        );
      }
    } else {
      // Add upgrade notice section before changelog
      const upgradeNoticeSection = `\n\n== Upgrade Notice ==\n\n${upgradeNotice}`;
      readmeContent = readmeContent.replace(
        /(== Changelog ==)/,
        `${upgradeNoticeSection}\n\n$1`
      );
    }
    
    fs.writeFileSync(readmePath, readmeContent);
    console.log(`Updated upgrade notice in readme.txt for version ${version}.`);
  } catch (err) {
    console.error('Error updating upgrade notice:', err);
  }
}

/**
 * Main function
 */
function main() {
  try {
    const { newVersion, notesFile } = argv;
    
    // Extract breaking changes from release notes
    const breakingChanges = extractBreakingChanges(notesFile);
    
    // If there are breaking changes, update the upgrade notice
    if (breakingChanges.length > 0) {
      updateUpgradeNotice(newVersion, breakingChanges);
    } else {
      console.log('No breaking changes to add to upgrade notice.');
    }
  } catch (err) {
    console.error('Error updating upgrade notice:', err);
    process.exit(1);
  }
}

// Run the script
main(); 