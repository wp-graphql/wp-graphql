# On-Demand Revalidation

Static frontends (such as Next.js, Astro, Nuxt, or any framework with Incremental Static Regeneration) often poll their backend on a fixed interval to check whether content has changed. Polling is wasteful when nothing has changed, and slow when something has.

A better pattern is **on-demand revalidation**: when a piece of content changes in WordPress, the WPGraphQL Smart Cache plugin emits a [`graphql_purge`](#the-graphql_purge-action) action, and you can respond to that action by sending a webhook to your frontend telling it exactly which page(s) to rebuild.

This document shows how to wire that up. The running example uses Next.js, but the same pattern works for any framework that exposes a revalidation API.

- [The `graphql_purge` action](#the-graphql_purge-action)
- [Key formats](#key-formats)
- [Translating keys to URL paths](#translating-keys-to-url-paths)
- [Batching events per request](#batching-events-per-request)
- [Authenticating the webhook](#authenticating-the-webhook)
- [Full example](#full-example)
- [Related hooks](#related-hooks)

## The `graphql_purge` action

Every time WPGraphQL Smart Cache decides a cache entry should be invalidated, it fires:

```php
do_action( 'graphql_purge', $key, $event, $hostname );
```

| Argument | Type | Example | Description |
|---|---|---|---|
| `$key` | `string` | `UG9zdDoxMjM=` | The cache key being purged. See [Key formats](#key-formats). |
| `$event` | `string` | `post_UPDATE` | A short label describing what triggered the purge. Useful for logging/debugging. |
| `$hostname` | `string` | `example.com/graphql` | The GraphQL endpoint host (no scheme). |

A single content change typically fires the action multiple times — for example, updating a published post fires once for the post's node ID and once for `skipped:post`. See [Batching events per request](#batching-events-per-request) for how to handle this efficiently.

For the full list of events Smart Cache tracks (publish, update, delete, term assignment, comment transitions, menu changes, etc.), see [Cache Invalidation](./cache-invalidation.md).

## Key formats

The `$key` argument is one of the following shapes:

| Shape | Example | Meaning |
|---|---|---|
| Relay global ID | `UG9zdDoxMjM=` | An individual node — base64 of `<type>:<database_id>` (e.g., `post:123`, `term:45`, `user:7`). Decode with `GraphQLRelay\Relay::fromGlobalId()`. |
| `list:<type>` | `list:post`, `list:category`, `list:menu` | A list/archive cache for that content type. Fires when a node is added or removed from that list (e.g., publishing a post fires `list:post`). |
| `skipped:<type>` | `skipped:post`, `skipped:user` | Fired alongside individual node IDs to invalidate caches whose `X-GraphQL-Keys` header was truncated due to header size limits. Read more in [Cache Invalidation](./cache-invalidation.md#hold-up-whats-the-deal-with-the-skippedtype_name-thing). |
| `graphql:Query` | `graphql:Query` | Fired only for "purge all" — for example, when an admin clicks **Purge Cache Now**. |

For an on-demand revalidation handler, the keys you most often act on are the **Relay global IDs** (to revalidate a specific page) and the **`list:<type>` keys** (to revalidate the corresponding archive/index). `skipped:` and `graphql:Query` typically map to "revalidate everything of this type" or "revalidate the whole site" — handle those cautiously.

## Translating keys to URL paths

Your frontend doesn't care about Smart Cache's internal keys; it cares about URL paths to revalidate. Translate the key into a path on the WordPress side using the existing WordPress APIs (`get_permalink()`, `get_term_link()`, etc.):

```php
use GraphQLRelay\Relay;

/**
 * Resolve a Smart Cache key to a frontend path.
 *
 * Returns null when the key has no direct page representation
 * (e.g. skipped:* or list:* keys — handle those separately).
 */
function my_smart_cache_key_to_path( string $key ): ?string {
    if ( strpos( $key, 'list:' ) === 0 || strpos( $key, 'skipped:' ) === 0 || $key === 'graphql:Query' ) {
        return null;
    }

    $decoded = Relay::fromGlobalId( $key );
    $type    = $decoded['type'] ?? null;
    $id      = absint( $decoded['id'] ?? 0 );

    if ( ! $type || ! $id ) {
        return null;
    }

    switch ( $type ) {
        case 'post':
            $permalink = get_permalink( $id );
            break;
        case 'term':
            $term      = get_term( $id );
            $permalink = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : null;
            break;
        case 'user':
            $user      = get_user_by( 'id', $id );
            $permalink = $user instanceof WP_User ? get_author_posts_url( $user->ID ) : null;
            break;
        default:
            $permalink = null;
    }

    if ( ! is_string( $permalink ) ) {
        return null;
    }

    $path = wp_parse_url( $permalink, PHP_URL_PATH );
    return is_string( $path ) ? $path : null;
}
```

You can extend the `switch` statement to handle additional node types (custom post types, custom taxonomies, etc.) and to map `list:<type>` keys to your archive paths (e.g., `list:post` → `/blog/`).

## Batching events per request

A single editor action — saving a post, assigning a term, deleting a comment — typically fires `graphql_purge` multiple times in the same request. Sending one HTTP webhook per event is wasteful and stresses your frontend.

Collect events during the request and flush a single webhook on `shutdown`:

```php
add_action( 'graphql_purge', static function ( string $key, string $event, string $hostname ): void {
    static $registered = false;

    $path = my_smart_cache_key_to_path( $key );
    if ( ! $path ) {
        return;
    }

    $GLOBALS['my_revalidate_paths'][ $path ] = true;

    if ( ! $registered ) {
        add_action( 'shutdown', 'my_revalidate_flush', 99 );
        $registered = true;
    }
}, 10, 3 );

function my_revalidate_flush(): void {
    $paths = array_keys( $GLOBALS['my_revalidate_paths'] ?? [] );
    if ( empty( $paths ) ) {
        return;
    }
    $GLOBALS['my_revalidate_paths'] = [];

    // ... send webhook (see next section)
}
```

Using the path itself as the key in `$GLOBALS['my_revalidate_paths']` automatically de-duplicates repeated events for the same page.

## Authenticating the webhook

Your frontend's revalidate endpoint should be protected — anyone who can call it can force your site to rebuild every page. Use a **shared secret in a request header**, never in the query string (where it can leak into access logs and `Referer` headers).

Generate a secret once (any random string with sufficient entropy):

```bash
openssl rand -hex 32
```

Store it as a constant in `wp-config.php`:

```php
define( 'MY_REVALIDATE_SECRET', 'your-generated-secret-here' );
define( 'MY_REVALIDATE_URL',    'https://example.com/api/revalidate' );
```

And configure the same value in your frontend's environment (e.g., `WPGRAPHQL_REVALIDATE_SECRET` in a Next.js `.env.local`).

## Full example

Drop this into a small mu-plugin or site-specific plugin. Together with the helper from [Translating keys to URL paths](#translating-keys-to-url-paths), it implements end-to-end on-demand revalidation:

```php
<?php
/**
 * Plugin Name: My On-Demand Revalidation
 */

use GraphQLRelay\Relay;

if ( ! defined( 'MY_REVALIDATE_URL' ) || ! defined( 'MY_REVALIDATE_SECRET' ) ) {
    return;
}

// Collect paths to revalidate during the request.
add_action( 'graphql_purge', static function ( string $key, string $event, string $hostname ): void {
    static $registered = false;

    $path = my_smart_cache_key_to_path( $key );
    if ( ! $path ) {
        return;
    }

    $GLOBALS['my_revalidate_paths'][ $path ] = true;

    if ( ! $registered ) {
        add_action( 'shutdown', 'my_revalidate_flush', 99 );
        $registered = true;
    }
}, 10, 3 );

// Flush all collected paths in a single webhook on shutdown.
function my_revalidate_flush(): void {
    $paths = array_keys( $GLOBALS['my_revalidate_paths'] ?? [] );
    if ( empty( $paths ) ) {
        return;
    }
    $GLOBALS['my_revalidate_paths'] = [];

    wp_remote_post(
        MY_REVALIDATE_URL,
        [
            'method'   => 'POST',
            'blocking' => false,
            'headers'  => [
                'Content-Type'                    => 'application/json',
                'X-WPGraphQL-Revalidate-Secret'   => MY_REVALIDATE_SECRET,
            ],
            'body'     => wp_json_encode( [ 'paths' => $paths ] ),
            'timeout'  => 5,
        ]
    );
}
```

### A note on reliability

`wp_remote_post()` with `'blocking' => false` is fire-and-forget — if the frontend is down or the request fails, the invalidation is silently lost and the affected page will stay stale until your next deploy or full purge.

For a production setup, consider:

- Logging failed webhook attempts (drop `'blocking' => false` and check the response)
- Queueing the webhook with [Action Scheduler](https://actionscheduler.org/) so failed attempts can be retried
- Periodically sending a "purge all" / full revalidation as a safety net

## Related hooks

A few other hooks may be useful when building custom invalidation handlers:

| Hook | Type | Purpose |
|---|---|---|
| `wpgraphql_cache_purge_all` | action | Fired when an admin clicks **Purge Cache Now**. Use this to trigger a full revalidation of your frontend. |
| `wpgraphql_cache_purge_nodes` | action | `do_action( 'wpgraphql_cache_purge_nodes', $key, $nodes )` — fires alongside `graphql_purge` when Smart Cache has resolved node data for the key. Useful when you want metadata about the affected nodes, not just the key. |
| `graphql_cache_invalidation_init` | action | Fires at the end of the Invalidation class's `init()` method and passes the instance. Use this to register additional listeners against Smart Cache internals. |

----

## 👉 Up Next:

- [Cache Invalidation](./cache-invalidation.md)
- [Extending / Customizing](./extending.md)
- [Network Cache](./network-cache.md)
