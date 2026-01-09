# Auth Switch

The "auth switch" feature allows users to use GraphiQL in an authenticated or non-authenticated state.

This "extension" hooks into:

- `graphiql_app`: to provide wrap the app with a custom Context Provider
- `graphiql_toolbar_before_buttons`: to provide the avatar button in the toolbar
- `graphiql_fetcher`: to customize behavior of how GraphiQL fetches the requests

## Usage

When a user clicks the avatar in the GraphiQL Toolbar, it toggles the fetcher to either execute with credentials (authenticated) or without credentials (public / non-authenticated).

The default state is public.

![Screen Recording of WPGraphiQL 2 in action](../../../img/graphiql-toggle-auth.gif)
