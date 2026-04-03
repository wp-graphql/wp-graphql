<?php
/**
 * Logger utility for PQC purge events
 *
 * @package WPGraphQL\PQC\Utils
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Utils;

/**
 * Class Logger
 *
 * @package WPGraphQL\PQC\Utils
 */
class Logger {

	/**
	 * Trace hook for purge resolution (not written to PHP error_log; use the action for custom logging).
	 *
	 * @param string   $cache_key The cache key that triggered the purge.
	 * @param string   $event     The event that triggered the purge.
	 * @param string   $hostname  The hostname/endpoint.
	 * @param string[] $urls      URLs PQC resolved for this key (may be empty).
	 */
	public static function log_purge_event( string $cache_key, string $event, string $hostname, array $urls ): void {
		/**
		 * Fires after PQC resolves persisted URLs for a `graphql_purge` event.
		 *
		 * @param string $cache_key Cache key passed to `graphql_purge`.
		 * @param string $event     Event name.
		 * @param string $hostname  Hostname / endpoint argument.
		 * @param string[] $urls    Resolved URL paths to purge at the edge (empty if none indexed).
		 */
		do_action( 'wpgraphql_pqc_purge_resolved', $cache_key, $event, $hostname, $urls );
	}

	/**
	 * Log a failed HTTP edge purge (HttpPurgeAdapter).
	 *
	 * @param string $target  Full URL that was requested.
	 * @param string $reason  Error message or status summary.
	 */
	public static function log_http_purge_failure( string $target, string $reason ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[WPGraphQL PQC] HttpPurgeAdapter: failed purge target=%s reason=%s',
					$target,
					$reason
				)
			);
		}
	}
}
