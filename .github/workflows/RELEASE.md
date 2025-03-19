# Release Management Workflow

This document outlines the implementation details for the Release Management workflow.

## Overview

This workflow automates the release process by:
1. Creating and managing releases based on changesets
2. Updating version numbers and changelogs
3. Creating GitHub releases
4. Deploying to WordPress.org
5. Managing release artifacts

## Triggers

The workflow is triggered by:

1. When a pull request from `develop` to `main` is merged
2. Manually via the GitHub Actions UI using workflow_dispatch with options:
   - `release_type`: Force a specific release type (auto/major/minor/patch)
   - `target_branch`: Target branch for manual release (default: develop)
   - `deploy_only`: Only deploy an existing tag
   - `deploy_tag`: Specific tag to deploy
3. Scheduled runs on the 1st and 15th of each month

## Jobs

### 1. Prepare Release
Handles the release preparation process:
1. Version bumping based on changesets or specified type
2. Updating @since and deprecated version tags
3. Generating release notes
4. Creating GitHub release
5. Managing changesets
6. Syncing changes back to develop

### 2. Deploy WordPress
Handles the WordPress.org deployment and artifact management:
1. Building the plugin (PHP + JS dependencies)
2. Deploying to WordPress.org
3. Creating and uploading release artifacts
4. Attaching artifacts to GitHub release

## Release Notes Generation

The workflow generates comprehensive release notes including:
1. Changes from changesets
2. Updates to @since tags
3. Breaking changes (if any)
4. PR content (when merging from develop to main)

## Version Management

Version bumping is determined by:
1. Explicitly specified release type via workflow_dispatch
2. Changesets content analysis
3. Default to patch version if no changesets found

## Branch Management

The workflow maintains synchronization between branches:
1. Creates/updates release PR from develop to main
2. Syncs main back to develop after release
3. Handles merge conflicts and updates

## Environment Variables and Secrets

Required secrets:
- `REPO_PAT`: GitHub token with repo scope
- `SVN_PASSWORD`: WordPress.org SVN password
- `SVN_USERNAME`: WordPress.org SVN username

## Prerequisites

1. GitHub repository with main and develop branches
2. WordPress.org plugin repository access
3. Proper repository secrets configuration

## Error Handling

The workflow includes robust error handling for:
1. Release creation failures
2. WordPress.org deployment issues
3. GitHub API rate limiting
4. Artifact upload failures

## Local Testing

You can test various components locally:

```bash
# Test version bumping
npm run version:bump                        # Auto-detect version bump from changesets
npm run version:bump -- --type=patch        # Force specific bump type (patch|minor|major)
npm run version:bump -- --new-version=1.2.3 # Set specific version number

# Test @since tag updates
npm run since-tags:update -- <version>      # Update @since tags with specific version
npm run since-tags:update -- --dry-run      # Check for pending updates without applying

# Generate release notes
npm run release:notes                       # Generate markdown format
npm run release:notes -- --format=json      # Generate JSON format
```

These are the same scripts used in the automated workflow. Make sure you have all dependencies installed (`npm ci`) before running these commands. 