<?php
/**
 * Purge adapter factory
 *
 * @package WPGraphQL\PQC\Purge
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Purge;

/**
 * Class AdapterFactory
 *
 * @package WPGraphQL\PQC\Purge
 */
class AdapterFactory {

	/**
	 * Get the appropriate purge adapter
	 *
	 * @return AdapterInterface
	 */
	public static function get_adapter(): AdapterInterface {
		/**
		 * Filter the purge adapter
		 *
		 * @param AdapterInterface|null $adapter The adapter instance, or null to use auto-detection.
		 * @return AdapterInterface|null
		 */
		$adapter = apply_filters( 'wpgraphql_pqc_purge_adapter', null );

		if ( $adapter instanceof AdapterInterface ) {
			return $adapter;
		}

		// Auto-detect based on environment.
		if ( VIPAdapter::is_available() ) {
			return new VIPAdapter();
		}

		if ( HttpPurgeAdapter::is_available() ) {
			return new HttpPurgeAdapter();
		}

		// Default to null adapter (no-op).
		return new NullAdapter();
	}
}
