---
title: "Build Your First IDE Extension"
description: "A complete, minimal IDE extension — a PHP plugin shell that enqueues a script, and a JavaScript entry that adds a response panel."
---

This walkthrough builds a tiny but complete WPGraphQL IDE extension from scratch. By the end you'll have a WordPress plugin that adds a new tab to the IDE's response pane. It assumes you've read [Extending the IDE](./extending-the-ide.md) for the big picture.

## What we'll build

A plugin that surfaces a custom `extensions` payload — call it `myExtension` — in a new **My Extension** response tab. (Adding the payload to the response is a server-side concern in your own plugin; here we focus on the IDE side, and use the [execute lifecycle](./hooking-the-execute-lifecycle.md) to synthesize one for a quick demo.)

## 1. The plugin shell (PHP)

Create `my-ide-extension/my-ide-extension.php`:

```php
<?php
/**
 * Plugin Name: My IDE Extension
 * Requires Plugins: wpgraphql-ide
 */

add_action( 'wpgraphql_ide_enqueue_script', function ( $app_context ) {
	$asset = require plugin_dir_path( __FILE__ ) . 'build/extension.asset.php';

	wp_enqueue_script(
		'my-ide-extension',
		plugins_url( 'build/extension.js', __FILE__ ),
		// 'wpgraphql-ide' guarantees window.WPGraphQLIDE exists; the asset
		// file adds react / wp-* deps detected by @wordpress/scripts.
		array_merge( [ 'wpgraphql-ide' ], $asset['dependencies'] ),
		$asset['version'],
		true
	);
} );
```

`wpgraphql_ide_enqueue_script` fires right before the IDE's own bundle is enqueued, so listing `wpgraphql-ide` as a dependency loads your script at the right time.

## 2. The JavaScript entry

Create `my-ide-extension/src/extension.js`:

```js
window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { registerResponseExtensionTab } = window.WPGraphQLIDE;

	registerResponseExtensionTab(
		'myExtension', // the key in response.extensions this tab reads
		{
			// Dynamic titles get the tab's data; show a count, status, etc.
			title: ({ data }) => (data ? 'My Extension ✓' : 'My Extension'),
			content: ({ data, response }) => {
				if (!data) {
					return <p>No myExtension data in the last response.</p>;
				}
				return <pre>{JSON.stringify(data, null, 2)}</pre>;
			},
		},
		50 // priority — lower renders further left
	);
});
```

The first argument, `myExtension`, is the key the tab reads from `response.extensions`. When a response contains `extensions.myExtension`, the tab appears and your `content` receives that value as `data`. See [Adding a Response Panel](./adding-a-response-panel.md) for the full contract.

## 3. Build it

Use `@wordpress/scripts` so React and the `wp.*` packages are externalized (shared with the IDE rather than bundled):

```json
{
  "scripts": {
    "build": "wp-scripts build src/extension.js --output-path=build"
  },
  "devDependencies": { "@wordpress/scripts": "*" }
}
```

```bash
npm install && npm run build
```

Activate the plugin, open the IDE, and run a query. Once a response carries `extensions.myExtension`, your tab shows up.

## 4. See it without a server (optional)

To preview the tab before wiring up the server side, synthesize the payload with the [`wpgraphql-ide.executeResponse`](./hooking-the-execute-lifecycle.md) filter — add this inside the same `WPGraphQLIDE_Window_Ready` listener:

```js
const { hooks } = window.WPGraphQLIDE;
hooks.addFilter(
	'wpgraphql-ide.executeResponse',
	'my-ide-extension/demo',
	(response) => ({
		...response,
		extensions: {
			...response.extensions,
			myExtension: { hello: 'world', at: Date.now() },
		},
	})
);
```

Run any query and the **My Extension** tab appears with the synthetic data. Remove this once your server emits the real payload.

## Where to go next

- [Adding a Response Panel](./adding-a-response-panel.md) — titles, `alwaysShow`, and the data contract in depth.
- [Hooking the Execute Lifecycle](./hooking-the-execute-lifecycle.md) — rewrite requests, synthesize extensions, run analytics.
- [Tracking State Across Executions](./tracking-state-across-executions.md) — if your panel needs to count or aggregate across runs.
- [Access Functions](./access-functions.md) — the full registration API.
