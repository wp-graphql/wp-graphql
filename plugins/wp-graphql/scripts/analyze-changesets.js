#!/usr/bin/env node

/**
 * Script to analyze changesets and determine the appropriate version bump type
 * 
 * Usage:
 *   node scripts/analyze-changesets.js
 * 
 * Output:
 *   The script will output "major", "minor", or "patch" based on the changesets
 */

const fs = require('fs-extra');
const path = require('path');
const glob = require('glob');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const chalk = require('chalk');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('json', {
    type: 'boolean',
    description: 'Output as JSON',
    default: false
  })
  .option('verbose', {
    type: 'boolean',
    description: 'Show detailed analysis',
    default: false
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
    console.log('No .changesets directory found.');
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
      const changeset = {
        file
      };
      
      lines.forEach(line => {
        if (line.trim() === '' || line.startsWith('description:')) return;
        
        const [key, ...valueParts] = line.split(':');
        const value = valueParts.join(':').trim();
        
        // Remove quotes if present
        changeset[key.trim()] = value.replace(/^"(.*)"$/, '$1');
      });
      
      return changeset;
    } catch (err) {
      console.warn(`Error parsing changeset ${file}:`, err);
      return null;
    }
  }).filter(Boolean);
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
  
  // Check for "BREAKING CHANGE:" or "BREAKING-CHANGE:" in description
  if (changeset.description && 
      (changeset.description.includes('BREAKING CHANGE:') || 
       changeset.description.includes('BREAKING-CHANGE:'))) {
    return true;
  }
  
  return false;
}

/**
 * Analyze changesets to determine bump type
 * 
 * @param {Array} changesets Array of changeset objects
 * @returns {Object} Analysis result with bump type and details
 */
function analyzeChangesets(changesets) {
  if (changesets.length === 0) {
    return {
      bumpType: 'none',
      reason: 'No changesets found',
      breakingChanges: [],
      features: [],
      fixes: [],
      other: []
    };
  }
  
  const breakingChanges = changesets.filter(changeset => isBreakingChange(changeset));
  
  const features = changesets.filter(changeset => 
    changeset.type === 'feat' && !breakingChanges.includes(changeset)
  );
  
  const fixes = changesets.filter(changeset => 
    changeset.type === 'fix' && !breakingChanges.includes(changeset) && !features.includes(changeset)
  );
  
  const other = changesets.filter(changeset => 
    !breakingChanges.includes(changeset) && !features.includes(changeset) && !fixes.includes(changeset)
  );
  
  let bumpType = 'patch';
  let reason = 'Default to patch bump';
  
  if (breakingChanges.length > 0) {
    bumpType = 'major';
    reason = `Found ${breakingChanges.length} breaking change(s)`;
  } else if (features.length > 0) {
    bumpType = 'minor';
    reason = `Found ${features.length} feature(s)`;
  } else if (fixes.length > 0 || other.length > 0) {
    bumpType = 'patch';
    reason = `Found ${fixes.length} fix(es) and ${other.length} other change(s)`;
  }
  
  return {
    bumpType,
    reason,
    breakingChanges,
    features,
    fixes,
    other
  };
}

/**
 * Main function
 */
function main() {
  try {
    const changesets = readChangesets();
    const analysis = analyzeChangesets(changesets);
    
    if (argv.verbose) {
      console.log('Changeset Analysis:');
      console.log('-------------------');
      console.log(`Total changesets: ${changesets.length}`);
      console.log(`Breaking changes: ${analysis.breakingChanges.length}`);
      console.log(`Features: ${analysis.features.length}`);
      console.log(`Fixes: ${analysis.fixes.length}`);
      console.log(`Other: ${analysis.other.length}`);
      console.log(`\nRecommended bump type: ${analysis.bumpType}`);
      console.log(`Reason: ${analysis.reason}`);
      
      if (analysis.breakingChanges.length > 0) {
        console.log('\nBreaking Changes:');
        analysis.breakingChanges.forEach(change => {
          console.log(`- ${change.title} (${change.file})`);
        });
      }
      
      if (analysis.features.length > 0) {
        console.log('\nFeatures:');
        analysis.features.forEach(feature => {
          console.log(`- ${feature.title} (${feature.file})`);
        });
      }
    } else if (argv.json) {
      console.log(JSON.stringify({
        bumpType: analysis.bumpType,
        reason: analysis.reason,
        counts: {
          total: changesets.length,
          breakingChanges: analysis.breakingChanges.length,
          features: analysis.features.length,
          fixes: analysis.fixes.length,
          other: analysis.other.length
        }
      }));
    } else {
      // Simple output for scripting
      console.log(analysis.bumpType);
    }
  } catch (err) {
    console.error('Error analyzing changesets:', err);
    process.exit(1);
  }
}

// Run the script
main(); 