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
  .option('version', {
    type: 'string',
    description: 'Version number for the upgrade notice',
    demandOption: true
  })
  .option('notes-file', {
    type: 'string',
    description: 'Path to the release notes file to extract breaking changes from',
    default: 'release_notes_temp.md'
  })
  .help()
  .argv;

/**
 * Extract breaking changes from release notes
 * 
 * @param {string} notesPath Path to the release notes file
 * @returns {Array} Array of breaking changes
 */
function extractBreakingChanges(notesPath) {
  try {
    // Read the release notes file
    const notesContent = fs.readFileSync(notesPath, 'utf8');
    
    // Check if there are breaking changes
    if (!notesContent.includes('### ⚠️ BREAKING CHANGES')) {
      console.log('No breaking changes found in release notes.');
      return [];
    }
    
    // Extract the breaking changes section
    const breakingSection = notesContent.match(/### ⚠️ BREAKING CHANGES\n([\s\S]*?)(?=###|$)/);
    
    if (!breakingSection || !breakingSection[1]) {
      console.log('Breaking changes section found but no changes extracted.');
      return [];
    }
    
    // Extract each breaking change line
    const breakingChanges = [];
    const lines = breakingSection[1].trim().split('\n');
    
    for (const line of lines) {
      if (line.startsWith('- ')) {
        // Remove markdown formatting and PR references
        const cleanLine = line
          .replace(/^- /, '')
          .replace(/ \(\[#\d+\]\(.*?\)\)$/, '');
        
        breakingChanges.push(cleanLine);
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
 * @param {Array} breakingChanges Array of breaking changes
 */
function updateUpgradeNotice(version, breakingChanges) {
  try {
    const readmePath = path.join(process.cwd(), 'readme.txt');
    
    // Check if readme.txt exists
    if (!fs.existsSync(readmePath)) {
      console.error('readme.txt not found.');
      return;
    }
    
    // Read the readme.txt file
    let readmeContent = fs.readFileSync(readmePath, 'utf8');
    
    // Format the upgrade notice
    let upgradeNotice = `= ${version} =\n`;
    upgradeNotice += 'BREAKING CHANGES: This release contains breaking changes. Please review before upgrading.\n\n';
    
    // Add each breaking change
    for (const change of breakingChanges) {
      upgradeNotice += `* ${change}\n`;
    }
    
    // Check if the Upgrade Notice section exists
    if (readmeContent.includes('== Upgrade Notice ==')) {
      // If it exists, add the new notice after the section header
      readmeContent = readmeContent.replace(
        /== Upgrade Notice ==/,
        `== Upgrade Notice ==\n\n${upgradeNotice}`
      );
    } else {
      // If it doesn't exist, add the section at the end
      readmeContent += `\n\n== Upgrade Notice ==\n\n${upgradeNotice}`;
    }
    
    // Write the updated content back to readme.txt
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
    const { version, notesFile } = argv;
    
    // Extract breaking changes from release notes
    const breakingChanges = extractBreakingChanges(notesFile);
    
    // If there are breaking changes, update the upgrade notice
    if (breakingChanges.length > 0) {
      updateUpgradeNotice(version, breakingChanges);
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