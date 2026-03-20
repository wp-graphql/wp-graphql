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
	 * Log a purge event with details
	 *
	 * @param string $cache_key The cache key that triggered the purge.
	 * @param string $event      The event that triggered the purge.
	 * @param string $hostname   The hostname/endpoint.
	 * @param array  $urls       Array of URLs that would be purged.
	 * @return void
	 */
	public static function log_purge_event( string $cache_key, string $event, string $hostname, array $urls ): void {
		$message = sprintf(
			'[WPGraphQL PQC] Purge Event: key="%s" event="%s" hostname="%s" urls_count=%d',
			$cache_key,
			$event ?: 'unknown',
			$hostname ?: 'unknown',
			count( $urls )
		);

		// Log to error_log if WP_DEBUG is enabled.
		// Note: We use error_log instead of graphql_debug because purge events
		// are triggered by WordPress actions (post_updated, transition_post_status, etc.)
		// which occur outside of GraphQL requests.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );

			// Log each URL that would be purged.
			if ( ! empty( $urls ) ) {
				foreach ( $urls as $url ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( '[WPGraphQL PQC]   → Would purge URL: %s', $url ) );
				}
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WPGraphQL PQC]   → No URLs found for this cache key' );
			}
		}
	}
}
