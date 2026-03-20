<?php
/**
 * Store factory for selecting the appropriate store implementation
 *
 * @package WPGraphQL\PQC\Store
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Store;

/**
 * Class StoreFactory
 *
 * @package WPGraphQL\PQC\Store
 */
class StoreFactory {

	/**
	 * Get the store instance
	 *
	 * @return StoreInterface
	 */
	public static function get_store(): StoreInterface {
		/**
		 * Filter the store implementation
		 *
		 * @param StoreInterface|null $store The store instance, or null to use default.
		 * @return StoreInterface
		 */
		$store = apply_filters( 'wpgraphql_pqc_store', null );

		if ( $store instanceof StoreInterface ) {
			return $store;
		}

		// Default to DB store.
		return new DBStore();
	}
}
