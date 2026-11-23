---
uri: "/docs/request-extensions/"
title: "Request Extensions"
---

Some things a client tells the server aren't part of any one field: which post it's previewing, a password that unlocks protected content, the locale to answer in, an A/B test variant. They describe the whole request, so they travel next to the operation rather than inside it, as a namespace in the request `extensions` object and, optionally, as an HTTP header.

WPGraphQL's [preview context](/docs/previews/) is one of these, carried by the `X-GraphQL-Preview` header or `extensions.preview`. Extensions can register their own namespaces the same way, with their own header, typed members and cache rule, and can add members to existing namespaces, `preview` included.

## Registering a namespace

```php
add_action(
	'graphql_register_request_extensions',
	static function () {
		register_graphql_request_extension(
			'postPassword',
			[
				'description' => 'Passwords that unlock password-protected posts.',
				'header'      => 'X-GraphQL-Post-Password',
				'cache'       => 'no-store',
				'members'     => [
					'postId'   => [
						'type'        => 'integer',
						'description' => 'The database ID of the protected post.',
						'sanitize'    => static function ( $value ) {
							return $value > 0 ? $value : null;
						},
					],
					'password' => [
						'type'        => 'string',
						'description' => 'The password for the post.',
						'sanitize'    => static function ( $value ) {
							return '' !== $value ? $value : null;
						},
					],
				],
			]
		);
	}
);
```

A client can then send the namespace either way:

```http
X-GraphQL-Post-Password: post_id=123, password="correct horse"
```

```jsonc
{
  "query": "{ post(id: 123, idType: DATABASE_ID) { title content } }",
  "extensions": {
    "postPassword": { "postId": 123, "password": "correct horse" }
  }
}
```

And a resolver reads it from the `AppContext`:

```php
$post_password = $context->get_request_extension( 'postPassword' );

if ( null !== $post_password && $post_password['postId'] === $post->databaseId ) {
	// Check $post_password['password'] against the post.
}
```

`get_request_extension()` returns every registered member of the namespace (`null` for one that wasn't sent or was invalid), or `null` when the request doesn't carry the namespace at all.

The namespace config:

- **`header`** (optional) is the name of the HTTP header that carries the namespace, made of letters, digits and hyphens. Without one, the namespace is only read from the request `extensions`. A namespace can't use a header another namespace already uses, or one that WordPress, browsers or proxies already use for something else: `Accept`, `Authorization`, `Cache-Control`, `Connection`, `Content-Length`, `Content-Type`, `Cookie`, `Forwarded`, `Host`, `If-Modified-Since`, `If-None-Match`, `Origin`, `Pragma`, `Referer`, `User-Agent`, `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Proto` and `X-WP-Nonce`, or any header starting with `Access-Control-`, `Proxy-` or `Sec-`.
- **`cache`** (optional) is `vary` (the default) or `no-store`. Either way, the header is added to the response's `Vary` header, so caches keep requests with and without it apart. With `no-store`, a response to a request that carries the namespace is also sent with `Cache-Control: no-store, private`, so no cache stores it at all. Use `no-store` for anything secret or specific to one client, like a password: the unlocked response must not be cached and served to someone else.
- **`members`** are the values the namespace carries, keyed by camelCase name. They can also be registered separately (see below).
- **`description`** (optional) says what the namespace is for.

The name must be camelCase (a lowercase letter, then letters and digits), since it's the key in `extensions`. The header is also added to `Access-Control-Allow-Headers`, so cross-origin clients can send it.

## Members

Each member declares how it's sent and checked:

- **`type`** (required) is one of `integer`, `string`, `boolean` or `inner-list`. An `inner-list` also declares its item type in `items` (`integer`, `string` or `boolean`), for example `[ 'type' => 'inner-list', 'items' => 'integer' ]`, sent as `post_ids=(1 2)` in the header or `[1, 2]` in JSON.
- **`sanitize`** (required) runs after the type check and receives the type-checked value. Whatever it returns is what resolvers get; return `null` to treat the value as absent. The values come from the client, so treat them as untrusted.
- **`header_key`** (optional) overrides the header key, which is otherwise derived from the name: `postId` is sent as `post_id`. Header keys are lowercase, as Structured Fields require.
- **`description`** (optional) says what the member is for.

Values must match the declared type exactly on both transports, and nothing is coerced: an `integer` member doesn't accept `"123"`, and a `string` member doesn't accept a bare header token like `secret` (send `"secret"`). A value of the wrong type is discarded, with a debug notice naming the member and the expected type. Keys that no member registered are dropped.

To add a member to a namespace someone else registered, or to core's `preview`:

```php
register_graphql_request_extension_member(
	'postPassword',
	'remember',
	[
		'type'     => 'boolean',
		'sanitize' => static fn ( $value ) => $value,
	]
);
```

A member can be registered before its namespace is; it's added when the namespace registers, after the namespace's own `members`. So if it has the same name or header key as one of them, the namespace's own member is kept, since whoever registers a namespace defines its shape, and a debug notice says the other was refused. If the namespace is never registered, a debug notice says so. For `preview`, `register_graphql_preview_context_member()` is the same as passing `'preview'` here; see [Adding your own preview context members](/docs/previews/#adding-your-own-preview-context-members).

## How a request is read

- **The header wins.** When the namespace has a header and the request sends it, its value is parsed as an [RFC 8941 Structured Field](https://www.rfc-editor.org/rfc/rfc8941) dictionary, and the request `extensions` object is ignored. The header is parsed as sent, and each value is then passed to its member's sanitizer.
- **A header that can't be used falls back.** As the RFC requires, a header that fails to parse (a trailing comma, or a camelCase key) is discarded in its entirety. A header that parses but carries none of the namespace's keys, or none of the right type, is ignored too. In both cases the `extensions` object applies, and a debug notice says why the header was ignored.
- **Headers are read from HTTP requests only.** A query run from PHP with `graphql()` passes its request extensions in `extensions`, and never reads headers, even when it runs during an HTTP request that sent them (a resolver running a nested query, say). Otherwise a query run from PHP would quietly pick up whatever the surrounding request carried:

  ```php
  graphql(
  	[
  		'query'      => '{ post(id: 123, idType: DATABASE_ID) { title content } }',
  		'extensions' => [
  			'postPassword' => [ 'postId' => 123, 'password' => 'correct horse' ],
  		],
  	]
  );
  ```

  To give a nested query the same values as the request it runs in, pass them on: `'extensions' => [ 'postPassword' => $context->get_request_extension( 'postPassword' ) ]`. The values are checked against the members again, so this works as long as each member's sanitizer returns a value of the member's declared type.

- **Batch requests use the header only.** An `extensions` object belongs to one operation, while a request extension applies to the whole request, so in a batch the header applies to every operation and a per-operation `extensions` object is ignored, with a debug notice.
- **GET requests** work with both transports. With the query-string form of `extensions`, the values become part of the URL (and any access logs); a header keeps them out of the URL.

With `GRAPHQL_DEBUG` enabled, each of these cases adds a debug notice to the response: `REQUEST_EXTENSION_MALFORMED` (a header that failed to parse), `REQUEST_EXTENSION_UNRECOGNIZED` (a header with none of the namespace's keys), `REQUEST_EXTENSION_MEMBER_INVALID` (a value of the wrong type) and `REQUEST_EXTENSION_IGNORED` (an `extensions` object in a batch). The `preview` namespace keeps its own `PREVIEW_CONTEXT_*` codes for the same cases.

## Registration rules

- **Register on the `graphql_register_request_extensions` action, or on `init` or earlier.** The action fires the first time a request reads the registry, and receives the `RequestExtensionRegistry`. The registration functions can be called before it fires: they wait for it. A request reads the registry before it builds the schema, so a namespace or member registered on `graphql_register_types`, or later, isn't read for that request, and a debug notice says so.
- **Core's namespaces and members are reserved.** `preview` can't be registered again, so its header and its `no-store` rule can't change, and its members (`databaseId`, `revisionDatabaseId`, `featuredImageDatabaseId`, `postFormat`, `nonce`, and their header keys) can't be registered or replaced.
- **The first registration wins.** If two extensions register the same namespace, header, member name or header key, the second registration is refused, so the outcome never depends on plugin load order. The one exception is a member registered for a namespace before the namespace itself: the namespace's own `members` are registered first, so they win over it.
- **Every refusal says why**, in a debug notice (with `GRAPHQL_DEBUG` enabled): `REQUEST_EXTENSION_NOT_REGISTERED` for a namespace, `REQUEST_EXTENSION_MEMBER_NOT_REGISTERED` for a member, and `PREVIEW_CONTEXT_MEMBER_NOT_REGISTERED` for a member of `preview`.

Registering a namespace or a member grants no access by itself. It only gets the client's values to your resolvers: deciding what they unlock, and for whom, is up to the code that reads them.
