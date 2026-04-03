<?php
/**
 * Null (no-op) purge adapter for development and debugging
 *
 * @package WPGraphQL\PQU\Purge
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQU\Purge;

/**
 * Class NullAdapter
 *
 * @package WPGraphQL\PQU\Purge
 */
class NullAdapter implements AdapterInterface {

	/**
	 * Purge a specific URL from the cache
	 *
	 * @param string $url The URL to purge.
	 * @return bool Always returns true (no-op).
	 */
	public function purge_url( string $url ): bool {
		return true;
	}

	/**
	 * Purge all cached URLs
	 *
	 * @return bool Always returns true (no-op).
	 */
	public function purge_all(): bool {
		return true;
	}
}
