---
uri: "/submit-extension/"
title: "Submit Extension"
---

# Submit a WPGraphQL Extension

We aim to create a robust and well-curated list of extensions for WPGraphQL. To achieve this, we have established a formal submission policy that outlines the process and criteria for inclusion. 

The goal is to ensure that extensions are well-maintained, properly supported, and provide value to WPGraphQL users.

## Submission Process

To submit a WPGraphQL extension for review:

1. **Fork** the [WPGraphQL GitHub repository](https://github.com/wp-graphql/wp-graphql).
2. **Create a new branch** for your extension submission.
3. **Modify** the `extensions.json` file: Add your plugin's details (see JSON schema below for required fields).
4. **Validate** your JSON file against the provided `extensions.json.schema`.
5. **Submit a Pull Request (PR)** for review.

### Alternate Submission Methods

We are exploring additional submission methods:

- **GitHub Issue Template**: An automated workflow that converts a GitHub issue submission into a commit.
- **Web Form on wpgraphql.com**: A convenient form-based submission process that converts the submission into a commit.

## Criteria for Inclusion

1. **Public Repository (Required)**: The plugin must be hosted in a public repository (e.g., GitHub or WordPress.org).
    - Only **free, publicly available** plugins will be accepted.
2. **Support Link (Required)**: A valid support channel must be provided, such as a GitHub issues page or a support forum on WordPress.org.
3. **Active Maintenance**: Plugins should demonstrate active development and maintenance. Inactivity for more than **two years** _may_ lead to removal unless the plugin remains fully compatible with the latest versions of WPGraphQL and WordPress.
4. **Basic Testing (Recommended)**: While automated test coverage is not mandatory, extensions that include tests or documented compatibility checks will be prioritized for inclusion in the list of extensions.
5. **WPGraphQL Compatibility**: The extension must be compatible with the latest stable version of WPGraphQL and follow best practices for performance and security.
6. **Documentation Link (Required)**: Adequate documentation for installation, setup, and basic usage must be provided. This can be as simple as a detailed README file.

## Validation and Review

- **JSON Schema**: Submissions must adhere to the `./schemas/extensions.json` to ensure required fields are included.
- **GitHub Actions**: Automated workflows will validate JSON files and flag issues for maintainers.
- **Basic Smoke-testing**: An approving maintainer will perform basic smoke-tests to ensure the extension integrates with WPGraphQL as expected. Documentation of this testing will be added to the PR for internal records.

---

## Example Extension Submission

```jsonc
{
   "extensions": [
      // ... other extensions
      {
         "name": "Plugin Name",                         // Required: Name of the plugin
         "description": "Short description here",       // Required: Brief description (limit: 150 characters)
         "plugin_url": "https://plugin-url.com",        // Required: URL to the plugin repository or download
         "support_url": "https://support-url.com",      // Required: URL for user support
         "repo_url": "https://repo-url.com",            // Optional: URL to the plugin's source repository
         "documentation_url": "https://docs-url.com",   // Required: URL to the plugin's documentation
         "author": {
            "name": "Author Name",                     // Required: Author or maintainer name
            "homepage": "https://author-homepage.com"  // Required: URL to the author's profile or homepage
         }
      }
   ]
}
```

---

## Enforcement and Maintenance

1. **Ongoing Validation**: We may periodically review extensions for activity and compatibility. Extensions failing to meet criteria may be removed, with or without notice of the author.
2. **Community Reporting**: Users are encouraged to report issues or suggest the removal of extensions that no longer meet criteria.
3. **Version Compatibility**: Future updates to WPGraphQL may require extensions to specify version compatibility headers.

---

## Community Feedback

We welcome feedback on this policy and suggestions for improvement. Please share your ideas in the [WPGraphQL GitHub Discussions](https://github.com/wp-graphql/wp-graphql/discussions) or [WPGraphQL Discord](https://wpgraphql.com/discord).