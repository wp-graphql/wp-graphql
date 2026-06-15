---
title: "Adding an Activity Bar Panel"
description: "Add a global panel to the IDE's left activity bar with registerActivityBarPanel."
---

The activity bar is the icon strip down the left side of the IDE. Each icon toggles a global panel — Saved Queries, Docs Explorer, History. These panels are **global**: they aren't tied to the active document, which makes the activity bar the right home for anything that spans the whole workspace (a saved-snippets library, a schema-diff viewer, a team notes panel).

## Registering a panel

```js
import { Icon, plugins } from '@wordpress/icons';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { registerActivityBarPanel } = window.WPGraphQLIDE;

	registerActivityBarPanel('my-snippets', {
		title: 'Snippets',                 // header text + tooltip
		icon: () => <Icon icon={plugins} />, // the activity-bar button
		content: () => <MySnippetsPanel />,  // the panel body
	}, 20);
});
```

- `title` — shown in the panel header and as the icon's tooltip.
- `icon` — a component for the activity-bar button. The `@wordpress/icons` set matches the IDE's visual language.
- `content` — the panel body, rendered when the panel is open.
- `headerAction` *(optional)* — a component rendered on the right of the panel header (the built-in Saved Queries panel uses it for its "New collection" button).

## Priority

The third argument orders icons top-to-bottom (lower first). Built-ins use Saved Queries (1), Docs Explorer (5), History (30).

## Reading IDE state

Panels are plain React, so pull live state from the public stores with `useSelect`:

```js
import { useSelect } from '@wordpress/data';

function MySnippetsPanel() {
	const query = useSelect((s) => s('wpgraphql-ide/app').getQuery(), []);
	// …render against the current query, schema, response, etc.
}
```

The store names (`wpgraphql-ide/app`, `wpgraphql-ide/document-editor`, …) are public API. See [API Surface](./api-surface.md) for what they hold.

## A caveat worth knowing

Like every surface, the panel's `content` is unmounted while the panel is closed. If your panel maintains something that should keep updating while it's closed (or be shared with another surface), keep that state outside the component — see [Tracking State Across Executions](./tracking-state-across-executions.md).

## Reference

Full parameter list and the registration hook: [`registerActivityBarPanel`](./access-functions.md#registeractivitybarpanel).
