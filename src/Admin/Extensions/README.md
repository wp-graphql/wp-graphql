# Extensions

This directory contains a list of extensions for WPGraphQL. These extensions are not all officially maintained by the WPGraphQL team, but they have been reviewed and approved for inclusion in this list.

For more information on how to submit an extension, see [docs/submit-extension.md](../docs/submit-extension.md).

## Validation and Review

- **JSON Schema**: Submissions must adhere to the schema defined at `./schemas/extensions.json`. 
- **GitHub Actions**: Automated workflows will validate JSON files and flag issues for maintainers.
- **Basic Smoke-testing**: An approving maintainer will perform basic smoke-tests to ensure the extension integrates with WPGraphQL as expected. Documentation of this testing will be added to the PR for internal records.
- **Documentation**: Adequate documentation for installation, setup, and basic usage must be provided. This can be as simple as a detailed README file.
- **Support**: A valid support channel must be provided, such as a GitHub issues page or a support forum on WordPress.org.

If an extension does not meet these criteria, it may be rejected or removed from the list.

## How the Extensions Page Works

The extensions page is generated from the `extensions.json` file in this directory. The JSON file is validated against the [schemas/extensions.json](../../../schemas/extensions.json) file in a [GitHub Workflow](../../../.github/workflows/validate-extensions.yml) to ensure that all required fields are included and valid.

The extensions are then displayed on the [WPGraphQL website](https://www.wpgraphql.com/extensions/) as part of the WPGraphQL.com website build process.

Extensions are then shown in the WPGraphQL Extensions page in the WordPress dashboard under the WPGraphQL Admin Menu.

The Extensions page is a React app that reads the list of extensions, sent to the React app that displays the extensions and allows for extensions hosted on WordPress.org to be installed and activated directly from the Extensions page. Extensions hosted on GitHub are linked to their repository and can be downloaded from their repository, or following whatever installation instructions the plugin provides.

The list of extensions is loaded into PHP from the `src/Admin/Extensions/extensions.json` file, and passed through filters before the list is localized via `wp_localize_script` for the React app to use.

There are some helpful php filters that allow for additional extensions to be added to the list, or for the list to be modified in any way before it is sent to the React app.

## Filtering the Extensions List

The extensions list can be filtered using the `graphql_pre_get_extensions` or `graphql_get_extensions` filter.

### Modify the Extensions List

If you want to modify the extensions list by adding or removing extensions, you can use the `graphql_get_extensions` filter.

This filter is run after the extensions list is loaded from the `extensions.json` file, but before it is localized for the React app.

The example below shows how to add a new extension to the list of extensions.

```php
// Filter the list of extensions to add a new extension
add_filter( 'graphql_get_extensions', function( $extensions ) {
    // Add a new extension to the list
    $extensions[] = [
        'name' => 'My New Extension',
        'description' => 'This is a new extension that I created.',
        'plugin_url' => 'https://example.com/my-new-extension',
        'support_url' => 'https://example.com/my-new-extension/support',
        'repo_url' => 'https://wordpress.org/plugins/my-new-extension',
        'author' => [
            'name' => 'My Name',
            'homepage' => 'https://example.com/my-homepage',
        ],
    ];
    return $extensions;
} );
```

### Overriding the Extensions List

If you want to completely replace the extensions list with your own list, you can use the `graphql_pre_get_extensions` filter.

If this filter is used, it will completely replace the extensions list loaded from the `extensions.json` file. 

The `graphql_get_extensions` filter will NOT be run in this case.

The example below shows how to completely replace the extensions list with a new list of extensions, bypassing the `extensions.json` file.

```php
// Completely replace the extensions list, preventing the extensions.json file from being loaded
add_filter( 'graphql_pre_get_extensions', function( $extensions ) {
    // Completely replace the extensions list
    $extensions = [
        [
            'name' => 'My New Extension',
            'description' => 'This is a new extension that I created.',
            'plugin_url' => 'https://example.com/my-new-extension',
            'support_url' => 'https://example.com/my-new-extension/support',
            'repo_url' => 'https://wordpress.org/plugins/my-new-extension',
            'author' => [
                'name' => 'My Name',
                'homepage' => 'https://example.com/my-homepage',
            ],
        ],
    ];
    return $extensions;
} );
```

## React App

The React app that consumes the filtered list of extension plugins is maintained under `./packages/extensions`.

More information about the React app can be found in the [README.md](../packages/extensions/README.md) file in that directory.
