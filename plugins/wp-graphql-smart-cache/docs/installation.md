# Installation and Activation

In this guide you will find information about installing and activating the WPGraphQL Smart Cache plugin.

## Requirements

### A WordPress Install

WPGraphQL Smart Cache is a WordPress plugin. You will need a WordPress install that you have access to upload and activate plugins to.

You can test many of the features of the plugin in a local WordPress install using tools such as [LocalWP](https://localwp.com/), but the [Network Cache](./network-cache.md) feature requires a WordPress install on a [supported host](./network-cache.md#supported-hosts).

### WPGraphQL

In order to use the WPGraphQL Smart Cache plugin, you will need to have a WordPress install with the latest version of [WPGraphQL](https://github.com/wp-graphql/wp-graphql/releases) installed and activated.

Over time we plan to support multiple versions, but currently we will only provide support if you're on the latest version of WPGraphQL and WPGraphQL Smart Cache.

## Download the plugin

At the moment, WPGraphQL Smart Cache is not available on the WordPress.org repository, or packagist.org, so you must download the plugin from Github.

You can find the latest release by visiting the [WPGraphQL monorepo releases page](https://github.com/wp-graphql/wp-graphql/releases) and looking for releases tagged with `wp-graphql-smart-cache/v*` (e.g., `wp-graphql-smart-cache/v2.0.1`).

On the release page you will see an "Assets" panel.

The asset named `wp-graphql-smart-cache.zip` is the plugin.

Download the .zip file.

> **Note:** WPGraphQL Smart Cache is now part of the [WPGraphQL monorepo](https://github.com/wp-graphql/wp-graphql). All releases, issues, and discussions are managed in the main repository.

## Upload the Plugin

Login to the WordPress installation you want to install the plugin to.

Upload the .zip to your WordPress installation under "Plugins > Add New".

> **NOTE:** Make sure the .zip file is named `wp-graphql-smart-cache.zip` when you upload it. If you've downloaded the asset multiple times you might have files named wp-graphql-smart-cache (1).zip  or similar, and that _can_ be a source of problems.

If you're updating from another version, when you upload the .zip, WordPress will ask if you want to replace the existing plugin. Confirm that you want to.

Activate the plugin.

## Verify the Plugin is Active

The easiest way to confirm the plugin is active is to visit the "WPGraphQL > Settings" page in your WordPress dashboard.

On this page, you should now see 2 new settings Tabs: "Saved Queries" and "Cache".

These settings pages are added by the WPGraphQL Smart Cache plugin.

How these settings work are covered in the following guides:

- [Network Cache](./network-cache.md)
- [Object Cache](./object-cache.md)
- [Persisted Queries](./persisted-queries.md)

----

## 👉 Next Steps

Once the plugin is installed and activated, head back to the [quick start](../README.md#quick-start) or learn more about the plugin features:

- [Network Cache (requires a supported host)](./network-cache.md)
- [Object Cache](./object-cache.md)
- [Persisted Queries](./persisted-queries)
- [Cache Invalidation](./cache-invalidation.md)
