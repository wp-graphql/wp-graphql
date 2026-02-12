# Testing Release Scripts Locally

This guide shows you how to test the release scripts locally to verify they work correctly before they run in CI.

## Quick Start

### Run Automated Tests

```bash
# Run all script tests
npm run test:scripts

# Or run individual tests
node scripts/update-version-constants.test.js
node scripts/update-upgrade-notice.test.js
```

The `update-version-constants.test.js` test suite includes:

**Unit Tests (13 tests)** covering:
- ✅ `wp-graphql` (uses `constants.php` + `wp-graphql.php`)
- ✅ `wp-graphql-smart-cache` (uses `wp-graphql-smart-cache.php` for both)
- ✅ `wp-graphql-ide` (uses `wpgraphql-ide.php` for both)
- Edge cases, placeholders, error handling, etc.

**Integration Test (1 test)** that:
- ✅ Reads `.release-please-manifest.json` to discover all plugins
- ✅ Validates each plugin's file structure matches expected format
- ✅ Tests that the script can update version constants for all plugins
- ✅ Automatically tests any new plugins added to the manifest

This means **you don't need to manually add tests** when adding a new plugin - just ensure it's in the manifest and has the proper constant mapping in the script!

## Manual Testing with Git (Safest Method)

The safest way to test the scripts is to use git to create a temporary branch and test on real files, then discard the changes.

### Testing `update-version-constants.js`

1. **Create a test branch** (optional, but recommended):
   ```bash
   git checkout -b test-version-script
   ```

2. **Run the script on a real plugin**:
   ```bash
   # Test updating wp-graphql to version 2.9.0
   node scripts/update-version-constants.js \
     --version=2.9.0 \
     --component=wp-graphql \
     --plugin-dir=plugins/wp-graphql
   ```

3. **Verify the changes**:
   ```bash
   # Check what changed
   git diff plugins/wp-graphql/wp-graphql.php
   git diff plugins/wp-graphql/constants.php
   
   # Or view specific lines
   grep -n "Version:\|@version\|WPGRAPHQL_VERSION" \
     plugins/wp-graphql/wp-graphql.php \
     plugins/wp-graphql/constants.php
   ```

4. **Discard changes** (if you don't want to keep them):
   ```bash
   git checkout -- plugins/wp-graphql/wp-graphql.php plugins/wp-graphql/constants.php
   ```

5. **Or commit if the changes are correct**:
   ```bash
   git add plugins/wp-graphql/wp-graphql.php plugins/wp-graphql/constants.php
   git commit -m "test: verify version update script"
   ```

### Testing with Placeholders

To test the placeholder replacement (as release-please does):

1. **Temporarily add placeholders**:
   ```bash
   # Backup files first
   cp plugins/wp-graphql/wp-graphql.php plugins/wp-graphql/wp-graphql.php.bak
   cp plugins/wp-graphql/constants.php plugins/wp-graphql/constants.php.bak
   
   # Replace version with placeholder
   sed -i '' 's/2\.8\.0/x-release-please-version/g' \
     plugins/wp-graphql/wp-graphql.php \
     plugins/wp-graphql/constants.php
   ```

2. **Run the script**:
   ```bash
   node scripts/update-version-constants.js \
     --version=2.8.0 \
     --component=wp-graphql \
     --plugin-dir=plugins/wp-graphql
   ```

3. **Verify placeholders were replaced**:
   ```bash
   grep -n "x-release-please-version" \
     plugins/wp-graphql/wp-graphql.php \
     plugins/wp-graphql/constants.php
   # Should return nothing if replacement worked
   ```

4. **Restore from backup**:
   ```bash
   mv plugins/wp-graphql/wp-graphql.php.bak plugins/wp-graphql/wp-graphql.php
   mv plugins/wp-graphql/constants.php.bak plugins/wp-graphql/constants.php
   ```

## Testing Different Scenarios

### Test 1: Update from 2.7.0 to 2.8.0

```bash
# First, manually set version to 2.7.0
sed -i '' 's/2\.8\.0/2.7.0/g' \
  plugins/wp-graphql/wp-graphql.php \
  plugins/wp-graphql/constants.php

# Run the script
node scripts/update-version-constants.js \
  --version=2.8.0 \
  --component=wp-graphql \
  --plugin-dir=plugins/wp-graphql

# Verify
grep "Version:\|@version\|WPGRAPHQL_VERSION" \
  plugins/wp-graphql/wp-graphql.php \
  plugins/wp-graphql/constants.php
```

### Test 2: Verify No Changes When Version Matches

```bash
# Version is already 2.8.0, run script with same version
node scripts/update-version-constants.js \
  --version=2.8.0 \
  --component=wp-graphql \
  --plugin-dir=plugins/wp-graphql

# Should output: "already has version 2.8.0"
```

### Test 3: Test Error Handling

```bash
# Test with invalid version format
node scripts/update-version-constants.js \
  --version=invalid \
  --component=wp-graphql \
  --plugin-dir=plugins/wp-graphql
# Should error: "Invalid version format"

# Test with missing component
node scripts/update-version-constants.js \
  --version=2.8.0 \
  --component=invalid-plugin \
  --plugin-dir=plugins/wp-graphql
# Should warn: "No version constant mapping"
```

## Testing in a Clean Environment

If you want to test without affecting your working directory:

1. **Clone to a temporary directory**:
   ```bash
   cd /tmp
   git clone https://github.com/wp-graphql/wp-graphql.git wp-graphql-test
   cd wp-graphql-test
   ```

2. **Run your tests**:
   ```bash
   node scripts/update-version-constants.js \
     --version=2.9.0 \
     --component=wp-graphql \
     --plugin-dir=plugins/wp-graphql
   ```

3. **Clean up**:
   ```bash
   cd ..
   rm -rf wp-graphql-test
   ```

## What to Check

When testing, verify:

- ✅ Version constant (`WPGRAPHQL_VERSION`) is updated in `constants.php`
- ✅ Version header (`Version: X.Y.Z`) is updated in `wp-graphql.php`
- ✅ `@version` docblock is updated in `wp-graphql.php`
- ✅ Indentation (tabs/spaces) is preserved
- ✅ No other files are modified
- ✅ Script exits with success code (0) when successful
- ✅ Script reports errors clearly when something goes wrong
- ✅ Placeholders (`x-release-please-version`) are replaced correctly

## Integration Testing

To test the full workflow as it would run in CI:

1. **Simulate a Release PR**:
   ```bash
   # Create a test branch
   git checkout -b release-please--branches--main--components--wp-graphql
   
   # Add placeholders (as release-please would)
   sed -i '' 's/2\.8\.0/x-release-please-version/g' \
     plugins/wp-graphql/wp-graphql.php \
     plugins/wp-graphql/constants.php
   
   # Run the script (as update-release-pr.yml would)
   node scripts/update-version-constants.js \
     --version=2.8.0 \
     --component=wp-graphql \
     --plugin-dir=plugins/wp-graphql
   
   # Verify results
   git diff
   ```

## Troubleshooting

### Script doesn't update files

- Check that you're running from the repo root
- Verify the `--plugin-dir` path is correct
- Check file permissions (script needs write access)

### Version not found

- Ensure the version pattern matches exactly (e.g., `2.8.0` not `2.8`)
- Check that the file contains the expected version format
- Look for typos in version numbers

### Permission errors

- Make sure files aren't read-only
- Check that you have write permissions to the plugin directory

## See Also

- [Scripts README](./README.md) - Overview of all release scripts
- [Automated Tests](./update-version-constants.test.js) - Unit tests for the script
- [CI Workflow](../.github/workflows/test-scripts.yml) - How tests run in CI
