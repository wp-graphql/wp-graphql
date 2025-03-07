# GitHub Actions Workflows

This directory contains GitHub Actions workflows that automate our release process. Here's how they work together:

## PR to Changeset Process

### 1. PR Validation (`lint-pr.yml`)

- Validates PR titles follow conventional commit format
- Ensures proper scoping and breaking change indicators
- Runs on PR creation and updates

### 2. Changeset Generation (`generate-changeset.yml`)

Triggered when a PR is labeled with `ready-for-changeset`:

1. Validates:
   - PR title format
   - Required PR description sections
2. Generates a changeset file containing:
   - Version bump type (patch/minor/major)
   - PR reference
   - Breaking change indicators
   - Upgrade instructions
3. Creates or updates a collection PR with the changeset

## Release Process

> **Note**: All releases are deployed to WordPress.org, with different handling for stable vs beta releases.

```mermaid
flowchart TD
    %% PR and Changeset Process
    PR[PR Merged] --> GC[Generate Changeset]
    GC --> ST[Scan @since todo tags]
    ST --> CPR[Create Changeset PR]

    %% Standard Release Flow
    CPR --> |Merged to develop| DEV[develop branch]
    DEV --> |Auto-merge to master| M[master branch]
    M --> VB[Version Bump]
    VB --> SV[Sync Versions<br/>package.json<br/>wp-graphql.php<br/>constants.php]
    SV --> US[Update @since tags]
    US --> CL[Generate Changelogs]
    CL --> GR[Create GitHub Release]
    GR --> WO[Deploy to WordPress.org<br/>Update Stable Tag]

    %% Beta Release Flow
    PR2[PR to next-major] --> GC2[Generate Changeset]
    GC2 --> ST2[Scan @since todo tags]
    ST2 --> CPR2[Create Beta Changeset PR]
    CPR2 --> |Merged to next-major| NM[next-major branch]
    NM --> BV[Version Bump with Beta]
    BV --> BSV[Sync Versions<br/>Keep Stable Tag]
    BSV --> BCL[Generate Changelogs]
    BCL --> BGR[Create GitHub Pre-release]
    BGR --> BWO[Deploy to WordPress.org<br/>Keep Stable Tag]
```

### Unified Release Workflow (`release-and-deploy.yml`)
