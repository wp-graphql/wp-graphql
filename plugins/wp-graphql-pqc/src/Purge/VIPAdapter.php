<?php
/**
 * WordPress VIP purge adapter
 *
 * @package WPGraphQL\PQC\Purge
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Purge;

/**
 * Class VIPAdapter
 *
 * @package WPGraphQL\PQC\Purge
 */
class VIPAdapter implements AdapterInterface {

	/**
	 * Check if VIP functions are available
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		// Check for new non-deprecated function first.
		if ( function_exists( 'wpvip_purge_edge_cache_for_url' ) ) {
			return true;
		}

		// Fall back to deprecated function for backward compatibility.
		return function_exists( 'wpcom_vip_purge_edge_cache_for_url' );
	}

	/**
	 * Purge a specific URL from the cache
	 *
	 * @param string $url The URL to purge.
	 * @return bool True on success, false on failure.
	 */
	public function purge_url( string $url ): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		// Build full URL if relative.
		$full_url = $this->build_full_url( $url );

		// Use new non-deprecated function if available, otherwise fall back to deprecated function.
		if ( function_exists( 'wpvip_purge_edge_cache_for_url' ) ) {
			wpvip_purge_edge_cache_for_url( $full_url );
		} elseif ( function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) ) {
			// phpcs:ignore WordPressVIP.Functions.RestrictedFunctions.deprecated_wpcom_vip_purge_edge_cache_for_url
			wpcom_vip_purge_edge_cache_for_url( $full_url );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Purge all cached URLs
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all(): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		// VIP doesn't have a purge_all function, so we'll need to handle this differently.
		// For now, return false to indicate it's not supported.
		return false;
	}

	/**
	 * Build full URL from relative path
	 *
	 * @param string $url The relative or absolute URL.
	 * @return string The full URL.
	 */
	private function build_full_url( string $url ): string {
		// If already a full URL, return as-is.
		if ( preg_match( '/^https?:\/\//', $url ) ) {
			return $url;
		}

		// Build full URL from site URL.
		$site_url = site_url();
		$url = ltrim( $url, '/' );

		return $site_url . '/' . $url;
	}
}
