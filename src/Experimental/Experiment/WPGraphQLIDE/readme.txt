=== WPGraphQL IDE ===
Contributors: jasonbahl, joefusco
Tags: headless, decoupled, graphql, devtools
Requires at least: 5.7
Tested up to: 6.5
Stable tag: 2.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GraphQL IDE for WPGraphQL

== Description ==

GraphQL IDE for WPGraphQL

== Installation ==

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 2.1.1 =

### Patch Changes

- c450e5a: - Reorganized plugin directories/files.

= 2.1.0 =

### Minor Changes

- 6752c37: - Added new settings section to WPGraphQL, IDE Settings

  - Added new setting, Admin Bar Link Behavior

  ![WPGraphQL IDE Settings tab showing the admin bar link behavior and Show legacy editor settings](https://github.com/wp-graphql/wpgraphql-ide/assets/6676674/59236b4c-0019-40a8-ae9b-a1228997f30c)

= 2.0.0 =

### Major Changes

- 43eea79: Refactored stores, including renaming 'wpgraphql-ide' to 'wpgraphql-ide/app', and adding additional stores such as 'wpgraphql-ide/editor-toolbar.

  Added `registerDocumentEditorToolbarButton` function to public API.

  This function allows registering a new editor toolbar button with the following parameters:

  - `name` (string): The name of the button to register.
  - `config` (Object): The configuration object for the button.
  - `priority` (number, optional): The priority for the button, with lower numbers meaning higher priority. Default is 10.

  Example usage:

  ```js
  const { registerDocumentEditorToolbarButton } = window.WPGraphQLIDE;

  registerDocumentEditorToolbarButton("toggle-auth", toggleAuthButton, 1);
  ```

  ![Screenshot of the GraphiQL IDE highlighting the Toolbar buttons within the Document Editor region.](https://github.com/wp-graphql/wpgraphql-ide/assets/1260765/2395c3c8-1915-4a24-b64e-35ebe16e674f)

[View the full changelog](https://github.com/wp-graphql/wpgraphql-ide/blob/main/CHANGELOG.md)