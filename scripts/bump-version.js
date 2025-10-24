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
const glob = require('glob');
const { execSync } = require('child_process');
const chalk = require('chalk');

// Use async function to allow dynamic imports
(async () => {
  // Dynamically import yargs as an ES module
  const yargs = await import('yargs');
  const { hideBin } = await import('yargs/helpers');
  
  // Parse command line arguments
  const argv = yargs.default(hideBin(process.argv))
    .option('type', {
      type: 'string',
      description: 'Type of version bump (patch, minor, major)',
      choices: ['patch', 'minor', 'major']
    })
    .option('new-version', {
      type: 'string',
      description: 'Specific version to set',
      conflicts: 'type'
    })
    .version(false)
    .check(argv => {
      if (argv['new-version'] && !/^\d+\.\d+\.\d+$/.test(argv['new-version'])) {
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
    
    if (!fs.existsSync(constantsPath)) {
      console.log(chalk.yellow('Warning: constants.php not found, falling back to package.json'));
      const packageJson = require(path.join(process.cwd(), 'package.json'));
      return packageJson.version;
    }
    
    const contents = fs.readFileSync(constantsPath, 'utf8');
    
    // Try to find the WPGRAPHQL_VERSION constant
    const wpGraphQLMatch = contents.match(/define\(\s*'WPGRAPHQL_VERSION',\s*'([^']+)'\s*\)/);
    
    if (wpGraphQLMatch) {
      return wpGraphQLMatch[1];
    }
    
    // If no version found in constants.php, try package.json
    console.log(chalk.yellow('Warning: No version constant found in constants.php, falling back to package.json'));
    const packageJson = require(path.join(process.cwd(), 'package.json'));
    return packageJson.version;
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
    
    if (!fs.existsSync(filePath)) {
      console.log(chalk.yellow('Warning: constants.php not found, skipping update'));
      return;
    }
    
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Update WPGRAPHQL_VERSION with WordPress coding standards formatting
    if (content.includes('WPGRAPHQL_VERSION')) {
      content = content.replace(
        /define\(\s*'WPGRAPHQL_VERSION',\s*'[^']+'\s*\)/,
        `define( 'WPGRAPHQL_VERSION', '${newVersion}' )`
      );
      fs.writeFileSync(filePath, content);
      console.log(`Updated version in constants.php to ${newVersion}`);
    } else {
      console.log(chalk.yellow('Warning: WPGRAPHQL_VERSION constant not found in constants.php'));
    }
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
      if (argv['new-version']) {
        newVersion = argv['new-version'];
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
      
      // Use new GitHub Actions output syntax
      const outputFile = process.env.GITHUB_OUTPUT;
      if (outputFile) {
        fs.appendFileSync(outputFile, `version=${newVersion}\n`);
      }
    } catch (err) {
      console.error(chalk.red('Error bumping version:'), err);
      process.exit(1);
    }
  }

  // Run the script
  bumpVersion();
})().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
