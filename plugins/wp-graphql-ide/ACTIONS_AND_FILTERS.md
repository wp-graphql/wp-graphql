# Actions & Filters

## PHP Actions

### `wpgraphql_ide_init`

Fires when WPGraphQL is available. Sub-plugins and third-party code should use this to initialize.

### `wpgraphql_ide_enqueue_script`

Fires just before the IDE render script is enqueued. Use this to enqueue extension scripts that depend on `wpgraphql-ide`. Receives the `$app_context` array.

## PHP Filters

### `wpgraphql_ide_capability_required`

Override the capability required to view the IDE. Default: `manage_graphql_ide`.

### `wpgraphql_ide_context`

Modify the app context object passed to `window.WPGRAPHQL_IDE_DATA.context`.

### `wpgraphql_ide_external_fragments`

Add external GraphQL fragments (strings) that are auto-merged into every query.

## JavaScript Actions

All JavaScript hooks use a private `@wordpress/hooks` instance exposed on `window.WPGraphQLIDE.hooks`.

### Lifecycle

- `wpgraphql-ide.init` — Fires after stores are registered and the registry is initialized.
- `wpgraphql-ide.rendered` — Fires after the React root has mounted.
- `wpgraphql-ide.destroyed` — Fires when the React root unmounts.

### Registration

- `wpgraphql-ide.afterRegisterToolbarButton` — Fires after a toolbar button is successfully registered. Args: `name`, `config`, `priority`.
- `wpgraphql-ide.registerToolbarButtonError` — Fires when toolbar button registration fails. Args: `name`, `config`, `priority`, `error`.
- `wpgraphql-ide.afterRegisterActivityBarPanel` — Fires after an activity bar panel is successfully registered. Args: `name`, `config`, `priority`.
- `wpgraphql-ide.registerActivityBarPanelError` — Fires when activity bar panel registration fails. Args: `name`, `config`, `priority`, `error`.
- `wpgraphql-ide.afterRegisterResponseExtensionTab` — Fires after a response extension tab is successfully registered. Args: `name`, `config`, `priority`.
- `wpgraphql-ide.registerResponseExtensionTabError` — Fires when response extension tab registration fails. Args: `name`, `config`, `priority`, `error`.

## JavaScript Filters

None currently. The hook instance supports `applyFilters` for future use.
