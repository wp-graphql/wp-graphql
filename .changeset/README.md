# Changesets

This directory contains "changesets" which help us manage changes and document them correctly.

## Automated Changeset Generation

Changesets are automatically generated when PRs are labeled with `ready-for-changeset` based on:

1. PR Title: Must follow conventional commit standards

   - `feat:` = minor version bump
   - `fix:` = patch version bump
   - Optional scopes can be used: `feat(graphiql):`, `fix(core):`
   - Breaking changes can be indicated by:
     - Adding an exclamation mark: `feat!:` or `fix!:`
     - Note: Any valid scope can be used (e.g., `feat(graphiql):`), but breaking changes should use the `!` suffix

2. PR Description Sections:
   - Breaking Changes: Used to identify major version bumps
     - Note: Template placeholders and HTML comments are automatically ignored
     - Only actual breaking change content will trigger a major version bump
   - Upgrade Instructions: Added to changelog for breaking changes
   - Description: Used as changelog entry

## How Changesets Work in WPGraphQL

Changesets are automatically generated when PRs are labeled to track changes and automate releases. The process works as follows:

1. Contributors submit PRs following our conventional commit standards
2. GitHub Actions automate the process:
   - Validates PR format and content
   - Generates and commits changesets
   - Creates a collection PR titled "release: next version 📦" (or "release: next beta version 📦" for beta releases)
   - When merged, triggers version updates and deployment

For detailed information about the automation process, see [GitHub Workflows](../.github/workflows/README.md).

## What is a changeset?

A changeset is a file that describes a change to the codebase. It includes:

1. The type of change (major, minor, patch)
2. The PR number
3. Whether it's a breaking change
4. A description of the change

## Format

Changesets are markdown files with YAML frontmatter. The frontmatter should include:

```md
---
"wp-graphql": major|minor|patch
pr: 123
breaking: true|false
contributorUsername: "githubusername"
newContributor: true|false
---

### feat: Title of the change

[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)

#### Description

Description of the change

#### Breaking Changes

Description of breaking changes (if any)

#### Upgrade Instructions

Instructions for upgrading (if needed)
```

## How are changesets used?

When a PR is merged, a changeset is automatically generated based on the PR title and description.

These changesets are then used to:

1. Determine the next version number
2. Generate the changelog
3. Update @since tags in the code
4. Identify and credit new contributors

### New Contributor Detection

The changeset generation process automatically:

1. Identifies the GitHub username of the PR author
2. Checks if this is their first contribution to the project
3. Adds this information to the changeset metadata
4. Includes a "New Contributors" section in the release notes

This helps recognize and welcome first-time contributors to the project.

## Manual creation

Changesets are typically created automatically, but you can create one manually by running:

```
npm run changeset
```

This will prompt you for the necessary information and create a changeset file.

## Skipping Releases

There are times when you might want to commit directly to master without triggering a release. You can do this in two ways:

1. **Use the `[skip release]` tag in your commit message**:

   ```
   git commit -m "fix: update documentation [skip release]"
   ```

   This will prevent the release workflow from running.

2. **Use the `[skip ci]` tag to skip all workflows**:
   ```
   git commit -m "chore: update config [skip ci]"
   ```
   This will skip all GitHub Actions workflows.

Note that direct pushes to the `master` branch will not trigger the release workflow by default, but they will still trigger the sync workflow to keep `develop` in sync with `master`.

## How are Changesets Used?

### Release Process

> **Note**: All releases are deployed to WordPress.org, with different handling for stable vs beta releases.

```mermaid
flowchart TD
    %% PR and Changeset Process
    PR[PR Merged] --> RL[Add ready-for-changeset label]
    RL --> GC[Generate Changeset]
    GC --> ST[Scan @since todo tags]
    ST --> CPR[Create "release: next version 📦" PR]
    CPR --> |Merged to develop| DEV[develop branch]
    DEV --> |Auto-merge to master| M[master branch]

    %% Standard Release Flow
    subgraph "Standard Release"
        M --> VB[Version Bump]
        VB --> SV[Sync Versions<br/>package.json<br/>wp-graphql.php<br/>constants.php]
        SV --> US[Update @since tags]
        US --> CL[Generate Changelogs]

        %% Changelog Generation
        CL --> MD[CHANGELOG.md<br/>Developer Format]
        CL --> RT[readme.txt<br/>WordPress.org Format]

        MD & RT --> GR[Create GitHub Release]
        GR --> WO[Deploy to WordPress.org<br/>Update Stable Tag]
    end

    %% Beta Release Flow
    subgraph "Beta Release"
        PR2[PR to next-major] --> RL2[Add ready-for-changeset label]
        RL2 --> GC2[Generate Changeset]
        GC2 --> ST2[Scan @since todo tags]
        ST2 --> CPR2[Create "release: next beta version 📦" PR]
        CPR2 --> |Merged to next-major| NM[next-major branch]
        NM --> BV[Version Bump with Beta]
        BV --> BSV[Sync Versions<br/>Keep Stable Tag]
        BSV --> BCL[Generate Changelogs]
        BCL --> BGR[Create GitHub Pre-release]
        BGR --> BWO[Deploy to WordPress.org<br/>Keep Stable Tag]
    end
```

When a release is created:

1. All changesets are collected
2. Version bump is determined (patch/minor/major)
3. Changelog entries are generated for:
   - CHANGELOG.md: Generated by @changesets/cli/changelog
     - Detailed developer changelog
     - Includes PR links and commit references
     - Used by GitHub releases
   - readme.txt: Generated by our custom formatter
     - WordPress.org compatible format
     - Groups changes by type (Features/Bugfixes)
     - Updates stable tag (standard releases only)
     - Follows WordPress.org readme standards
4. Version numbers are updated in:
   - constants.php
   - package.json
   - wp-graphql.php
5. `@since next-version` tags are replaced with new version (standard releases only)
6. Changes are committed and pushed
7. GitHub release is created
8. Plugin is deployed to WordPress.org:
   - Standard releases: Update stable tag to new version
   - Beta releases: Keep stable tag pointing to last stable

## Branch Strategy

### Active Development (v2.x)

- `master`: Current stable release (2.x.x)
- `develop`: Development branch for next 2.x release
- All feature/bugfix PRs target `develop`

### Maintenance Mode (v1.x)

> **Note**: 1.x branch is in limited support mode with manual releases only.

- Only critical bug fixes and security patches
- Limited to latest minor.patch of v1.x series

### Next Major Version (v3.x)

- `next-major`: Development branch for v3.0
- Breaking changes and major features target this branch
- Follows beta release process (see Beta Releases Guide)

## Release Workflow

### Current Version (v2.x)

The release process is fully automated:

1. PRs are merged to `develop` with the `ready-for-changeset` label
2. Changesets are collected in a PR from `changeset-collection` to `develop`
3. When the changeset collection PR is merged to `develop`, a workflow automatically:
   - Merges `develop` into `master`
   - Processes changesets to determine version bump
   - Updates version numbers and `@since` tags
   - Generates changelogs
   - Creates a GitHub release
   - Deploys to WordPress.org

### Beta Releases

For beta releases (e.g., v3.0.0-beta.1):

1. PRs are merged to `next-major` with the `ready-for-changeset` label
2. Changesets are collected in a PR from `changeset-beta` to `next-major`
3. When the changeset collection PR is merged to `next-major`, the same workflow:
   - Processes changesets with prerelease flag
   - Updates version numbers with beta suffix
   - Keeps stable tag unchanged
   - Creates a GitHub pre-release
   - Deploys to WordPress.org
   - **Note: `@since` tags are NOT updated for beta releases**

See [Beta Releases Guide](../docs/beta-releases.md) for more details.

## Customizations

WPGraphQL uses some custom changeset behaviors:

1. Automatic generation from PR metadata
2. Multi-file version updates (constants.php, wp-graphql.php, etc)
3. Dual changelog generation (CHANGELOG.md and readme.txt)
4. WordPress.org deployment integration
5. `@since next-version` tag replacement

## Changeset Collection PR

When changesets are generated, they are collected in a PR with the following characteristics:

1. **Title**: "release: next version 📦" (or "release: next beta version 📦" for beta releases)
2. **Target Branch**:
   - Standard releases: `develop` branch
   - Beta releases: `next-major` branch
3. **Source Branch**:
   - Standard releases: `changeset-collection` branch
   - Beta releases: `changeset-beta` branch
4. **PR Description**: Includes a changelog preview showing:
   - Release type (major, minor, or patch)
   - Bulleted list of changes with PR numbers and titles
   - Breaking changes are marked with 🚨

Example PR description:

```markdown
# release: next version 📦

This PR collects changesets from various merged PRs. When merged, it will trigger version updates based on the collected changesets.

## Changelog Preview

This PR includes the following changes that will be part of the next release:

### Release Type: patch

- #123: fix: Fix issue with resolver cache
- #124: chore: Update dependencies
- #125: feat: Add new filter for post connections

## Additional Information

When merged, this PR will trigger version updates based on the collected changesets.
```

The changelog preview is automatically updated whenever new changesets are added to the collection branch.

## Breaking Changes Detection

Breaking changes are detected in two ways:

1. **PR Title**: If the PR title includes an exclamation mark (`!`) before the colon, it's considered a breaking change:

   - `feat!: Add new API` (breaking change)
   - `fix(core)!: Change behavior` (breaking change)

2. **PR Description**: If the PR description includes a "Breaking Changes" section with actual content:
   - The section must contain meaningful content (not just template placeholders)
   - HTML comments and template instructions are automatically ignored
   - Empty sections or sections with only placeholders will not trigger a major version bump

When a breaking change is detected:

- The changeset will be marked with `breaking: true`
- The version bump will be set to `major`
- The PR must include an "Upgrade Instructions" section
- The breaking change will be marked with 🚨 in the changelog preview

### Example of Breaking Change in PR Description

```markdown
## Breaking Changes

The `graphql_connection_page_info` filter has been renamed to `graphql_connection_page_info_fields`
for better clarity about what's being filtered.

## Upgrade Instructions

Update any code using the `graphql_connection_page_info` filter to use
`graphql_connection_page_info_fields` instead.
```
