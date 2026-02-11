# Monorepo Release Scripts

This directory contains scripts that support the release process for the WPGraphQL monorepo.

## Scripts

### `update-upgrade-notice.js`

Updates the Upgrade Notice section in `readme.txt` when there are breaking changes in a release.

**Purpose**: WordPress.org displays upgrade notices to users before they update a plugin. This script ensures users are warned about breaking changes.

**Usage**:
```bash
node scripts/update-upgrade-notice.js --version=X.Y.Z --plugin-dir=plugins/wp-graphql
```

**Arguments**:
| Argument | Required | Description |
|----------|----------|-------------|
| `--version` | Yes | The version to check for breaking changes |
| `--plugin-dir` | No | Path to plugin directory (default: `plugins/wp-graphql`) |

**How it works**:
1. Reads `CHANGELOG.md` in the specified plugin directory
2. Extracts breaking changes for the specified version (looks for `### ⚠ BREAKING CHANGES` section)
3. If breaking changes exist, updates the `== Upgrade Notice ==` section in `readme.txt`
4. Preserves PR links and scope prefixes (e.g., `**resolver:**`)

**Example output in readme.txt**:
```
== Upgrade Notice ==

= 3.0.0 =

**⚠️ BREAKING CHANGES**: This release contains breaking changes that may require updates to your code.

* Remove deprecated `singlePost` query in favor of `post` query ([#1234](https://github.com/wp-graphql/wp-graphql/pull/1234))
* Change default pagination limit from 100 to 50 items ([#1235](https://github.com/wp-graphql/wp-graphql/pull/1235))

Please review these changes before upgrading.
```

### `update-version-constants.js`

Updates version constants and Version headers in plugin files during the release PR update process.

**Purpose**: Ensures that version numbers in PHP constants and plugin headers are synchronized when release-please creates a Release PR.

**Usage**:
```bash
node scripts/update-version-constants.js --version=X.Y.Z --component=wp-graphql --plugin-dir=plugins/wp-graphql
```

**Arguments**:
| Argument | Required | Description |
|----------|----------|-------------|
| `--version` | Yes | The version number to set |
| `--component` | Yes | Component name (e.g., `wp-graphql`, `wp-graphql-smart-cache`) |
| `--plugin-dir` | Yes | Path to plugin directory (relative to repo root) |

**How it works**:
1. Reads `release-please-config.json` to get the `constantMap` for the component
2. Updates the version constant (e.g., `WPGRAPHQL_VERSION`) in the specified file
3. Updates the `Version:` header in the main plugin file
4. Updates the `@version` docblock tag if present

**Configuration**:

Version constant mappings are configured in `release-please-config.json` under each plugin's `constantMap` key:

```json
"plugins/your-plugin-name": {
  "component": "your-plugin-name",
  "constantMap": {
    "constantName": "YOUR_PLUGIN_VERSION",
    "fileName": "your-plugin-name.php",
    "mainPluginFile": "your-plugin-name.php"
  }
}
```

**Configuration fields**:
- `constantName`: The PHP constant name (e.g., `YOUR_PLUGIN_VERSION`)
- `fileName`: The file containing the constant definition
- `mainPluginFile`: The main plugin file with the `Version:` header

**Note**: When adding a new plugin, simply add the `constantMap` to `release-please-config.json`. No code changes to the script are needed!

## Testing

### Running Tests Locally

```bash
# Run from repo root
npm run test:scripts
```

### Test File

Tests are located in `update-upgrade-notice.test.js` and cover:

| Test Case | Description |
|-----------|-------------|
| Standard format | Extracts breaking changes from release-please CHANGELOG format |
| No breaking changes | Handles versions without breaking changes gracefully |
| Version not found | Handles missing version in changelog |
| Update existing | Updates existing upgrade notice without duplicating |
| Alternative headers | Handles `### BREAKING CHANGES` (without emoji) |
| Version formats | Handles both `## [1.0.0]` and `## 1.0.0` formats |
| Multiple versions | Extracts changes for correct version only |
| Scope prefixes | Preserves `**scope:**` prefixes in changes |

### CI Testing

Tests run automatically via `.github/workflows/test-scripts.yml`:
- On push/PR when `scripts/**` changes
- Monthly (1st of each month)
- Manually via workflow_dispatch

## Integration with Release Process

These scripts are called by GitHub Actions workflows during the release process:

```
┌─────────────────────┐
│  PR Merged to main  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│   release-please    │
│  creates Release PR │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ update-release-pr   │◄── Runs update-upgrade-notice.js
│    workflow         │    if breaking changes detected
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Release PR Merged  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Deploy to WP.org    │
└─────────────────────┘
```

## Adding New Scripts

When adding new release scripts:

1. Place the script in this directory
2. Add tests in a corresponding `.test.js` file
3. Update `package.json` if a new npm script is needed
4. Document the script in this README
5. Update `.github/workflows/test-scripts.yml` if needed
