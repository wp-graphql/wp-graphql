# Extensions

This directory contains the React app that is enqueue'd in the WordPress admin to display a list of WPGraphQL extensions.

It reads the list of extensions from the localized json under the name `wpgraphqlExtensions`.

You can read more about the extensions list in the [Admin Extensions README](../../src/Admin/Extensions/README.md).

## Development

To start the development server, run:

```shell
$ npm install && npm start
```

This will run wp-scripts start and start the development server.

## Production

To build the production version of the app, run:

```shell
$ npm run build
```

This will run wp-scripts build and create a production-ready build of the app.

## Deployment

The production build of the extensions page is built in a GitHub Action and bundled with the plugin during each release.
