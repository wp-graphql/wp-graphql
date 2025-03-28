# Milestone Branch Management Workflow

This document outlines the implementation details for the Milestone Branch Management workflow.

## Overview

This workflow manages milestone branches and their associated release PRs. It automatically generates changesets for PRs merged into milestone branches and maintains a release PR that tracks all changes that will be included in the milestone.

## Triggers

The workflow is triggered by:

1. When a pull request is merged to any `milestone/*` branch, using the `pull_request_target` event
2. Manually via the GitHub Actions UI using the workflow_dispatch event
   - Go to Actions > Manage Milestone Branches > Run workflow
   - Enter the PR number and milestone branch name
   - Click "Run workflow"

## Implementation

This workflow works in conjunction with the Changeset Generation workflow but specifically handles:

- Milestone branch changesets with milestone metadata
- Release PR management from milestone branches to develop
- Release notes generation with milestone grouping
- Changeset updates when PRs move between milestones

## Changeset Management

### Milestone Detection

- Automatically detects milestone name from branch reference (e.g., `milestone/feature-x` → `feature-x`)
- Includes milestone information in changeset metadata
- Updates existing changesets when PRs move between milestones

### Release Notes Organization

- Groups changes by milestone
- Provides a milestone summary section
- Maintains standard categorization (breaking changes, features, fixes)
- Lists completed milestones with links to their PRs

## Release PR Format

The release PR follows this format:

- Title: `milestone: {milestone-name} 🏁`
- Target: from `milestone/*` to `develop`
- Content:

  ```md
  ## Upcoming Changes

  ### 🎯 Completed Milestones

  - **milestone-name** (#PR)

  ### ✨ New Features

  - Feature description (#PR)

  ### 🐛 Bug Fixes

  - Fix description (#PR)

  ### 🔄 Other Changes

  - Other changes (#PR)

  ### 👏 Contributors

  [List of contributors]

  This PR contains all changes that will be included in the next release ({milestone-name}).
  It is automatically updated when new changesets are added to the milestone/\* branch.
  ```

## Environment Variables

The workflow uses:

- `REPO_URL`: Set to `https://github.com/${{ github.repository }}`
- `GITHUB_TOKEN`: The default GitHub token provided by Actions

## Prerequisites

The workflow requires:

- Default `GITHUB_TOKEN` with permissions:
  - `contents: write`
  - `pull-requests: write`
- Node.js and npm for running scripts
- Milestone branches following the pattern `milestone/*`

## Error Handling

The workflow includes error handling for:

- PR information extraction
- Changeset management
- Release PR creation/updates
- API responses

All errors are logged with detailed information for debugging purposes.

## Relationship with Changeset Generation

While the Changeset Generation workflow handles changes going into `develop`, this workflow:

- Manages changes in milestone branches
- Creates staging PRs for milestone releases
- Ensures changes are properly tracked before merging to develop

This separation allows for better organization of features and changes that should be released together.
