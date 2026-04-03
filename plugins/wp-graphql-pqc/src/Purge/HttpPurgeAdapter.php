<?php
/**
 * HTTP PURGE adapter for local benchmarks and generic reverse proxies
 *
 * Sends an HTTP PURGE (or configurable method) to an edge origin so URL-keyed
 * caches (Varnish, some nginx builds) evict a specific path. WordPress must
 * define WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN (e.g. http://host.docker.internal:8081).
 *
 * @package WPGraphQL\PQC\Purge
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Purge;

use WPGraphQL\PQC\Utils\Logger;

/**
 * Class HttpPurgeAdapter
 *
 * @package WPGraphQL\PQC\Purge
 */
class HttpPurgeAdapter implements AdapterInterface {

	/**
	 * Whether this adapter is enabled (constant defined and non-empty).
	 */
	public static function is_available(): bool {
		if ( ! defined( 'WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN' ) ) {
			return false;
		}

		$origin = constant( 'WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN' );

		return is_string( $origin ) && '' !== $origin;
	}

	/**
	 * Purge a specific URL from the edge cache
	 *
	 * @param string $url Relative path (e.g. /graphql/persisted/{hash}) or absolute URL.
	 * @return bool True when the edge returns a success status; false otherwise.
	 */
	public function purge_url( string $url ): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		$target = $this->build_edge_url( $url );

		/**
		 * Filter HTTP method used for edge purge (default PURGE).
		 *
		 * @param string $method HTTP method.
		 * @param string $target Full URL being requested at the edge.
		 * @param string $url    Original path or URL from PQC.
		 * @return string
		 */
		$method = apply_filters( 'wpgraphql_pqc_http_purge_method', 'PURGE', $target, $url );
		if ( ! is_string( $method ) || '' === $method ) {
			$method = 'PURGE';
		}

		/**
		 * Filter arguments passed to wp_remote_request for edge purge.
		 *
		 * @param array  $args   Request arguments.
		 * @param string $target Full URL at the edge.
		 * @param string $url    Original path or URL from PQC.
		 * @return array
		 */
		$args = apply_filters(
			'wpgraphql_pqc_http_purge_request_args',
			[
				'method'    => strtoupper( $method ),
				'timeout'   => 3,
				'blocking'  => true,
				// Local benchmark edges often use HTTP; allow TLS without verification when needed.
				'sslverify' => true,
			],
			$target,
			$url
		);

		$response = wp_remote_request( $target, $args );

		if ( is_wp_error( $response ) ) {
			Logger::log_http_purge_failure( $target, $response->get_error_message() );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$ok   = $code >= 200 && $code < 300;

		if ( ! $ok ) {
			Logger::log_http_purge_failure( $target, 'HTTP ' . $code );
		}

		return $ok;
	}

	/**
	 * Purge all cached URLs (not supported for generic HTTP PURGE).
	 */
	public function purge_all(): bool {
		return false;
	}

	/**
	 * Build full edge URL from PQC path or absolute site URL.
	 *
	 * @param string $url Relative path or absolute URL.
	 */
	private function build_edge_url( string $url ): string {
		if ( ! defined( 'WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN' ) ) {
			return '';
		}

		$origin_raw = constant( 'WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN' );
		if ( ! is_string( $origin_raw ) || '' === $origin_raw ) {
			return '';
		}

		$origin = rtrim( $origin_raw, '/' );

		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			$parts = wp_parse_url( $url );
			$path  = isset( $parts['path'] ) && is_string( $parts['path'] ) ? $parts['path'] : '/';
			$query = isset( $parts['query'] ) && is_string( $parts['query'] ) ? '?' . $parts['query'] : '';

			return $origin . $path . $query;
		}

		return $origin . '/' . ltrim( $url, '/' );
	}
}
