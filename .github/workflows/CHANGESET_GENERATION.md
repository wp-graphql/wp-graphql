# Changeset Generation Workflow

This document outlines the implementation details for the Changeset Generation workflow.

## Overview

This workflow automatically generates changesets for pull requests. Changesets are used to track changes that will be included in the next release, helping generate changelogs and determine version bumps.

## Triggers

The workflow is triggered by:

1. When a pull request is merged to the `develop` branch, using the `pull_request_target` event
2. Manually via the GitHub Actions UI using the workflow_dispatch event
   - Go to Actions > Generate Changeset > Run workflow
   - Enter the PR number and click "Run workflow"

## Implementation

This repository uses a custom implementation tailored to our specific needs, rather than using the @changesets/cli package.

## Changeset Format

Each changeset is a markdown file stored in the `.changesets` directory with the following format:

```md
---
title: "Brief description of the change"
pr: 123
author: "username"
type: "feat|fix|chore|docs|refactor|test|style|perf"
---

Detailed description of the change...
```

### Fields

- **title**: A brief description of the change
- **pr**: The pull request number
- **author**: The GitHub username of the PR author
- **type**: The type of change, following conventional commit types

## Workflow Jobs

The workflow consists of two main jobs:

### 1. Debug Event (debug-event)
- Only runs for pull_request_target events
- Logs important event details for debugging purposes:
  - Event name
  - Action
  - PR merged status
  - Base and head refs
  - PR number and title

### 2. Generate Changeset (generate-changeset)
Runs when:
- A PR is merged via pull_request_target
- Manually triggered via workflow_dispatch

This job:
1. Checks out the code
2. Sets up Node.js
3. Installs dependencies
4. Extracts PR information
5. Generates changeset
6. Commits and pushes the changeset

## Environment Variables

The workflow uses:
- `REPO_URL`: Set to `https://github.com/${{ github.repository }}`
- `GITHUB_TOKEN`: The default GitHub token provided by Actions

## Prerequisites

The workflow uses the default `GITHUB_TOKEN` provided by GitHub Actions, which has the necessary permissions to:
- Read repository contents
- Create and update pull requests
- Commit changes to branches

No additional secrets need to be configured.

## Error Handling

The workflow includes error handling for:
- PR information extraction
- Changeset generation
- Git operations

All errors are logged with detailed information for debugging purposes. 