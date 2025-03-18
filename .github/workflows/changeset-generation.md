# Changeset Generation Workflow

This document outlines the requirements and implementation details for the Changeset Generation workflow.

## Overview

This workflow automatically generates changesets for pull requests. Changesets are used to track changes that will be included in the next release, and they help in generating changelogs and determining version bumps.

## Triggers

The workflow is triggered by:

1. When a pull request is merged to the `develop` branch
2. Manually via the GitHub Actions UI using the workflow_dispatch event
   - Go to Actions > Generate Changeset > Run workflow
   - Enter the PR number and click "Run workflow"

## Implementation Options

There are two main approaches to implementing changeset generation:

1. **Using @changesets/cli package**: A popular solution for managing changesets in JavaScript projects.
2. **Custom implementation**: A tailored solution that fits specific needs, which is the approach used in this repository.

## Changeset Format

Each changeset is a markdown file stored in the `.changesets` directory with the following format:

```md
---
title: "Brief description of the change"
pr: 123
author: "username"
type: "feat|fix|chore|docs|refactor|test|style|perf"
breaking: true|false
---

Detailed description of the change...
```

### Fields

- **title**: A brief description of the change
- **pr**: The pull request number
- **author**: The GitHub username of the PR author
- **type**: The type of change, following conventional commit types
- **breaking**: Whether this is a breaking change (true/false)

## Breaking Change Detection

Breaking changes are detected by:

1. Explicit flag in the changeset (`breaking: true`)
2. Conventional commit prefix with `!` (e.g., `feat!:`)
3. The phrase "BREAKING CHANGE" in the PR title or body

## Workflow Steps

1. **Debug Event Information**: The workflow first logs important event details for debugging purposes
2. **Generate Changeset**: The main job runs when a PR is merged or manually triggered, and:
   - Checks out the code
   - Sets up Node.js
   - Installs dependencies
   - Extracts PR information
   - Generates and commits the changeset
   - Updates or creates a release PR

## Release Notes Generation

The `generate-release-notes.js` script:

1. Reads all changeset files from the `.changesets` directory
2. Categorizes changes into breaking changes, features, fixes, and other changes
3. Determines the appropriate version bump type (major, minor, or patch)
4. Formats the release notes in either Markdown or JSON format
5. Identifies contributors and first-time contributors
6. Can be run locally for testing with `npm run release:notes`

### Script Options

The script supports the following command-line options:

- `--format`: Output format (json or markdown, default: markdown)
- `--repo-url`: Repository URL to use for PR links (overrides package.json)
- `--token`: GitHub token for API requests (needed to identify first-time contributors)

### Environment Variables

The script also supports the following environment variables:

- `REPO_URL`: Repository URL to use for PR links (can be used instead of `--repo-url`)
- `GITHUB_TOKEN`: GitHub token for API requests (can be used instead of `--token`)

Using environment variables allows you to set these values once in your environment rather than passing them as command-line arguments each time.

### Contributors Section

The release notes include a special section to acknowledge contributors:

1. **Contributors**: Lists all contributors who made changes in this release
2. **First-time Contributors**: Gives special recognition to first-time contributors

To identify first-time contributors, the script uses the GitHub API to check if a contributor has made 3 or fewer commits to the repository. This requires a GitHub token to be provided via the `--token` option or the `GITHUB_TOKEN` environment variable.

## GitHub Action Implementation

```yaml
name: Generate Changeset

on:
  pull_request_target:
    types: [closed]
    branches:
      - develop
  workflow_dispatch:
    inputs:
      pr_number:
        description: 'PR number to generate changeset for'
        required: true
        type: string

jobs:
  debug-event:
    runs-on: ubuntu-latest
    if: github.event_name == 'pull_request_target'
    steps:
      - name: Debug Event
        run: |
          echo "Event details..."

  generate-changeset:
    permissions:
      contents: write
      pull-requests: write
    runs-on: ubuntu-latest
    needs: [debug-event]
    if: (github.event_name == 'pull_request_target' && github.event.pull_request.merged == true) || github.event_name == 'workflow_dispatch'
    env:
      REPO_URL: "https://github.com/${{ github.repository }}"
      GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
    steps:
      # Checkout code
      # Setup Node.js
      # Install dependencies
      # Extract PR information
      # Generate changeset
      # Generate release notes
      # Update/Create release PR
```

## Temporary Files

The workflow uses temporary files to store release notes during execution:

1. Release notes are generated to a temporary directory (`/tmp/release-notes`)
2. These files are not committed to the repository
3. The temporary files are automatically cleaned up after the workflow completes

This approach keeps the repository clean while still allowing the workflow to process and use the release notes.

## Prerequisites

The workflow uses the default `GITHUB_TOKEN` secret provided by GitHub Actions, which has the necessary permissions to:
- Read repository contents
- Create and update pull requests
- Commit changes to branches

No additional secrets or tokens need to be configured.

## Local Testing

You can test the release notes generation locally by running:

```bash
npm run release:notes
```

For JSON output (used in PR descriptions):

```bash
npm run release:notes -- --format=json
```

To specify a custom repository URL for PR links:

```bash
npm run release:notes -- --repo-url="https://github.com/wp-graphql/wp-graphql"
```

Or using environment variables:

```bash
export REPO_URL="https://github.com/wp-graphql/wp-graphql"
npm run release:notes
```

To identify first-time contributors (requires a GitHub token):

```bash
npm run release:notes -- --token="your_github_token"
```

Or using environment variables:

```bash
export GITHUB_TOKEN="your_github_token"
npm run release:notes
```

## Next Steps and Considerations

- Create the 'ready-for-changeset' label in the repository
- Set up a Personal Access Token (PAT) with repo scope as a repository secret named `REPO_PAT`
- Test workflow with sample PRs
- Consider how to handle merge conflicts
- Plan integration with the release workflow

## Workflow Configuration

The workflow is configured to run in two scenarios:

1. When a pull request is merged to any branch (typically develop or main)
2. Manually via the GitHub Actions UI using the workflow_dispatch event

### Environment Variables

The workflow uses the following environment variables:

- `REPO_URL`: Set to `https://github.com/${{ github.repository }}` to provide the repository URL for generating PR links
- `GITHUB_TOKEN`: Set to `${{ secrets.REPO_PAT }}` to provide authentication for GitHub API requests

These environment variables are automatically set at the job level and are used by the `generate-release-notes.js` script without needing to pass them as command-line arguments.

### Prerequisites

For the workflow to function correctly, you need to:

1. Create a Personal Access Token (PAT) with `repo` scope
2. Add this token as a repository secret named `REPO_PAT` 