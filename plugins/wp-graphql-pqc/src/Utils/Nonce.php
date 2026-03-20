<?php
/**
 * Nonce generation and validation for PQC flow
 *
 * @package WPGraphQL\PQC\Utils
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Utils;

/**
 * Class Nonce
 *
 * @package WPGraphQL\PQC\Utils
 */
class Nonce {

	/**
	 * Nonce expiration time in seconds (default: 5 minutes)
	 *
	 * @var int
	 */
	const NONCE_EXPIRATION = 300;

	/**
	 * Transient prefix for storing nonces
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'wpgraphql_pqc_nonce_';

	/**
	 * Generate a nonce for a query hash and variables hash combination
	 *
	 * @param string      $query_hash    The query hash.
	 * @param string|null $variables_hash The variables hash.
	 * @return string The nonce token.
	 */
	public static function generate( string $query_hash, ?string $variables_hash ): string {
		// Generate a cryptographically secure random token.
		$token = bin2hex( random_bytes( 32 ) ); // 64 character hex string.

		// Store nonce data in transient (expires in NONCE_EXPIRATION seconds).
		$nonce_key = self::TRANSIENT_PREFIX . $token;
		$nonce_data = [
			'query_hash'    => $query_hash,
			'variables_hash' => $variables_hash ?: '',
			'created_at'    => time(),
			'used'          => false,
		];

		set_transient( $nonce_key, $nonce_data, self::NONCE_EXPIRATION );

		return $token;
	}

	/**
	 * Validate a nonce token (without marking as used)
	 *
	 * @param string      $nonce         The nonce token.
	 * @param string      $query_hash    The query hash to validate against.
	 * @param string|null $variables_hash The variables hash to validate against.
	 * @return bool True if nonce is valid, false otherwise.
	 */
	public static function validate( string $nonce, string $query_hash, ?string $variables_hash ): bool {
		if ( empty( $nonce ) ) {
			return false;
		}

		$nonce_key = self::TRANSIENT_PREFIX . $nonce;
		$nonce_data = get_transient( $nonce_key );

		// Nonce doesn't exist or expired.
		if ( false === $nonce_data ) {
			return false;
		}

		// Nonce already used.
		if ( ! empty( $nonce_data['used'] ) ) {
			return false;
		}

		// Validate query hash matches.
		if ( $nonce_data['query_hash'] !== $query_hash ) {
			return false;
		}

		// Validate variables hash matches.
		$expected_variables_hash = $variables_hash ?: '';
		if ( $nonce_data['variables_hash'] !== $expected_variables_hash ) {
			return false;
		}

		return true;
	}

	/**
	 * Mark a nonce as used (call after successful storage)
	 *
	 * @param string $nonce The nonce token.
	 * @return bool True if marked successfully, false otherwise.
	 */
	public static function mark_used( string $nonce ): bool {
		if ( empty( $nonce ) ) {
			return false;
		}

		$nonce_key = self::TRANSIENT_PREFIX . $nonce;
		$nonce_data = get_transient( $nonce_key );

		if ( false === $nonce_data ) {
			return false;
		}

		// Mark nonce as used.
		$nonce_data['used'] = true;
		set_transient( $nonce_key, $nonce_data, self::NONCE_EXPIRATION );

		return true;
	}

	/**
	 * Get nonce data without validating (for debugging/inspection)
	 *
	 * @param string $nonce The nonce token.
	 * @return array|null Nonce data or null if not found.
	 */
	public static function get_data( string $nonce ): ?array {
		if ( empty( $nonce ) ) {
			return null;
		}

		$nonce_key = self::TRANSIENT_PREFIX . $nonce;
		$nonce_data = get_transient( $nonce_key );

		return false !== $nonce_data ? $nonce_data : null;
	}
}
