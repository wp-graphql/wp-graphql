# Changesets

This directory contains "changesets" which help us manage versioning and changelogs.

## Automated Changeset Generation

Changesets are automatically generated when PRs are merged based on:

1. PR Title: Must follow conventional commit standards
   - `feat:` = minor version bump
   - `fix:` = patch version bump
   - Breaking changes can be indicated by:
     - Adding an exclamation mark: `feat!:` or `fix!:`
     - Using a breaking scope: `feat(breaking):` or `fix(breaking):`

2. PR Description Sections:
   - Breaking Changes: Used to identify major version bumps
   - Upgrade Instructions: Added to changelog for breaking changes
   - Description: Used as changelog entry

## How Changesets Work in WPGraphQL

Changesets are automatically generated when PRs are merged to track changes and automate releases. The process works as follows:

1. Contributors submit PRs following the PR template and conventional commit standards
2. When a PR is merged, a GitHub Action automatically:
   - Generates a changeset file based on:
     - PR title (for change type - feat/fix/etc)
     - PR description (for breaking changes and upgrade notes)
     - Files containing `@since todo` tags
   - Names the file with a unique hash (e.g., `hash.md`)
   - Commits the changeset to the repository

## What is a Changeset?

A changeset is a file that describes changes made in a PR. It includes:

- Type of change (patch/minor/major)
- PR number and link
- Whether it contains breaking changes
- Description of changes
- Upgrade notes (if any)
- Files containing `@since todo` that need updating

## Example Changeset

```md
---
type: minor
pr: 123
breaking: false
---

### feat: Add new GraphQL field to Post type

[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)

#### Description
Adds a new GraphQL field `customField` to the Post type that exposes custom meta data.

#### Upgrade Notes
Users implementing the PostType interface will need to implement this new field.

#### Files with @since todo
- src/Type/ObjectType/PostType.php
```

## How are Changesets Used?

When a release is created:
1. All changesets are collected
2. Version bump is determined (patch/minor/major)
3. Changelog entries are generated for:
   - CHANGELOG.md (detailed developer changelog)
   - readme.txt (WordPress.org changelog)
4. Version numbers are updated in:
   - constants.php
   - package.json
   - wp-graphql.php
5. `@since todo` tags are replaced with new version
6. Stable tag is updated in readme.txt (for non-beta releases)
7. Changes are committed and pushed
8. GitHub release is created
9. Plugin is deployed to WordPress.org (stable releases only)

## Branch Strategy

### Active Development (v2.x)
- `master`: Current stable release (v2.x.x)
- `develop`: Development branch for next v2.x release
- All feature/bugfix PRs target `develop`

### Maintenance Mode (v1.x)
- `1.x/master`: Stable v1.x release
- `1.x/develop`: Development branch for v1.x bugfixes
- Only critical bug fixes and security patches
- Limited to latest minor.patch of v1.x series

### Next Major Version (v3.x)
- `next-major`: Development branch for v3.0
- Breaking changes and major features target this branch
- Follows beta release process (see Beta Releases Guide)

## Release Workflow

### Current Version (v2.x)

```bash
# Ensure you're on develop with latest changes
git checkout develop
git pull origin develop

# Create release
npm run changeset version

# Review the changes:
# - Version numbers in constants.php, package.json, wp-graphql.php
# - Changelog entries in CHANGELOG.md and readme.txt
# - Stable tag in readme.txt
# - @since todo tags replaced with new version

# Create release
npm run changeset publish

# After release, merge to master
git checkout master
git merge develop
```

### Maintenance Release (v1.x)

```bash
# For critical bug fixes/security updates only
git checkout 1.x/develop
git pull origin 1.x/develop

# Create patch release
npm run changeset version

# Review changes...
npm run changeset publish

# After release, merge to 1.x/master
git checkout 1.x/master
git merge 1.x/develop
```

### Beta Releases

For beta releases (e.g., v3.0.0-beta.1), see [Beta Releases Guide](../docs/beta-releases.md)

## Customizations

WPGraphQL uses some custom changeset behaviors:

1. Automatic generation from PR metadata
2. Multi-file version updates (constants.php, wp-graphql.php, etc)
3. Dual changelog generation (CHANGELOG.md and readme.txt)
4. WordPress.org deployment integration
5. `@since todo` tag replacement