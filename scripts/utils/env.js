/**
 * Environment variable utilities
 * 
 * This module provides utilities for loading and accessing environment variables
 * in a consistent way across all scripts, supporting both local development
 * with .env files and CI/CD environments with secrets.
 */

const fs = require('fs-extra');
const path = require('path');

// Track if we've already loaded environment variables
let envLoaded = false;

/**
 * Load environment variables from .env files
 * Only runs once per process
 */
function loadEnv() {
  // Skip if already loaded or in GitHub Actions
  if (envLoaded || process.env.GITHUB_ACTIONS) {
    return;
  }

  try {
    // Only try to load .env if the package is available
    try {
      const dotenv = require('dotenv');
      const cwd = process.cwd();
      
      // Define possible env file names in order of priority
      const envFiles = [
        '.env.local',     // Local overrides
        '.env.development', // Development environment
        '.env'            // Default
      ];
      
      let loaded = false;
      
      // Try each env file in order
      for (const envFile of envFiles) {
        const envPath = path.join(cwd, envFile);
        
        if (fs.existsSync(envPath)) {
          const result = dotenv.config({ path: envPath });
          if (result.error) {
            console.warn(`Warning: Error loading ${envFile}:`, result.error.message);
          } else {
            console.log(`Loaded environment variables from ${envFile}`);
            loaded = true;
            break; // Stop after first successful load
          }
        }
      }
      
      if (!loaded) {
        console.log('No .env files found, using existing environment variables');
      }
    } catch (moduleError) {
      console.log('dotenv module not found, skipping .env file loading');
    }
    
    // Mark as loaded to prevent duplicate loading
    envLoaded = true;
  } catch (error) {
    console.warn('Warning: Could not process environment setup', error.message);
  }
}

/**
 * Get an environment variable with an optional default value
 * 
 * @param {string} name - The name of the environment variable
 * @param {string} defaultValue - Default value if the variable is not set
 * @returns {string} The value of the environment variable or the default value
 */
function getEnvVar(name, defaultValue = '') {
  // Load environment variables if not already loaded
  loadEnv();
  
  return process.env[name] || defaultValue;
}

/**
 * Get a boolean environment variable
 * 
 * @param {string} name - The name of the environment variable
 * @param {boolean} defaultValue - Default value if the variable is not set
 * @returns {boolean} The boolean value of the environment variable
 */
function getBoolEnvVar(name, defaultValue = false) {
  const value = getEnvVar(name, '').toLowerCase();
  
  if (value === '') {
    return defaultValue;
  }
  
  return value === 'true' || value === '1' || value === 'yes';
}

/**
 * Get a numeric environment variable
 * 
 * @param {string} name - The name of the environment variable
 * @param {number} defaultValue - Default value if the variable is not set or invalid
 * @returns {number} The numeric value of the environment variable
 */
function getNumEnvVar(name, defaultValue = 0) {
  const value = getEnvVar(name, '');
  
  if (value === '') {
    return defaultValue;
  }
  
  const num = Number(value);
  return isNaN(num) ? defaultValue : num;
}

// Export the utility functions
module.exports = {
  loadEnv,
  getEnvVar,
  getBoolEnvVar,
  getNumEnvVar
}; 