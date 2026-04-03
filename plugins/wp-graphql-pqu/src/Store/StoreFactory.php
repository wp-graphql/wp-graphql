<?php
/**
 * Store factory for selecting the appropriate store implementation
 *
 * @package WPGraphQL\PQU\Store
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQU\Store;

/**
 * Class StoreFactory
 *
 * @package WPGraphQL\PQU\Store
 */
class StoreFactory {

	/**
	 * Get the store instance
	 */
	public static function get_store(): StoreInterface {
		/**
		 * Filter the store implementation
		 *
		 * @param \WPGraphQL\PQU\Store\StoreInterface|null $store The store instance, or null to use default.
		 * @return \WPGraphQL\PQU\Store\StoreInterface
		 */
		$store = apply_filters( 'wpgraphql_pqu_store', null );

		if ( $store instanceof StoreInterface ) {
			return $store;
		}

		// Default to DB store.
		return new DBStore();
	}
}
