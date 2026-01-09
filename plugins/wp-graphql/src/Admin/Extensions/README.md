# Extensions

This directory contains a list of extensions for WPGraphQL. These extensions are not all officially maintained by the WPGraphQL team, but they have been reviewed and approved for inclusion in this list.

For more information on how to submit an extension, see [docs/submit-extension.md](../docs/submit-extension.md).

## How the Extensions Page Works

The extensions page is generated from the array defined in the [`src/Admin/Extensions/Registry.php::get_extensions()` method ](./Registry.php).

Extensions are then shown in the WPGraphQL Extensions page in the WordPress dashboard under the WPGraphQL Admin Menu, and displayed on the [WPGraphQL website](https://www.wpgraphql.com/extensions/) as part of the WPGraphQL.com website build process.

The Extensions page is a React app that reads the list of extensions, sent to the React app that displays the extensions and allows for extensions hosted on WordPress.org to be installed and activated directly from the Extensions page. Extensions hosted on GitHub are linked to their repository and can be downloaded from their repository, or following whatever installation instructions the plugin provides.

Extensions are passed through filters before the list is localized via `wp_localize_script` for the React app to use.

## Filtering the Extensions List

If you want to modify the extensions list by adding or removing extensions, you can use the `graphql_get_extensions` filter.

This filter is run after the extensions list is loaded from the `Admin\Extensions\Registry`, but before it is localized for the React app.

The example below shows how to add a new extension to the list of extensions.

```php
// Filter the list of extensions to add a new extension
add_filter( 'graphql_get_extensions', static function( array $extensions ) {
	// Add a new extension to the list
	$extensions['my-unique-prefix/my-new-extension'] = [
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

Alternatively, you can override the entire list of extensions by returning a new array with only your custom extensions.

## React App

The React app that consumes the filtered list of extension plugins is maintained under `./packages/extensions`.

More information about the React app can be found in the [README.md](../packages/extensions/README.md) file in that directory.
