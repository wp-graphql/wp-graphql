# WPGraphiQL

**WARNING:**

This project is rapidly iterating and may cause breaking changes at any time. Please use this and provide feedback, but understand that it will be changing quickly.

![Screen Recording of WPGraphiQL 2 in action](./img/explorer-screen-recording.gif)

## What is WPGraphiQL 2.0?

WPGraphiQL 2.0 is a rebuild of the GraphiQL IDE that ships with WPGraphiQL.

It is being developed as a standalone plugin to start, but will eventually be merged into WPGraphQL
core as the default GraphiQL IDE that will be supported and maintained with WPGraphQL.

## Installation / Activation

At this moment, this plugin is only available from Github, so you'll need to download the Zip or clone the repo.

The repo does include the built JavaScript files, so you can install the zip of the plugin to your WordPress install.

In the future, the plugin will also be available on Packagist for installing via Composer and available on WordPress.org

## Why rebuild WPGraphiQL?

You, like many other users, have probably used GraphiQL inside your WordPress dashboard
and thought to yourself "wow, this is perfect software" and so now you're wondering why it's being
re-built.

Extendability is the answer.

The main goal behind the rebuild of WPGraphiQL is to build it in a way where it, much like the
rest of WPGraphQL, can be extended by other WordPress plugins to create a customizable experience
for users using WPGraphQL.

That, and, despite the unanimous feedback, it's not actually perfect software.

In addition to making the IDE extendable, we're also working on improving some User Interfaces.

## What's Different?

For the most part, functionality should be similar, but the UI has been updated to provide a smoother
experience.

### New Explorer

The "Explorer" UI has been rebuilt with an updated UI (using Ant.Design components) and the ability
for plugin authors to customize the experience.

For example, the Explorer has filters in place that allow plugin authors the ability to add custom
action buttons for each operation, override input components for arguments, etc.

### New Code Exporter

@todo

The Code Exporter is being rebuilt to include more relevant templates and extendable components.

## How can I extend WPGraphiQL?

Ok, this sounds interesting!

So tell me, how can I, as a plugin developer, go about "extending" GraphiQL??

Here's a video you can watch, and below is some documentation about the filters available.

[![Video showing how to build a WPGraphiQL extension](./img/extension-tutorial-video-screenshot.png)](https://www.youtube.com/watch?v=e2l35zAT4JQ)

### Hooks and Filters

Many WordPress developers are familiar with hooks and filters in PHP, and WPGraphiQL 2.0 now includes
a hook and filter system for JavaScript.

We've rebuilt WPGraphiQL to have many hookable/filterable areas, using
the [@wordpress/hooks](https://www.npmjs.com/package/@wordpress/hooks) package.

The following filters have been added (with more to come):

#### graphiql_query_params_provider_config

This filter can be used to adjust the default behavior for the Query Params Context

#### graphiql_apollo_client_config

This filter can be used to customize the Apollo client config.

This is still in progress, but the plan is to expose Apollo to extensions so that
extensions can easily query data from GraphQL using Apollo for use in their extensions.

#### graphiql_app

This filter can be used to wrap the app with Context providers.

You can see this in use by the Explorer and Code Exporter

#### graphiql_app_context

This filter allows plugins to adjust the default values of the AppContext provider

#### graphiql_explorer_context_default_value

This filter allows plugins to adjust the default value of the Explorer Context provider

#### graphiql_code_exporter_context_default_value

This filter allows plugins to adjust the default value of the Code Exporter Context provider

#### graphiql_before_graphiql

This allows plugins to add panels before (to the left of) the GraphiQL IDE

#### graphiql_after_graphiql

This allows plugins to add panels after (to the right of) the GraphiQL IDE

#### graphiql_container

This allows plugins to wrap the container with other markup

#### graphiql_toolbar_buttons

This allows plugins to filter the array of buttons in the Toolbar

#### graphiql_toolbar_before_buttons

This filter allows plugins to add custom components before (to the left of) the Toolbar buttons

#### graphiql_toolbar_after_buttons

This filter allows plugins to add custom components after (to the right of) the Toolbar buttons

#### graphiql_explorer_operation_action_menu_items

This filter allows plugins to add custom actions to the "Operation Actions" menu.
The default buttons are "Clone Query" and "Delete Query".
