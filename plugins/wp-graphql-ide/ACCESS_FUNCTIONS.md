# Access Functions

Access Functions abstract several layers of complexity and provide simpler ways to accomplish common tasks. They are modeled around core WordPress functions like `register_post_type`, `register_taxonomy`, etc.

All access functions are available on `window.WPGraphQLIDE` after the `WPGraphQLIDE_Window_Ready` event fires.

## registerDocumentEditorToolbarButton

Registers a new toolbar button in the query editor toolbar.

**Parameters:**

- `name` (string): Unique identifier for the button.
- `config` (Object): Button configuration object.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterToolbarButton` on success
- `wpgraphql-ide.registerToolbarButtonError` on failure

**Example:**

```js
const { registerDocumentEditorToolbarButton } = window.WPGraphQLIDE;

registerDocumentEditorToolbarButton('my-button', {
  label: 'My Button',
  onClick: () => console.log('clicked'),
}, 20);
```

## registerActivityBarPanel

Registers a panel in the left activity bar sidebar.

Activity bar panels are global to the IDE (not tied to a specific document). Built-in panels include Docs Explorer, Documents, and History.

**Parameters:**

- `name` (string): Unique identifier for the panel.
- `config` (Object): Panel configuration.
  - `title` (string): Human-readable panel title shown in the header.
  - `icon` (Function): React component rendering the panel's icon.
  - `content` (Function): React component rendering the panel's content.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterActivityBarPanel` on success
- `wpgraphql-ide.registerActivityBarPanelError` on failure

**Example:**

```js
const { registerActivityBarPanel } = window.WPGraphQLIDE;
import { Icon, plugins } from '@wordpress/icons';

registerActivityBarPanel('my-panel', {
  title: 'My Panel',
  icon: () => <Icon icon={plugins} />,
  content: () => <div>Panel content here</div>,
}, 20);
```

## registerResponseExtensionTab

Registers a tab in the response pane's Extensions section.

The `name` must match a top-level key in the GraphQL response `extensions` object. The tab is only shown when the latest response contains that key. This allows plugins like WPGraphQL Smart Cache, ACF, or WooCommerce to register tabs that render their extension data.

**Parameters:**

- `name` (string): Extension key in the response (e.g. `"debug"`, `"graphqlSmartCache"`).
- `config` (Object): Tab configuration.
  - `title` (string): Human-readable tab label.
  - `content` (Function): React component receiving `{ data, response }`.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterResponseExtensionTab` on success
- `wpgraphql-ide.registerResponseExtensionTabError` on failure

**Example:**

```js
const { registerResponseExtensionTab } = window.WPGraphQLIDE;

registerResponseExtensionTab('graphqlSmartCache', {
  title: 'Smart Cache',
  content: ({ data, response }) => (
    <div>
      <p>Cache status: {data?.cacheStatus}</p>
    </div>
  ),
}, 50);
```
