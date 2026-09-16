---
uri: "/docs/security/"
title: "Security"
---

WPGraphQL has been developed with security in mind. Below, are details on some of the ways WPGraphQL works to allow you to use the benefits of GraphQL while keeping your data secure.

## Reporting a Vulnerability

If you believe you've discovered a security vulnerability, please email info@wpgraphql.com with details and steps to reproduce.

## Setup Wizard

The setup wizard walks through the WPGraphQL settings that affect who can use your GraphQL API, how much work a single request can ask for, and what debugging information responses include. For each setting it explains what you gain and what it costs, so you can choose what fits your site. Open it from **GraphQL > Setup Wizard**.

Every setting starts at the value your site uses today, and nothing changes until you save on the last step. Skipping the wizard changes no settings.

Administrators are invited to run the wizard, with a notice on WPGraphQL screens and the Plugins screen, until they complete or skip it. When an update adds a setting to the wizard, the invitation shows again and the new setting is marked as new.

### Adding settings to the setup wizard

Plugins can add their own settings to the wizard. Add a `setup_wizard` key to the config passed to `register_graphql_settings_field()`:

```php
add_action( 'graphql_register_settings', function () {
	register_graphql_settings_section(
		'my_plugin_settings',
		[ 'title' => __( 'My Plugin', 'my-plugin' ) ]
	);

	register_graphql_settings_field(
		'my_plugin_settings',
		[
			'name'         => 'public_widgets_enabled',
			'label'        => __( 'Show widgets to logged-out visitors', 'my-plugin' ),
			'type'         => 'checkbox',
			'default'      => 'off',
			'setup_wizard' => [
				'step'     => 'access',
				'benefits' => [ __( 'Public front ends can query widgets.', 'my-plugin' ) ],
				'costs'    => [ __( 'Widget content is readable by anyone.', 'my-plugin' ) ],
			],
		]
	);
} );
```

`setup_wizard` can be `true`, or an array with any of these keys:

- `step`: the step to show the setting in. Core registers `access`, `request-limits` and `diagnostics`. A setting without a registered step is shown in a step named after its settings section.
- `label` and `description`: override the label and description from the settings page.
- `benefits` and `costs`: lists of what turning the setting on gains and costs.
- `order`: the setting's position in the wizard.

The wizard supports the `checkbox`, `number`, `select`, `radio`, `user_role_select`, `text`, `url` and `textarea` field types. A `disabled` field is shown but can't be changed.

To register a step of your own, use `register_graphql_setup_wizard_step()`. Steps are shown in ascending `order`, and core's steps use 10, 20 and 30:

```php
register_graphql_setup_wizard_step(
	'my-plugin',
	[
		'title'       => __( 'My Plugin', 'my-plugin' ),
		'description' => __( 'Choose how My Plugin exposes data.', 'my-plugin' ),
		'order'       => 40,
	]
);
```

### Settings that depend on another setting

When a setting only applies while a checkbox is on, set `depends_on` to the name of that checkbox field in the same section. The setting is hidden while the checkbox is off, on the settings page and (when both settings are in it) in the setup wizard. Its saved value is kept:

```php
register_graphql_settings_field(
	'my_plugin_settings',
	[
		'name'       => 'public_widgets_limit',
		'label'      => __( 'Maximum widgets per request', 'my-plugin' ),
		'type'       => 'number',
		'default'    => 10,
		'depends_on' => 'public_widgets_enabled',
	]
);
```

## Introspection Disabled by Default

One feature of GraphQL is Schema Introspection, which means the GraphQL Schema itself can be queried. This is a feature used by tools such as GraphiQL and others.

It's possible that exposing the Schema publicly (in some cases) can leak information about the system that's not intended to be known publicly.

WPGraphQL disables public Schema Introspection by default, but for users that want to enable it, it can be enabled with one-click from the GraphQL > Settings page in the WordPress dashboard.

## Limiting Query Depth

GraphQL lets a client nest fields inside fields, for example posts, then each post's author, then each author's posts, and so on. Every level adds work for the server, so a deeply nested query sent to a public endpoint can use a lot of memory and CPU.

WPGraphQL can reject queries that are nested deeper than a limit you choose. On the GraphQL > Settings page:

- **Enable Query Depth Limiting** turns the limit on.
- **Max Depth to allow for GraphQL Queries** sets how many levels are allowed. The default is 15.

A query deeper than the limit is rejected before it runs, with an error like `The server administrator has limited the max query depth to 15, but the requested query has 18 levels.`

### Defaults

New installs have query depth limiting turned on with a max depth of 15. Sites that installed WPGraphQL before this default was added keep the setting they already had, which is off unless someone turned it on. If your site is one of those, we recommend turning it on. A limit of 15 leaves room for typical queries. If a query your site depends on is deeper, raise the Max Depth setting or use the filter below.

### Introspection queries

Queries that only ask for the schema (`__schema` or `__type`) are not limited. Tools such as the GraphiQL IDE and code generators rely on the standard introspection query, which is deeper than most content queries, and its shape is fixed by the GraphQL spec. Whether the public can run introspection is controlled by its own setting (see above).

This only applies when every field at the root of the operation is `__schema`, `__type` or `__typename`. A query that asks for content next to an introspection field is limited like any other query.

### Changing the limit in code

The `graphql_query_depth_max` filter sets the max depth for the current request. It receives the value from the settings, the Max Depth when limiting is enabled or `0` when it's disabled. Return `0` to allow any depth, or a positive number to set a limit. A positive number applies a limit even when the setting is disabled.

For example, to allow deeper queries for administrators while keeping the configured limit for everyone else:

```php
add_filter( 'graphql_query_depth_max', function ( $max_depth ) {
	if ( current_user_can( 'manage_options' ) ) {
		return 30;
	}

	return $max_depth;
} );
```

Base the decision on a capability, as above, rather than only on whether the user is logged in. On sites that allow anyone to register, a logged-in user isn't necessarily a trusted one.

### What depth limiting does not cover

Depth limiting caps how deeply a query is nested, not how wide it is. A query within the limit can still ask for many items at each level or repeat a field many times using aliases. WPGraphQL also caps how many items a connection returns per page (100 for most connections, adjustable with the `graphql_connection_max_query_amount` filter) and lets you limit or disable batch queries on the GraphQL > Settings page. Cost-based query complexity limiting is tracked in [#3922](https://github.com/wp-graphql/wp-graphql/issues/3922).

## CSRF Protection

WPGraphQL implements multiple layers of protection against Cross-Site Request Forgery (CSRF) attacks:

### CORS Headers

WPGraphQL sets `Access-Control-Allow-Origin: *` without `Access-Control-Allow-Credentials`, which prevents browsers from sending cookies with cross-origin JavaScript requests. This blocks most CSRF attack vectors.

### Nonce Verification

For cookie-authenticated requests, WPGraphQL requires a valid WordPress nonce. This provides defense-in-depth protection, particularly against form-based CSRF attacks that bypass CORS.

**How it works:**
- Requests with an `Authorization` header (JWT, Application Passwords, etc.) do NOT require a nonce
- Requests using cookie authentication SHOULD include a nonce via `X-WP-Nonce` header or `_wpnonce` parameter
- **No nonce provided**: Request is downgraded to guest/unauthenticated (executes but `viewer` is `null`)
- **"Falsy" nonce** (`null`, `undefined`, empty string): Treated as no nonce, downgraded to guest
- **Invalid nonce provided** (real but wrong/expired): Request fails with HTTP 403 and error `"Cookie nonce is invalid"`

This matches the security model of the WordPress REST API.

### For Developers

If you're building a browser-based application that uses cookie authentication:

```javascript
// Include nonce in your GraphQL requests
fetch('/graphql', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    credentials: 'same-origin',
    body: JSON.stringify({ query: '{ viewer { name } }' }),
});
```

See [Authentication and Authorization](/docs/authentication-and-authorization/) for detailed guidance on obtaining and using nonces.

## Access Control Rights

WordPress core has many access control rights established, and WPGraphQL follows them. Anything that is publicly exposed by WordPress is publicly exposed by WPGraphQL, and any data that requires a user to be authenticated to WordPress to see, WPGraphQL also requires requests to be properly authenticated for users to see.

For example, in WordPress core, users that have not published posts are only visible within the WordPress dashboard. There is no public URL for unpublished authors. WPGraphQL respects this, and queries for users will not include users without published posts. Properly authenticated requests from a user that has the capability to list users will be able to see unpublished authors, much like they would be able to within the WordPress dashboard.

## Model Layer

To help facilitate what data is publicly exposed and what data is considered private, WPGraphQL has a "Model Layer". Each type of object (Posts, Terms, Users, Comments, etc) have a WPGraphQL Model that is responsible for permission checks. Anytime an object is asked for from the Graph, the Model determines if the object is allowed to be returned to the requesting user, and if so, what specific fields can be returned. The Model Layer takes into consideration many things when determining if the object should be considered public or private.

Some things the [Model Layer](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql/src/Model) will consider before returning an object:

- Is the Request for data authenticated or public?
- What is the state of the object being requested (is it published, draft, etc)?
- Who is the owner of the object? ex: is the author of the post the same user requesting it in GraphQL?
- Does the object belong to a private Type (private post\_type, for example?)

If an object is determined to be allowed to be returned to the requesting user, further checks are done on the fields being requested.

For example, a user with published posts is considered a public entity, but the email address for that user is still considered a non-public field and requires specific permission to access.

Read more about the WPGraphQL Model Layer.

## Authentication and Authorization

- **Authentication**: the process of verifying who you are (logging in)
- **Authorization**: the process of verifying that you have access to something – (the ability to view/change private data)

### A quick word about GraphQL Mutations vs Queries

From a technical perspective, the only differences between GraphQL Queries and Mutations is the `mutation` keyword, and the GraphQL spec requires mutations to be processed synchronously, where queries can be processed Async (in environments that support it).

Other than that, Queries and Mutations are the same, they’re both just strings that map to functions.

Now that we’re clear on Queries vs. Mutations (both are just maps to functions), authentication & authorization is left up to the application layer, not the GraphQL API layer, although some mechanisms in GraphQL can help facilitate these processes.

### Authentication with WPGraphQL

Since WPGraphQL is a WordPress plugin that adheres largely to common WordPress practices, there are many ways to make authenticated WPGraphQL requests.

For remote HTTP requests to the `/graphql` endpoint, existing authentication plugins *should* work fine. These plugins make use of sending data in the Headers of requests and validating the credentials and setting the user before execution of the API request is returned:

- https://github.com/wp-graphql/wp-graphql-jwt-authentication
- https://github.com/WP-API/Basic-Auth (even though it’s labeled for the REST API, it works well with WPGraphQL – but not recommended for non-SSL connections)
- https://github.com/WP-API/OAuth1 (labeled for use with the WP REST API, but works well with WPGraphQL)

If the remote request is within the WordPress admin, such as the WPGraphiQL plugin, you can use the existing Auth nonce as seen in action [here](https://github.com/wp-graphql/wp-graphiql/blob/main/packages/graphiql-auth-switch).

For non-remote requests (PHP function calls), if the context of the request is already authenticated, such as an Admin page in the WordPress dashboard, existing WordPress authentication can be used, taking advantage of the existing session. For example, if you wanted to use a GraphQL query to populate a dashboard page, you could send your query using the `graphql()` helper, and since the request is already authenticated, GraphQL will execute with the current user set, and will resolve fields that the user has permission to resolve.

### Authorization with WPGraphQL

Since WPGraphQL is built as a WordPress plugin, it makes use of WordPress core methods to determine the current user for the request, and execute with that context.

The mutations that WPGraphQL provide out of the box attempt to adhere to best practices in regards to respecting user roles and capabilities. Whether the mutation is creating, updating or deleting content, WPGraphQL checks for capabilities before executing the mutation.

For example, any mutation that would create a `post` will first check to make sure the current user has proper capabilities to create a `post`.

Mutations are not alone when it comes to checking capabilities. Some queries expose potentially sensitive data, such as the email address field in `generalSettings`. By default, this field will only resolve if the request is authenticated, meaning that the value of the email address is only exposed to logged in users.

A public, non-authenticated request would return a null value for the field and would return an error message in the GraphQL response. However, it wouldn’t block the execution of the entire GraphQL request, just that field. So, if the request had a mix of publicly allowed fields and private fields, GraphQL would still execute the public data. For example, trying a query like:

```graphql
{
  generalSettings {
    title
    email
  }
}
```

But the results would return `null` for the email field, like so:

```graphql
{
  \"data\": {
    \"generalSettings\": {
      \"title\": \"WPGraphQL.com\",
      \"email\": null
    }
  }
}
```
