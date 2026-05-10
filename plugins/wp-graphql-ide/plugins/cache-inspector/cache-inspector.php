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

const REST_NAMESPACE = 'wpgraphql-cache-inspector/v1';
const TRANSIENT_PREFIX = 'gql_cache_';
const ENTRY_LIMIT = 500;

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
				'storage'  => 'object_cache',
				'entries'  => [],
				'count'    => 0,
				'truncated' => false,
				'totalSize' => 0,
			]
		);
	}

	global $wpdb;

	// One query: data row + matching timeout row, sorted by size desc.
	// `LENGTH(option_value)` returns bytes for UTF-8 in MySQL — same
	// thing the storage engine actually consumes.
	$prefix = '_transient_' . TRANSIENT_PREFIX;
	$timeout_prefix = '_transient_timeout_' . TRANSIENT_PREFIX;

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

	$now        = time();
	$total_size = 0;
	$entries    = array_map(
		static function ( $row ) use ( $now, &$total_size ) {
			$size      = (int) $row['size_bytes'];
			$total_size += $size;
			$expires_at = isset( $row['expires_at'] ) ? (int) $row['expires_at'] : 0;
			return [
				'cacheKey'  => (string) $row['cache_key'],
				'sizeBytes' => $size,
				'expiresAt' => $expires_at ?: null,
				'expiresIn' => $expires_at ? max( 0, $expires_at - $now ) : null,
			];
		},
		$rows
	);

	// If we truncated, total_size only covers the displayed slice. Run
	// a separate aggregate so the UI can show the true total.
	$true_total = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);

	$true_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);

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
	if ( '' === $cache_key || ! preg_match( '/^[a-f0-9]+$/i', $cache_key ) ) {
		return new \WP_Error(
			'wpgraphql_ide_cache_inspector_invalid_key',
			'Invalid cache key.',
			[ 'status' => 400 ]
		);
	}

	$deleted = delete_transient( TRANSIENT_PREFIX . $cache_key );

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
	$prefix = '_transient_' . TRANSIENT_PREFIX;

	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . TRANSIENT_PREFIX ) . '%'
		)
	);

	return rest_ensure_response(
		[
			'deleted' => $deleted,
		]
	);
}
