# Monorepo Release Scripts

This directory contains scripts that support the release process for the WPGraphQL monorepo.

## Scripts

### `hooks/generate-hook-docs.js`

Generates hook reference documentation for a plugin by scanning `do_action` and `apply_filters` callsites.

**Purpose**: Automates `actions` and `filters` docs pages, hook metadata index output, and `@hookGroup` linting.

**Hook lint checks** include:
- Missing/invalid `@hookGroup` tags (for `do_action` / `apply_filters` callsites)
- Missing hook descriptions in nearest docblock
- Missing/incomplete `@param` documentation relative to passed hook arguments
- Missing per-`@param` descriptions

**Usage**:
```bash
node scripts/hooks/generate-hook-docs.js --plugin=wp-graphql

# npm shortcut
npm run hooks:generate -- --plugin=wp-graphql
```

**Arguments**:
| Argument | Required | Description |
|----------|----------|-------------|
| `--plugin` | Yes | Plugin slug defined in `scripts/hooks/plugin-config.json` |
| `--validate-only` | No | If `true`, fails when generated output is stale |
| `--require-explicit-group` | No | If `true`, missing `@hookGroup` tags are errors |
| `--config` | No | Override plugin config file path |
| `--groups` | No | Override hook groups config file path |
| `--naming-rules` | No | Override hook naming rules file path |
| `--legacy-hooks` | No | Override legacy/deprecated hooks registry path |

**Generated output** (per plugin docs directory):
- `docs/generated/hooks-index.json`
- `docs/generated/hooks-lint.json`
- `docs/generated/hooks-lint.md`
- `docs/generated/hooks-naming-audit.json`
- `docs/generated/hooks-naming-audit.md`
- `docs/generated/hooks-deprecated.json`
- `docs/generated/hooks-deprecated.md`
- `docs/actions/index.md`
- `docs/actions/<hook>.md`
- `docs/filters/index.md`
- `docs/filters/<hook>.md`

**Deprecated/removed hook persistence**:
- The generator reads `scripts/hooks/legacy-hooks.json` to preserve documentation for deprecated hooks even after they are removed from source.
- Hook pages include lifecycle metadata (`status`, `deprecatedIn`, optional `removedIn`, `replacement`) when available.

**CI + release workflow integration**:
- **Release PR updates** (`.github/workflows/update-release-pr.yml`):
  - Replaces `x-release-please-version` in plugin PHP/readme files.
  - Regenerates hook docs for the releasing component by running `generate-hook-docs.js`.
  - Commits refreshed hook docs/index/lint artifacts back to the release PR branch.
- **Lint workflow** (`.github/workflows/lint.yml`):
  - Runs for plugin changes and release PRs.
  - Includes hook legacy coverage enforcement via `check-legacy-coverage.js`.

### `hooks/check-legacy-coverage.js`

Ensures hooks removed from the codebase are preserved in `scripts/hooks/legacy-hooks.json`.

**Purpose**: Prevent accidental loss of historical deprecated/removed hook documentation when callsites are deleted.

**Usage**:
```bash
node scripts/hooks/check-legacy-coverage.js --plugin=wp-graphql --base-ref=origin/main

# npm shortcut
npm run hooks:check-legacy -- --plugin=wp-graphql --base-ref=origin/main
```

**Arguments**:
| Argument | Required | Description |
|----------|----------|-------------|
| `--plugin` | No | Plugin slug (default: `wp-graphql`) |
| `--base-ref` | No | Git ref to compare against (default: auto-detected from CI context, then `origin/main`) |

**How it works**:
1. Reads current `plugins/<plugin>/docs/generated/hooks-index.json`
2. Reads baseline `hooks-index.json` from `--base-ref`
3. Detects WPGraphQL hooks that were removed from source callsites
4. Fails if removed hooks are missing or incomplete in `scripts/hooks/legacy-hooks.json`

**Important**:
- This check enforces that removed hooks remain documented historically.
- `legacy-hooks.json` entries should include lifecycle metadata (`status`, `deprecatedIn`, optional `removedIn`, `replacement`) and should also include `since` when known.
- `update-release-pr.yml` currently replaces placeholders inside the releasing plugin directory; if `legacy-hooks.json` still contains `x-release-please-version` for history-only hooks, update those values manually as part of the release PR.

**Naming audit behavior**:
- Naming audit focuses on WPGraphQL-specific hooks and excludes core hooks from naming violations.
- You can explicitly mark hook source in a hook docblock:
  - `@hookSource wpgraphql`
  - `@hookSource core`
- Prefix deprecation and migration guidance is driven by `scripts/hooks/naming-rules.json`.

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
| `--component` | Yes | Component name (e.g., `wp-graphql`, `wp-graphql-smart-cache`, `wp-graphql-acf`) |
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

# Run hook docs tests only
node scripts/hooks/generate-hook-docs.test.js
```

### Test File

Script tests include:
- `scripts/update-upgrade-notice.test.js`
- `scripts/update-version-constants.test.js`
- `scripts/hooks/generate-hook-docs.test.js`

`update-upgrade-notice.test.js` covers:

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
│ update-release-pr   │◄── Replaces placeholders in plugin files
│    workflow         │    Runs update-upgrade-notice.js
│                     │    Runs hooks/generate-hook-docs.js
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ lint.yml            │◄── Runs hooks/check-legacy-coverage.js
│ (PR + main)         │    for wp-graphql changes/release PRs
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
