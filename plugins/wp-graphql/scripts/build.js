#!/usr/bin/env node

/**
 * Script to build a zip file of the plugin for distribution
 * 
 * Usage:
 *   node scripts/build.js
 *   node scripts/build.js --output=my-plugin.zip
 * 
 * Options:
 *   --output   Output filename (defaults to automation-tests.zip)
 */

const fs = require('fs-extra');
const path = require('path');
const archiver = require('archiver');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const glob = require('glob');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('output', {
    type: 'string',
    description: 'Output filename',
    default: 'automation-tests.zip'
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
 * Create a clean build directory
 * 
 * @returns {string} Path to the build directory
 */
function createBuildDirectory() {
  const buildDir = path.join(process.cwd(), 'build');
  
  // Clean up existing build directory
  if (fs.existsSync(buildDir)) {
    fs.removeSync(buildDir);
  }
  
  // Create new build directory
  fs.mkdirSync(buildDir, { recursive: true });
  
  return buildDir;
}

/**
 * Copy plugin files to build directory
 * 
 * @param {string} buildDir Path to the build directory
 */
function copyPluginFiles(buildDir) {
  // Files and directories to exclude from the build
  const excludes = [
    '.git',
    '.github',
    '.gitignore',
    'node_modules',
    'build',
    '.changesets',
    'scripts',
    'tests',
    '*.zip'
  ];
  
  // Get all files in the project directory
  const files = glob.sync('**/*', {
    cwd: process.cwd(),
    dot: true,
    nodir: true,
    ignore: excludes
  });
  
  // Copy each file to the build directory
  files.forEach(file => {
    const sourcePath = path.join(process.cwd(), file);
    const destPath = path.join(buildDir, file);
    
    // Create directory if it doesn't exist
    fs.mkdirSync(path.dirname(destPath), { recursive: true });
    
    // Copy file
    fs.copyFileSync(sourcePath, destPath);
    console.log(`Copied ${file}`);
  });
}

/**
 * Create a zip file from the build directory
 * 
 * @param {string} buildDir Path to the build directory
 * @param {string} outputFile Output filename
 * @returns {Promise} Promise that resolves when the zip file is created
 */
function createZipFile(buildDir, outputFile) {
  return new Promise((resolve, reject) => {
    const output = fs.createWriteStream(outputFile);
    const archive = archiver('zip', {
      zlib: { level: 9 } // Maximum compression
    });
    
    // Listen for all archive data to be written
    output.on('close', () => {
      console.log(`Created ${outputFile} (${archive.pointer()} bytes)`);
      resolve();
    });
    
    // Listen for warnings
    archive.on('warning', (err) => {
      if (err.code === 'ENOENT') {
        console.warn('Warning:', err);
      } else {
        reject(err);
      }
    });
    
    // Listen for errors
    archive.on('error', (err) => {
      reject(err);
    });
    
    // Pipe archive data to the file
    archive.pipe(output);
    
    // Add the build directory contents to the zip
    archive.directory(buildDir, false);
    
    // Finalize the archive
    archive.finalize();
  });
}

/**
 * Build the plugin zip file
 */
async function buildPlugin() {
  try {
    const version = getCurrentVersion();
    console.log(`Building plugin version ${version}`);
    
    // Create build directory
    const buildDir = createBuildDirectory();
    console.log(`Created build directory: ${buildDir}`);
    
    // Copy plugin files
    copyPluginFiles(buildDir);
    
    // Create zip file
    const outputFile = argv.output;
    await createZipFile(buildDir, outputFile);
    
    console.log('Build complete!');
  } catch (err) {
    console.error('Error building plugin:', err);
    process.exit(1);
  }
}

// Run the script
buildPlugin(); 