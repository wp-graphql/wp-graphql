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
		// Debug: Log that the handler was called (before any other logic).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[WPGraphQL PQC] PurgeHandler::handle_purge called with key="%s" event="%s" hostname="%s"', $key, $event, $hostname ) );
		}

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
		 * Filter whether to delete index entries after purging.
		 * Defaults to true in production, but can be set to false for testing/debugging.
		 *
		 * @param bool   $delete_entries Whether to delete index entries.
		 * @param string $key            The cache key being purged.
		 * @param array  $urls           The URLs that were purged.
		 * @return bool
		 */
		$delete_entries = apply_filters( 'wpgraphql_pqc_delete_entries_on_purge', true, $key, $urls );

		// For each URL, purge it and delete all its entries.
		foreach ( $urls as $url ) {
			// Purge the URL via the adapter (VIP, WP Engine, etc.).
			$adapter->purge_url( $url );

			// Delete ALL entries for this URL (not just the specific cache key).
			// When a cache key is purged, the entire URL response is invalid,
			// so we need to remove all cache key associations for that URL.
			if ( $delete_entries ) {
				$store->delete_by_url( $url );
			}
		}
	}
}
