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

		if ( empty( $urls ) ) {
			return;
		}

		// Get purge adapter.
		$adapter = AdapterFactory::get_adapter();

		// Purge each URL.
		foreach ( $urls as $url ) {
			$adapter->purge_url( $url );
		}

		// Delete index entries for this key.
		$store->delete_by_key( $key );
	}
}
