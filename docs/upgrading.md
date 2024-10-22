---
uri: "/docs/upgrading/"
title: "Upgrading"
---

Upgrading WPGraphQL is an important part of keeping your API secure, performant, and up to date with new features. However, as with any software, some updates may introduce breaking changes that require you to take action to maintain compatibility with your project.

This guide outlines the key steps and policies for upgrading WPGraphQL, including how we handle breaking changes, what to expect from versioning, and how you can plan your upgrades.

## Semantic Versioning

WPGraphQL follows [**Semantic Versioning** (SemVer)](https://semver.org/) to communicate the meaning of each release and the nature of changes introduced. This ensures that users can easily understand the potential impact of any given release. Semantic Versioning uses the format **MAJOR.MINOR.PATCH**:

1. **MAJOR** (e.g., `2.0.0`):
    - A major version increment indicates a breaking change. Something in this release requires users to take action to remain compatible. Examples include changes to the API schema, function signatures, or other critical changes that could disrupt compatibility.
    - Major versions will contain clear documentation and upgrade guides to help users transition smoothly.

2. **MINOR** (e.g., `1.1.0`):
    - A minor version increment adds new functionality in a backward-compatible manner. Users should be able to upgrade without breaking their existing code or integrations.
    - Minor releases may include enhancements, new features, and optimizations, but they will not require users to make any changes to existing functionality.

3. **PATCH** (e.g., `1.0.1`):
    - A patch version increment is for backward-compatible bug fixes. These fixes address issues without affecting functionality or introducing breaking changes.
    - Patch releases are safe for users to apply without concern for changes to their existing workflows.

By adhering to Semantic Versioning, WPGraphQL clearly signals when users should expect breaking changes, when new features are available, and when critical bugs are fixed, helping the community make informed upgrade decisions.

## Categorizing Breaking Changes
Breaking changes will be categorized based on two dimensions: **Impact Scope** and **Effort to Upgrade**.

- **Impact Scope**:
    - Low: Affects few users (niche use cases or specific extensions).
    - Medium: Affects a moderate number of users or common use cases.
    - High: Affects most or all users interacting with the API.

- **Effort to Upgrade**:
    - Minimal: Requires little to no action to remain compatible.
    - Moderate: Requires small adjustments (e.g., changing function signatures).
    - Significant: Requires refactoring large sections of code or schema.

Each breaking change will be classified using these dimensions (e.g., "High Impact, Minimal Effort").

## Communication of Breaking Changes
Breaking changes will be communicated through multiple channels to ensure broad community awareness. These include:

- WPGraphQL Discord
- Headless WordPress Discord
- Twitter (X)
- Email

## Changelog and Upgrade Guide
For any version containing breaking changes, the changelog will include:
- A summary of the breaking changes with categorization (Impact Scope + Effort to Upgrade).
- A highlighted link to a detailed upgrade guide.

The **Upgrade Guide** will provide:
- Step-by-step instructions to adapt to the breaking change.
- Code examples, if applicable.
- Recommendations for common issues during upgrades.

## Release Cadence
WPGraphQL takes an approach of **smaller, more frequent releases**. This means:
- **Incremental Changes**: Breaking changes will be released in smaller increments, allowing users to upgrade more easily and manage the impact with minimal disruption.
- **Clear Communication**: Each breaking change will be categorized by **Impact Scope** and **Effort to Upgrade**, enabling users to make informed decisions about when to upgrade.
- **Limited Version Support**: WPGraphQL will support only the last 3 major versions to reduce fragmentation, ensuring that users have clear expectations regarding version support.

## Beta Period and Testing
WPGraphQL will establish a **beta period** of approximately 30 days for major releases. During this time, users are encouraged to:
- Test the upcoming release on a specific **release branch**.
- Report any bugs or issues not already covered by the testing matrix (PHP and WordPress versions).

## Support Policy
WPGraphQL will support the **last 3 major versions** to reduce version fragmentation. This means that older versions beyond this window will no longer receive official support, and users will be encouraged to upgrade to the latest version.

While older versions may still function, the quality and speed of support will be higher for those using the latest versions.

## Process for Releasing Breaking Changes
1. **Pre-release Preparation**:
    - Categorize the breaking change (Impact Scope + Effort to Upgrade).
    - Document the change in the upgrade guide.
    - Prepare a release branch for major releases with a beta period (30 days).

2. **Pre-release Communication**:
    - Share early release notes in community channels (Discord, Twitter, Facebook, etc.).
    - Encourage community testing and feedback during the beta period.

3. **Post-release**:
    - Announce the release and provide a link to the changelog and upgrade guide.
    - Monitor feedback and issue patches as necessary.