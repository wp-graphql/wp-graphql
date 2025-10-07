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
3. **Modify** the `./src/Admin/Extensions/Registry.php` file: Add your plugin's details to the array returned by the `Registry::get_extensions()` method.
4. **Validate** your change by ensuring `composer check-cs` and `composer phpstan` pass.
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

- **Basic Smoke-testing**: An approving maintainer will perform basic smoke-tests to ensure the extension integrates with WPGraphQL as expected. Documentation of this testing will be added to the PR for internal records.

---

## Example Extension Submission

```php
// ./src/Admin/Extensions/Registry.php
public static function get_extensions(): array {
	return [
		// ... other extensions
		'my-unique-prefix/my-new-extension' => [ // Unique identifier for the extension, this should be placed alphabetically in the list.
			'name' => 'My New Extension', // Required: Name of the plugin
			'description' => 'This is a new extension that I created.', // Required: Brief description (limit: 150 characters)
			'plugin_url' => 'https://example.com/my-new-extension', // Required: URL to the plugin repository or download
			'repo_url' => 'https://wordpress.org/plugins/my-new-extension', // Optional: URL to the plugin's source repository
			'support_url' => 'https://example.com/my-new-extension/support', // Required: URL for user support
			'documentation_url' => 'https://example.com/my-new-extension/docs', // Required: URL to the plugin's documentation
			'author' => [
				'name' => 'My Name', // Required: Author or maintainer name
				'homepage' => 'https://example.com/my-homepage', // Optional: URL to the author's profile or homepage
			],
		],
		// ... other extensions
	];
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
