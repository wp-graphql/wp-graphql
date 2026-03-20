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

		// Also use graphql_debug if available (for GraphQL debug output).
		if ( function_exists( 'graphql_debug' ) ) {
			graphql_debug(
				$message,
				[
					'cache_key' => $cache_key,
					'event'     => $event,
					'hostname'  => $hostname,
					'urls'      => $urls,
					'urls_count' => count( $urls ),
				]
			);
		}
	}
}
