---
uri: "/docs/customizing-wpgraphiql/"
title: "Customizing the WPGraphiQL IDE"
---

# Customizing WPGraphiQL

WPGraphiQL was built with extendability in mind.

## Local Development

Below are some helpful commands for working on WPGraphiQL locally.

- `npm install` This will install the JavaScript dependencies needed to work on the plugin
- `npm run build` This will build the scripts into the files that are enqueued
- `npm run start` This will start the scripts in develop mode. You can make code changes and refresh your browser to see the changes.
- `npm run wp-env start` This will start a WordPress environment with WPGraphQL active
- `npm run test:e2e-debug` This will run the end to end tests. Must run `npm run wp-env start` before running this
- `npm run wp-env stop` This will stop the WordPress environment.

## PHP Hooks and Filters

Below you will find documentation about the PHP hooks and filters available to be used to customize
the WPGraphiQL tooling.

### enqueue\_graphiql\_extension

The `enqueue_graphiql_extension` action is run when the GraphiQL IDE page is loading in the admin.

If you're writing JavaScript code that needs to be loaded on the GraphiQL screen, hook into this action.

For example:

```php
// This will only enqueue the script when the GraphiQL app is loaded. Hook into this action
// to make sure your scripts aren't loading on pages they shouldn't be loading for.
add_action( 'enqueue_graphiql_extension', function() {

    wp_enqueue_script(
        'name-of-your-script', // replace this with the handle of your script
        'path/to/your/script.js', // replace this with the path to your script
        [ 'wp-graphiql', 'wp-graphiql-app', 'any-other-dependencies-you-need' ], // include at least the first 2 to ensure wp-graphiql is loaded before your script
        'version-string', // replace with your version. (v1.0.1, etc)
        true // Whether to load in the footer. Leave true
	);

} );
```

## JavaScript Hooks and Filters

WPGraphiQL is a [React](https://reactjs.org/) application. It makes use of [React Components](https://reactjs.org/docs/components-and-props.html)
and [React Context](https://reactjs.org/docs/context.html).

The codebase provides several [hooks and filters](https://www.ibenic.com/use-wordpress-hooks-package-javascript-apps/),
which allow plugin developers the chance to write JavaScript code that can modify the user interface
and interact with the application state.

WPGraphQL ships with 3 features (Auth Switch, Fullscreen Toggle, and Query Composer) that were built
using the extension architecture. The code can serve as an example for how you might approach building
an extension.

You can find the code for these extensions [here](https://github.com/wp-graphql/wp-graphql/tree/develop/packages/).

Below, is documentation on the hooks and filters provided by the codebase:

### graphiql\_app

This filter can be used to wrap the App with Providers.

### graphiql\_query\_params\_provider\_config

This filter can be used to modify the config for the Query Params managed in AppContext.

### graphiql\_app\_context

This filter can be used to modify the default values of the AppContext Provider

### graphiql\_auth\_switch\_context\_default\_value

This filter can be used to modify the default values of the AuthSwitchContext Provider

### graphiql\_explorer\_context\_default\_value

This filter can be used to modify the default values of the ExplorerContext Provider

### graphiql\_fetcher

This filter is provided to allow overriding the default fetcher that's used by GraphiQL.

This can be seen in use by the Auth Switch feature, which changes the fetcher to be a public fetcher
or an authenticated fetcher.

### graphiql\_before\_graphiql

This filter can be used to provide elements *before* the GraphiQL IDE is rendered.

For example, the Query Composer panel is rendered at this hook.

### graphiql\_after\_graphiql

This filter can be used to provide elements *after* the GraphiQL IDE is rendered.

### graphiql\_context\_default\_value

This filter can be used to modify the default values of the GraphiQL Context Provider

### graphiql\_toolbar\_buttons

This filter can be used to modify the buttons in the GraphiQL IDE toolbar

### graphiql\_toolbar\_before\_buttons

This filter can be used to add elements *before* the Buttons are rendered in the GraphiQL IDE toolbar

### graphiql\_toolbar\_after\_buttons

This filter can be used to add elements *after* the Buttons are rendered in the GraphiQL IDE toolbar

### graphiql\_explorer\_operation\_action\_menu\_items

This filter can be used to add action items to the GraphiQL Query Composer for each operation

### graphiql\_router\_screens

This filter can be used to modify the "screens" that are available to the app.

The default Screens are the GraphiQL IDE screen and the Help screen.

In the future, there will be more "screens", and plugins can provide their own Screens that
have access to the same Context APIs as the rest of the app.
