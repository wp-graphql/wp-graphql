<?php
/**
 * Cache invalidation handler
 *
 * @package WPGraphQL\PQC\Invalidation
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Invalidation;

use WPGraphQL\PQC\Purge\AdapterFactory;
use WPGraphQL\PQC\Store\StoreFactory;
use WPGraphQL\PQC\Utils\Logger;

/**
 * Class PurgeHandler
 *
 * @package WPGraphQL\PQC\Invalidation
 */
class PurgeHandler {

	/**
	 * Initialize the purge handler
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'graphql_purge', [ $this, 'handle_purge' ], 10, 3 );
	}

	/**
	 * Handle cache purge event
	 *
	 * @param string $key      The cache key to purge (e.g., 'post:123', 'list:post').
	 * @param string $event    The event that triggered the purge.
	 * @param string $hostname The hostname/endpoint.
	 * @return void
	 */
	public function handle_purge( string $key, string $event = '', string $hostname = '' ): void {
		$store = StoreFactory::get_store();

		// Get all URLs tagged with this cache key.
		$urls = $store->get_urls_for_key( $key );

		// Log the purge event (even if no URLs found, for debugging).
		Logger::log_purge_event( $key, $event, $hostname, $urls );

		if ( empty( $urls ) ) {
			return;
		}

		// Get purge adapter.
		$adapter = AdapterFactory::get_adapter();

		/**
		 * Filter whether to delete url_keys rows for the purged cache key after edge purge.
		 * Defaults to true. Execution records stay so warm GET can re-resolve the document.
		 *
		 * @param bool   $delete_entries Whether to delete tag rows for this cache key.
		 * @param string $key            The cache key being purged.
		 * @param array  $urls           The URLs that were purged at the edge.
		 * @return bool
		 */
		$delete_entries = apply_filters( 'wpgraphql_pqc_delete_entries_on_purge', true, $key, $urls );

		foreach ( $urls as $url ) {
			$adapter->purge_url( $url );
		}

		// Remove only tag associations for this cache key (not the whole URL, not executions).
		if ( $delete_entries ) {
			$store->delete_by_key( $key );
		}
	}
}
