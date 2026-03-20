<?php
/**
 * Hashing utilities for queries and variables
 *
 * @package WPGraphQL\PQC\Utils
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Utils;

use GraphQL\Language\Parser;
use GraphQL\Language\Printer;

/**
 * Class Hasher
 *
 * @package WPGraphQL\PQC\Utils
 */
class Hasher {

	/**
	 * Generate hash for a GraphQL query document
	 *
	 * Uses the same normalization as WPGraphQL core (Printer::doPrint).
	 *
	 * @param string $query The GraphQL query document string.
	 * @return string|null SHA-256 hash in lowercase hex, or null on error.
	 */
	public static function hash_query( string $query ): ?string {
		try {
			$ast = Parser::parse( $query );
			$normalized = Printer::doPrint( $ast );

			return hash( 'sha256', $normalized );
		} catch ( \Throwable $exception ) {
			return null;
		}
	}

	/**
	 * Generate hash for GraphQL variables
	 *
	 * Variables are sorted recursively before hashing to ensure consistent hashes
	 * regardless of key order in the input.
	 *
	 * @param array|null $variables The variables array, or null/empty for no variables.
	 * @return string|null SHA-256 hash in lowercase hex, or null if variables are empty/null.
	 */
	public static function hash_variables( ?array $variables ): ?string {
		if ( empty( $variables ) ) {
			return null;
		}

		// Sort keys recursively.
		$sorted = self::recursive_ksort( $variables );

		// Encode to JSON with no extra whitespace.
		$json = wp_json_encode( $sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return null;
		}

		return hash( 'sha256', $json );
	}

	/**
	 * Recursively sort array keys
	 *
	 * @param array $array The array to sort.
	 * @return array The sorted array.
	 */
	private static function recursive_ksort( array $array ): array {
		ksort( $array );

		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$array[ $key ] = self::recursive_ksort( $value );
			}
		}

		return $array;
	}

	/**
	 * Generate hash for a URL
	 *
	 * @param string $url The URL to hash.
	 * @return string SHA-256 hash in lowercase hex.
	 */
	public static function hash_url( string $url ): string {
		return hash( 'sha256', $url );
	}
}
