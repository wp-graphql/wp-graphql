<?php
/**
 * Plugin Name: WPGraphQL IDE Cache Inspector
 * Description: Cache-wide inventory for the WPGraphQL Smart Cache object cache. Lists every cached entry with size and TTL, supports per-entry and bulk purge.
 *
 * Lives inside wp-graphql-ide so we can iterate quickly. The renderer talks
 * only to the routes registered below; lifting it into wp-graphql-smart-cache
 * later is a directory copy plus a namespace swap on the REST routes.
 */

declare(strict_types = 1);

namespace WPGraphQLIDE\CacheInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REST_NAMESPACE         = 'wpgraphql-cache-inspector/v1';
const DEFAULT_TRANSIENT_PREFIX = 'gql_cache_';
const ENTRY_LIMIT            = 500;
const BULK_PURGE_LIMIT       = 100;

// Cache keys come in two shapes:
//   * Response caches — SHA-256 hex (64 chars), one per cached operation.
//   * Trackers — short identifiers smart-cache writes for purge bookkeeping
//     (`list:post`, `cG9zdDoxMA==` for `post:10`, etc.). Used to invalidate
//     groups of responses when their underlying data changes.
const CACHE_KEY_PATTERN = '^([a-f0-9]{1,64}|[a-zA-Z0-9+/=:_\-]{1,128})$';

/**
 * Returns the transient prefix Smart Cache writes under. Filterable so
 * downstream (or a future wp-graphql-smart-cache home) can rename
 * without forking this file.
 *
 * @return string
 */
function transient_prefix(): string {
	/**
	 * Filters the prefix used to identify Smart Cache transients.
	 *
	 * @param string $prefix Default `gql_cache_`.
	 */
	$prefix = apply_filters( 'wpgraphql_ide_cache_inspector_prefix', DEFAULT_TRANSIENT_PREFIX );
	return is_string( $prefix ) && '' !== $prefix ? $prefix : DEFAULT_TRANSIENT_PREFIX;
}

define( 'WPGRAPHQL_IDE_CACHE_INSPECTOR_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_CACHE_INSPECTOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the script for the Cache Inspector.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_CACHE_INSPECTOR_DIR_PATH . 'build/cache-inspector.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'cache-inspector',
		WPGRAPHQL_IDE_CACHE_INSPECTOR_URL . 'build/cache-inspector.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);

	wp_localize_script(
		'cache-inspector',
		'WPGRAPHQL_IDE_CACHE_INSPECTOR',
		[
			'restUrl'   => esc_url_raw( rest_url( REST_NAMESPACE ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
		]
	);
}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\enqueue_assets' );

/**
 * Registers the REST routes that back the inspector.
 *
 * @return void
 */
function register_rest_routes(): void {
	register_rest_route(
		REST_NAMESPACE,
		'/entries',
		[
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\rest_list_entries',
			'permission_callback' => __NAMESPACE__ . '\rest_permission_check',
		]
	);

	register_rest_route(
		REST_NAMESPACE,
		'/purge',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_purge_entry',
			'permission_callback' => __NAMESPACE__ . '\rest_permission_check',
			'args'                => [
				'cacheKey' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'pattern'           => CACHE_KEY_PATTERN,
				],
			],
		]
	);

	register_rest_route(
		REST_NAMESPACE,
		'/purge-bulk',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_purge_bulk',
			'permission_callback' => __NAMESPACE__ . '\rest_permission_check',
			'args'                => [
				'cacheKeys' => [
					'type'     => 'array',
					'required' => true,
					'items'    => [
						'type'    => 'string',
						'pattern' => CACHE_KEY_PATTERN,
					],
				],
			],
		]
	);

	register_rest_route(
		REST_NAMESPACE,
		'/purge-all',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_purge_all',
			'permission_callback' => __NAMESPACE__ . '\rest_permission_check',
		]
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );

/**
 * Permission gate — same capability the IDE itself uses.
 *
 * @return bool True when the current user may inspect the cache.
 */
function rest_permission_check(): bool {
	return current_user_can( 'manage_graphql_ide' );
}

/**
 * GET /entries — returns the inventory.
 *
 * Reads transient-backed entries directly from `wp_options` (one query
 * with a self-join for timeouts). External object caches don't expose
 * a key inventory through PHP, so we surface a `storage` flag the UI
 * uses to render a "not introspectable" notice instead of a table.
 *
 * @return \WP_REST_Response
 */
function rest_list_entries() {
	if ( wp_using_ext_object_cache() ) {
		return rest_ensure_response(
			[
				'storage'   => 'object_cache',
				'entries'   => [],
				'count'     => 0,
				'truncated' => false,
				'totalSize' => 0,
			]
		);
	}

	global $wpdb;

	// One query: data row + matching timeout row, sorted by size desc.
	// `LENGTH(option_value)` returns bytes for UTF-8 in MySQL — same
	// thing the storage engine actually consumes.
	$prefix         = '_transient_' . transient_prefix();
	$timeout_prefix = '_transient_timeout_' . transient_prefix();

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				SUBSTRING(data.option_name, %d) AS cache_key,
				LENGTH(data.option_value) AS size_bytes,
				CAST(timeout.option_value AS UNSIGNED) AS expires_at
			FROM {$wpdb->options} AS data
			LEFT JOIN {$wpdb->options} AS timeout
				ON timeout.option_name = CONCAT(%s, SUBSTRING(data.option_name, %d))
			WHERE data.option_name LIKE %s
			ORDER BY size_bytes DESC
			LIMIT %d",
			strlen( $prefix ) + 1,
			$timeout_prefix,
			strlen( $prefix ) + 1,
			$wpdb->esc_like( $prefix ) . '%',
			ENTRY_LIMIT + 1
		),
		ARRAY_A
	);

	if ( ! is_array( $rows ) ) {
		$rows = [];
	}

	$truncated = count( $rows ) > ENTRY_LIMIT;
	if ( $truncated ) {
		$rows = array_slice( $rows, 0, ENTRY_LIMIT );
	}

	$now            = time();
	$displayed_size = 0;
	$entries        = array_map(
		static function ( $row ) use ( $now, &$displayed_size ) {
			$size            = (int) $row['size_bytes'];
			$displayed_size += $size;
			$expires_at      = isset( $row['expires_at'] ) ? (int) $row['expires_at'] : 0;
			$cache_key       = (string) $row['cache_key'];
			return [
				'cacheKey'  => $cache_key,
				'sizeBytes' => $size,
				'expiresAt' => $expires_at ?: null,
				'expiresIn' => $expires_at ? max( 0, $expires_at - $now ) : null,
				'type'      => classify_cache_key( $cache_key ),
			];
		},
		$rows
	);

	// When we didn't truncate, the displayed rows ARE the full set, so
	// no extra aggregate scan is needed. Only re-query the totals when
	// the slice misses entries the user can't see.
	if ( $truncated ) {
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS row_count, SUM(LENGTH(option_value)) AS total_size FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			),
			ARRAY_A
		);
		$true_count = isset( $totals['row_count'] ) ? (int) $totals['row_count'] : count( $entries );
		$true_total = isset( $totals['total_size'] ) ? (int) $totals['total_size'] : $displayed_size;
	} else {
		$true_count = count( $entries );
		$true_total = $displayed_size;
	}

	return rest_ensure_response(
		[
			'storage'   => 'transient',
			'entries'   => $entries,
			'count'     => $true_count,
			'truncated' => $truncated,
			'totalSize' => $true_total,
		]
	);
}

/**
 * POST /purge — deletes a single entry.
 *
 * @param \WP_REST_Request $request The incoming request with a `cacheKey` body field.
 *
 * @return \WP_REST_Response
 */
function rest_purge_entry( $request ) {
	$cache_key = (string) $request->get_param( 'cacheKey' );
	if ( '' === $cache_key || ! preg_match( '/' . CACHE_KEY_PATTERN . '/', $cache_key ) ) {
		return new \WP_Error(
			'wpgraphql_ide_cache_inspector_invalid_key',
			'Invalid cache key.',
			[ 'status' => 400 ]
		);
	}

	$deleted = delete_transient( transient_prefix() . $cache_key );

	return rest_ensure_response(
		[
			'cacheKey' => $cache_key,
			'deleted'  => (bool) $deleted,
		]
	);
}

/**
 * POST /purge-all — deletes every Smart Cache transient.
 *
 * @return \WP_REST_Response
 */
function rest_purge_all() {
	if ( wp_using_ext_object_cache() ) {
		return new \WP_Error(
			'wpgraphql_ide_cache_inspector_external_cache',
			'External object cache: bulk purge is not supported from this UI. Use your backend tools (redis-cli, etc.).',
			[ 'status' => 400 ]
		);
	}

	global $wpdb;
	$prefix         = '_transient_' . transient_prefix();
	$timeout_prefix = '_transient_timeout_' . transient_prefix();

	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%',
			$wpdb->esc_like( $timeout_prefix ) . '%'
		)
	);

	// Raw DELETE bypasses `delete_transient()`/`delete_option()`, so the
	// options layer doesn't invalidate its own caches. Flush so the next
	// `get_option()` doesn't return stale rows.
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );

	return rest_ensure_response(
		[
			'deleted' => $deleted,
		]
	);
}

/**
 * Classifies a cache key as a response cache (SHA-256 hex, 64 chars) or
 * a tracker key (shorter purge-map identifiers). The UI uses this to
 * separate the two surfaces so users can tell what they're looking at.
 *
 * @param string $cache_key Raw cache key (already stripped of the transient prefix).
 *
 * @return string `response` for SHA-shaped keys, `tracker` otherwise.
 */
function classify_cache_key( string $cache_key ): string {
	return preg_match( '/^[a-f0-9]{64}$/', $cache_key ) ? 'response' : 'tracker';
}

/**
 * POST /purge-bulk — deletes a caller-supplied list of cache entries.
 *
 * Each key goes through `delete_transient()` (not raw DELETE) so any
 * filters / actions listening on transient deletion fire normally.
 * Capped at BULK_PURGE_LIMIT keys per request — the UI doesn't surface
 * larger selections, but the cap is a defense against a buggy client.
 *
 * @param \WP_REST_Request $request Incoming request with a `cacheKeys` array.
 *
 * @return \WP_REST_Response|\WP_Error
 */
function rest_purge_bulk( $request ) {
	$keys = (array) $request->get_param( 'cacheKeys' );
	$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );

	if ( empty( $keys ) ) {
		return new \WP_Error(
			'wpgraphql_ide_cache_inspector_empty_selection',
			'No cache keys provided.',
			[ 'status' => 400 ]
		);
	}

	if ( count( $keys ) > BULK_PURGE_LIMIT ) {
		return new \WP_Error(
			'wpgraphql_ide_cache_inspector_too_many_keys',
			sprintf( 'At most %d keys can be purged per request.', BULK_PURGE_LIMIT ),
			[ 'status' => 400 ]
		);
	}

	$prefix   = transient_prefix();
	$deleted  = 0;
	$failures = [];
	foreach ( $keys as $cache_key ) {
		if ( ! preg_match( '/' . CACHE_KEY_PATTERN . '/', $cache_key ) ) {
			$failures[] = $cache_key;
			continue;
		}
		if ( delete_transient( $prefix . $cache_key ) ) {
			++$deleted;
		} else {
			$failures[] = $cache_key;
		}
	}

	return rest_ensure_response(
		[
			'deleted'  => $deleted,
			'failures' => $failures,
		]
	);
}
