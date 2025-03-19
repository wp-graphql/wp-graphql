#!/usr/bin/env node

/**
 * Script to bump version numbers across files
 * 
 * Usage:
 *   node scripts/bump-version.js --type=patch
 *   node scripts/bump-version.js --type=minor
 *   node scripts/bump-version.js --type=major
 *   node scripts/bump-version.js --version=1.2.3
 *   node scripts/bump-version.js (auto-detects type based on changesets)
 * 
 * Options:
 *   --type     Type of version bump (patch, minor, major)
 *   --version  Specific version to set
 */

const fs = require('fs-extra');
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const glob = require('glob');
const { execSync } = require('child_process');
const chalk = require('chalk');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('type', {
    type: 'string',
    description: 'Type of version bump (patch, minor, major)',
    choices: ['patch', 'minor', 'major']
  })
  .option('version', {
    type: 'string',
    description: 'Specific version to set',
    conflicts: 'type'
  })
  .check(argv => {
    if (argv.version && !/^\d+\.\d+\.\d+$/.test(argv.version)) {
      throw new Error('Version must be in format x.y.z');
    }
    return true;
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
  const match = contents.match(/define\('WPGRAPHQL_VERSION', '([^']+)'\)/);
  
  if (!match) {
    throw new Error('Could not find version in constants.php');
  }
  
  return match[1];
}

/**
 * Calculate new version based on current version and bump type
 * 
 * @param {string} currentVersion Current version
 * @param {string} bumpType Type of version bump (patch, minor, major)
 * @returns {string} New version
 */
function calculateNewVersion(currentVersion, bumpType) {
  const [major, minor, patch] = currentVersion.split('.').map(Number);
  
  switch (bumpType) {
    case 'major':
      return `${major + 1}.0.0`;
    case 'minor':
      return `${major}.${minor + 1}.0`;
    case 'patch':
      return `${major}.${minor}.${patch + 1}`;
    default:
      return currentVersion;
  }
}

/**
 * Determine bump type by analyzing changesets
 * 
 * @returns {string} Bump type (major, minor, patch, none)
 */
function determineBumpType() {
  try {
    // Run the analyze-changesets script and capture its output
    const bumpType = execSync('node scripts/analyze-changesets.js', { encoding: 'utf8' }).trim();
    
    if (bumpType === 'none') {
      console.log('No changesets found. Defaulting to patch bump.');
      return 'patch';
    }
    
    console.log(`Determined bump type from changesets: ${bumpType}`);
    return bumpType;
  } catch (err) {
    console.warn('Error analyzing changesets:', err.message);
    console.log('Defaulting to patch bump.');
    return 'patch';
  }
}

/**
 * Update version in constants.php
 * 
 * @param {string} newVersion New version
 */
function updateConstantsFile(newVersion) {
  const filePath = path.join(process.cwd(), 'constants.php');
  let content = fs.readFileSync(filePath, 'utf8');
  
  content = content.replace(
    /define\('WPGRAPHQL_VERSION', '[^']+'\)/,
    `define('WPGRAPHQL_VERSION', '${newVersion}')`
  );
  
  fs.writeFileSync(filePath, content);
  console.log(`Updated version in constants.php to ${newVersion}`);
}

/**
 * Update version in wp-graphql.php
 * 
 * @param {string} newVersion New version
 */
function updatePluginFile(newVersion) {
  const filePath = path.join(process.cwd(), 'wp-graphql.php');
  let content = fs.readFileSync(filePath, 'utf8');
  
  // Update both Version: in the plugin header and @version in the docblock
  content = content.replace(
    /Version: .+/,
    `Version: ${newVersion}`
  ).replace(
    /@version\s+.+/,
    `@version  ${newVersion}`
  );
  
  fs.writeFileSync(filePath, content);
  console.log(`Updated version in wp-graphql.php to ${newVersion}`);
}

/**
 * Update version in package.json
 * 
 * @param {string} newVersion New version
 */
function updatePackageJson(newVersion) {
  const filePath = path.join(process.cwd(), 'package.json');
  
  if (fs.existsSync(filePath)) {
    const packageJson = require(filePath);
    packageJson.version = newVersion;
    fs.writeFileSync(filePath, JSON.stringify(packageJson, null, 2) + '\n');
    console.log(`Updated version in package.json to ${newVersion}`);
  }
}

/**
 * Update version in readme.txt
 * 
 * @param {string} newVersion New version
 */
function updateReadmeTxt(newVersion) {
  const filePath = path.join(process.cwd(), 'readme.txt');
  
  if (fs.existsSync(filePath)) {
    let content = fs.readFileSync(filePath, 'utf8');
    
    content = content.replace(
      /Stable tag: .+/,
      `Stable tag: ${newVersion}`
    );
    
    fs.writeFileSync(filePath, content);
    console.log(`Updated version in readme.txt to ${newVersion}`);
  }
}

/**
 * Bump version numbers across files
 */
function bumpVersion() {
  try {
    const currentVersion = getCurrentVersion();
    
    // Determine the new version
    let newVersion;
    if (argv.version) {
      newVersion = argv.version;
    } else if (argv.type) {
      newVersion = calculateNewVersion(currentVersion, argv.type);
    } else {
      // Auto-detect bump type from changesets
      const bumpType = determineBumpType();
      newVersion = calculateNewVersion(currentVersion, bumpType);
    }
    
    console.log(`Bumping version from ${currentVersion} to ${newVersion}`);
    
    // Update all version references
    updateConstantsFile(newVersion);
    updatePluginFile(newVersion);
    updatePackageJson(newVersion);
    updateReadmeTxt(newVersion);
    
    console.log(chalk.green('✓ Version bump complete!'));
    
    // Output the new version for use in GitHub Actions
    console.log(`::set-output name=version::${newVersion}`);
  } catch (err) {
    console.error(chalk.red('Error bumping version:'), err);
    process.exit(1);
  }
}

// Run the script
bumpVersion(); 