<?php
/**
 * Plugin Name: WPGraphQL IDE Smart Cache Panel
 * Description: Renders the `graphqlSmartCache` response extension as a dedicated tab in the WPGraphQL IDE response panel.
 *
 * Built inside wp-graphql-ide so it can later be lifted into the
 * wp-graphql-smart-cache plugin itself — the renderer is server-agnostic
 * (reads only from `response.extensions.graphqlSmartCache`) and depends
 * only on the public `registerResponseExtensionTab` API exposed on
 * `window.WPGraphQLIDE`. Moving it later is a directory copy plus a
 * `wp_enqueue_script` adjustment.
 */

namespace WPGraphQLIDE\SmartCachePanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_SMART_CACHE_PANEL_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_SMART_CACHE_PANEL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the script for the Smart Cache Panel.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_SMART_CACHE_PANEL_DIR_PATH . 'build/smart-cache-panel.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'smart-cache-panel',
		WPGRAPHQL_IDE_SMART_CACHE_PANEL_URL . 'build/smart-cache-panel.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);
}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\enqueue_assets' );

/**
 * Augments `extensions.graphqlSmartCache` with diagnostic data the
 * smart-cache plugin doesn't surface itself: a purge map (which nodes
 * and list types invalidate this cache entry) and a TTL countdown
 * (when the response was cached, when it expires).
 *
 * Lives in the IDE plugin so we can ship cache-debugging UX without
 * coupling to wp-graphql-smart-cache. Reads are non-destructive — pulls
 * directly from the request's Query Analyzer (the same source smart-cache
 * uses internally) and from WordPress' transient timeout option.
 *
 * Bails when the smart-cache extension isn't already populated, so this
 * filter is a no-op on installs without smart-cache.
 *
 * Runs at priority 11 so smart-cache (priority 10) has already populated
 * `graphqlObjectCache`. Hooked off `do_graphql_request` to register the
 * filter only inside a GraphQL request lifecycle.
 *
 * @return void
 */
function register_diagnostics_filter(): void {
	add_filter(
		'graphql_request_results',
		__NAMESPACE__ . '\augment_smart_cache_diagnostics',
		11,
		7
	);
}
add_action( 'do_graphql_request', __NAMESPACE__ . '\register_diagnostics_filter' );

/**
 * @param mixed                                $response
 * @param mixed                                $schema
 * @param string|null                          $operation_name
 * @param string|null                          $query_string
 * @param array|null                           $variables
 * @param \WPGraphQL\Request|null              $request
 * @param string|null                          $query_id
 *
 * @return mixed The response, with `extensions.graphqlSmartCache.diagnostics` added.
 */
function augment_smart_cache_diagnostics( $response, $schema, $operation_name, $query_string, $variables, $request, $query_id ) {
	// Pull current extension data — array vs object response shapes
	// both happen depending on graphql-php internals.
	$existing = null;
	if ( is_array( $response ) && isset( $response['extensions']['graphqlSmartCache'] ) ) {
		$existing = $response['extensions']['graphqlSmartCache'];
	} elseif ( is_object( $response ) && property_exists( $response, 'extensions' ) && isset( $response->extensions['graphqlSmartCache'] ) ) {
		$existing = $response->extensions['graphqlSmartCache'];
	}

	if ( null === $existing ) {
		return $response;
	}

	$diagnostics = [];

	// Purge map: nodes + list types + root types associated with this
	// query, derived from the Query Analyzer (same source smart-cache
	// uses for purging). We also fold in the keys-count / keys-length
	// metrics and the skipped-keys diagnostics (entries the analyzer
	// dropped due to its header-length budget) — both used to live in
	// the standalone "Query Analyzer" response tab; consolidating them
	// here keeps the caching story in one place.
	if ( $request && method_exists( $request, 'get_query_analyzer' ) ) {
		$analyzer = $request->get_query_analyzer();
		if ( $analyzer ) {
			$nodes = method_exists( $analyzer, 'get_runtime_nodes' )
				? ( $analyzer->get_runtime_nodes() ?: [] )
				: [];
			$lists = method_exists( $analyzer, 'get_list_types' )
				? ( $analyzer->get_list_types() ?: [] )
				: [];
			$query_types = method_exists( $analyzer, 'get_query_types' )
				? ( $analyzer->get_query_types() ?: [] )
				: [];

			$purge_map = [
				'nodes'      => array_values( array_map( 'strval', $nodes ) ),
				'lists'      => array_values( array_map( 'strval', $lists ) ),
				'queryTypes' => array_values( array_map( 'strval', $query_types ) ),
			];

			// The "skipped" surface only appears when the analyzer's
			// budget dropped entries from the X-GraphQL-Keys header —
			// noise on every response otherwise.
			$skipped = null;
			if ( method_exists( $analyzer, 'get_graphql_keys' ) ) {
				$keys_data = $analyzer->get_graphql_keys();
				if ( is_array( $keys_data ) ) {
					$purge_map['keysCount']  = isset( $keys_data['keysCount'] ) ? (int) $keys_data['keysCount'] : 0;
					$purge_map['keysLength'] = isset( $keys_data['keysLength'] ) ? (int) $keys_data['keysLength'] : 0;

					$skipped_count = isset( $keys_data['skippedKeysCount'] ) ? (int) $keys_data['skippedKeysCount'] : 0;
					if ( $skipped_count > 0 ) {
						$skipped_keys_raw   = isset( $keys_data['skippedKeys'] ) ? (string) $keys_data['skippedKeys'] : '';
						$skipped_keys_array = '' !== $skipped_keys_raw ? array_values( array_filter( explode( ' ', $skipped_keys_raw ) ) ) : [];
						$skipped_types      = isset( $keys_data['skippedTypes'] ) && is_array( $keys_data['skippedTypes'] )
							? array_values( array_map( 'strval', $keys_data['skippedTypes'] ) )
							: [];
						$skipped = [
							'keys'  => $skipped_keys_array,
							'types' => $skipped_types,
							'count' => $skipped_count,
							'size'  => isset( $keys_data['skippedKeysSize'] ) ? (int) $keys_data['skippedKeysSize'] : 0,
						];
					}
				}
			}

			$diagnostics['purgeMap'] = $purge_map;
			if ( null !== $skipped ) {
				$diagnostics['skipped'] = $skipped;
			}
		}
	}

	// Default TTL from smart-cache settings (the same constant the
	// plugin would use when storing). Surfaced even on misses so the
	// panel can show "would expire in 600s" once cached.
	if ( function_exists( 'get_graphql_setting' ) ) {
		$global_ttl = (int) \get_graphql_setting( 'global_ttl', 600, 'graphql_cache_section' );
		$diagnostics['globalTtl'] = $global_ttl;
	}

	// On a HIT, look up the transient timeout to derive expiresAt /
	// expiresIn / cachedAt. Smart-cache stores entries under the prefix
	// `gql_cache_<sha256>` via WP transients. External object caches
	// (Redis/Memcache) don't expose timeouts the same way, so we skip
	// when smart-cache routed through wp_cache_*.
	$cache_key = isset( $existing['graphqlObjectCache']['cacheKey'] )
		? (string) $existing['graphqlObjectCache']['cacheKey']
		: '';

	if ( '' !== $cache_key && ! wp_using_ext_object_cache() ) {
		$timeout_option = '_transient_timeout_gql_cache_' . $cache_key;
		$expires_at     = (int) get_option( $timeout_option, 0 );
		if ( $expires_at > 0 ) {
			$now                         = time();
			$diagnostics['expiresAt']    = $expires_at;
			$diagnostics['expiresIn']    = max( 0, $expires_at - $now );
			$diagnostics['cachedAt']     = $expires_at - ( $diagnostics['globalTtl'] ?? 0 );
			$diagnostics['storage']      = 'transient';
		}
	} elseif ( '' !== $cache_key && wp_using_ext_object_cache() ) {
		// External object cache — TTL is enforced by the backend but
		// not introspectable from PHP. Surface backend type so the
		// panel can explain why no countdown is shown.
		$diagnostics['storage'] = 'object_cache';
	}

	if ( empty( $diagnostics ) ) {
		return $response;
	}

	if ( is_array( $response ) ) {
		$response['extensions']['graphqlSmartCache']['diagnostics'] = $diagnostics;
	} elseif ( is_object( $response ) && property_exists( $response, 'extensions' ) ) {
		$response->extensions['graphqlSmartCache']['diagnostics'] = $diagnostics;
	}

	return $response;
}
