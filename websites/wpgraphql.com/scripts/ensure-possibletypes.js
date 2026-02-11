#!/usr/bin/env node

/**
 * Ensures possibleTypes.json exists before build
 * This script creates an empty fallback if the file doesn't exist
 * or if generation fails
 */

const fs = require("fs")
const path = require("path")

const possibleTypesPath = path.join(__dirname, "..", "possibleTypes.json")

// Ensure the file exists with at least an empty object
if (!fs.existsSync(possibleTypesPath)) {
  console.log("⚠️  possibleTypes.json not found, creating empty fallback...")
  fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
  console.log("✅ Created empty possibleTypes.json fallback")
} else {
  // Verify the file is valid JSON
  try {
    const content = fs.readFileSync(possibleTypesPath, "utf-8")
    JSON.parse(content)
  } catch (error) {
    console.log("⚠️  possibleTypes.json is invalid, creating empty fallback...")
    fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
    console.log("✅ Replaced invalid possibleTypes.json with empty fallback")
  }
}
