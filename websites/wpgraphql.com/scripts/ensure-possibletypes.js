#!/usr/bin/env node

/**
 * Ensures possibleTypes.json exists before build
 * This script creates an empty fallback if the file doesn't exist
 * or if generation fails
 */

const fs = require("fs")
const path = require("path")

// Use __dirname to ensure we always create the file relative to this script's location
// This script is in websites/wpgraphql.com/scripts/, so we go up one level
// to get to websites/wpgraphql.com/ where faust.config.js is located
const projectRoot = path.join(__dirname, "..")
const possibleTypesPath = path.join(projectRoot, "possibleTypes.json")

// Also check if faust.config.js exists to verify we're in the right directory
const faustConfigPath = path.join(projectRoot, "faust.config.js")
if (!fs.existsSync(faustConfigPath)) {
  console.error(`[ensure-possibletypes] ERROR: faust.config.js not found at ${faustConfigPath}`)
  console.error(`[ensure-possibletypes] This script must be run from the wpgraphql.com directory`)
  process.exit(1)
}

// Log paths for debugging
console.log(`[ensure-possibletypes] Project root: ${projectRoot}`)
console.log(`[ensure-possibletypes] Looking for possibleTypes.json at: ${path.resolve(possibleTypesPath)}`)
console.log(`[ensure-possibletypes] Current working directory: ${process.cwd()}`)

try {
  // Check if file exists in the expected location (same directory as faust.config.js)
  if (!fs.existsSync(possibleTypesPath)) {
    console.log("⚠️  possibleTypes.json not found at expected location, creating empty fallback...")
    
    // Also check if it exists in the current working directory (in case faust generated it there)
    const cwdPath = path.join(process.cwd(), "possibleTypes.json")
    if (fs.existsSync(cwdPath) && process.cwd() !== projectRoot) {
      console.log(`⚠️  Found possibleTypes.json in cwd (${cwdPath}), moving to expected location...`)
      try {
        const content = fs.readFileSync(cwdPath, "utf-8")
        fs.writeFileSync(possibleTypesPath, content, "utf-8")
        console.log(`✅ Moved possibleTypes.json from cwd to expected location`)
      } catch (error) {
        console.log(`⚠️  Could not move file, creating empty fallback instead`)
        fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
      }
    } else {
      fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
      console.log(`✅ Created empty possibleTypes.json fallback at ${possibleTypesPath}`)
    }
  } else {
    // Verify the file is valid JSON
    try {
      const content = fs.readFileSync(possibleTypesPath, "utf-8")
      JSON.parse(content)
      console.log(`✅ possibleTypes.json exists and is valid at ${possibleTypesPath}`)
    } catch (error) {
      console.log("⚠️  possibleTypes.json is invalid, creating empty fallback...")
      fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
      console.log(`✅ Replaced invalid possibleTypes.json with empty fallback at ${possibleTypesPath}`)
    }
  }
} catch (error) {
  console.error("❌ Error ensuring possibleTypes.json exists:", error.message)
  process.exit(1)
}
