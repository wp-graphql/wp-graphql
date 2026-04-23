# WPGraphQL IDE

> A next-gen query editor for [WPGraphQL](https://github.com/wp-graphql/wp-graphql)

## Overview

WPGraphQL IDE is a WordPress plugin that provides a modern GraphQL query editor built with CodeMirror 6, `@wordpress/components`, and `@wordpress/data`. It integrates into the WordPress admin as a slide-up drawer (accessible from any admin page) or a dedicated full-page editor.

### Key Features

- **Tabbed document editor** with auto-save and per-document state
- **CodeMirror 6** with GraphQL syntax highlighting, autocomplete, and linting
- **Schema-aware** autocomplete and docs explorer
- **Response viewer** with Formatted, Table, and Raw modes
- **Response extension tabs** for Debug, Query Analyzer, Tracing, and Query Log data
- **Execution history** persisted as a custom post type, scoped per user
- **Query composer** for visual query building
- **Extensible** via access functions, Redux stores, and WordPress hooks

### Architecture

- **State management**: `@wordpress/data` Redux stores (`wpgraphql-ide/app`, `wpgraphql-ide/activity-bar`, `wpgraphql-ide/document-editor`, `wpgraphql-ide/response-extensions`)
- **Editors**: CodeMirror 6 (`cm6-graphql` for GraphQL, `@codemirror/lang-json` for JSON)
- **UI components**: `@wordpress/components` (Button, TabPanel, ResizableBox, Panel, Tooltip, etc.)
- **Persistence**: Documents and history stored as custom post types via the WordPress REST API. User preferences stored as user meta.
- **Layout**: Follows the Gutenberg Post Editor pattern with a global top bar, left activity bar for global panels, and a document-scoped editor area with tabs.

### Layout Philosophy

Controls are scoped by their relationship to the active document:

- **Global** (activity bar, left sidebar): Docs explorer, history, help — things that apply regardless of which tab is active.
- **Document-scoped** (editor area): Tabs, auth toggle, query composer, send button, variables, headers — things that change per document.

## Install

WPGraphQL IDE is included in the WPGraphQL monorepo. When using the monorepo, it is automatically available via `wp-env`.

## Usage

When this plugin is active, a new settings tab "IDE Settings" will appear in the WPGraphQL settings screen. The IDE can be accessed via:

- **Drawer**: Click "GraphQL IDE" in the admin bar (slide-up from any admin page)
- **Dedicated page**: Navigate to GraphQL > GraphQL IDE in the admin menu

## Extending the IDE

### Access Functions

Three public registration functions are available on `window.WPGraphQLIDE`:

- `registerDocumentEditorToolbarButton(name, config, priority)` — Add toolbar buttons
- `registerActivityBarPanel(name, config, priority)` — Add activity bar panels
- `registerResponseExtensionTab(name, config, priority)` — Add response extension tabs

See [ACCESS_FUNCTIONS.md](ACCESS_FUNCTIONS.md) for full documentation and examples.

### Custom Hooks

See [ACTIONS_AND_FILTERS.md](ACTIONS_AND_FILTERS.md) for PHP actions/filters and JavaScript hooks.

### Extension Pattern

The recommended pattern for extending the IDE from a WordPress plugin:

```php
// my-plugin.php
add_action('wpgraphql_ide_enqueue_script', function($app_context) {
    wp_enqueue_script(
        'my-ide-extension',
        plugins_url('build/ide-extension.js', __FILE__),
        ['wpgraphql-ide'],
        '1.0.0',
        true
    );
});
```

```js
// ide-extension.js
window.addEventListener('WPGraphQLIDE_Window_Ready', function () {
    const { registerResponseExtensionTab } = window.WPGraphQLIDE;

    registerResponseExtensionTab('myExtension', {
        title: 'My Extension',
        content: ({ data, response }) => <div>{JSON.stringify(data)}</div>,
    }, 50);
});
```

## Breaking Changes Policy

1. **Access Functions**: No intentional breaking changes to the three public access functions.
2. **Public Redux Stores**: No breaking changes to the four public `@wordpress/data` stores.
3. **Internal Refactoring**: Changes to internal functions and components do not constitute breaking changes.
4. **Semantic Versioning**: Breaking changes increment the major version number per [SemVer](https://semver.org/).

## Privacy Policy

WPGraphQL IDE uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md)
