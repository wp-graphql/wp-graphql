<?php
/**
 * Purge adapter interface
 *
 * @package WPGraphQL\PQC\Purge
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Purge;

/**
 * Interface AdapterInterface
 *
 * @package WPGraphQL\PQC\Purge
 */
interface AdapterInterface {

	/**
	 * Purge a specific URL from the cache
	 *
	 * @param string $url The URL to purge.
	 * @return bool True on success, false on failure.
	 */
	public function purge_url( string $url ): bool;

	/**
	 * Purge all cached URLs
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all(): bool;
}
