<?php
/**
 * Database-backed store implementation
 *
 * @package WPGraphQL\PQC\Store
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Store;

use WPGraphQL\PQC\Database\Schema;

/**
 * Class DBStore
 *
 * @package WPGraphQL\PQC\Store
 */
class DBStore implements StoreInterface {

	/**
	 * Store a URL and its associated query, variables, and cache keys
	 *
	 * @param string $url           The full URL (e.g., /graphql/persisted/{queryHash}/variables/{variablesHash}).
	 * @param string $query_hash    SHA-256 hash of the normalized query document.
	 * @param string $variables_hash SHA-256 hash of the canonicalized variables JSON.
	 * @param string $query_doc     The full GraphQL query document.
	 * @param string $variables     The variables JSON string.
	 * @param array  $cache_keys    Array of cache keys from X-GraphQL-Keys header.
	 * @return void
	 */
	public function store( string $url, string $query_hash, string $variables_hash, string $query_doc, string $variables, array $cache_keys ): void {
		global $wpdb;

		$table_name = Schema::get_table_name();
		$url_hash = hash( 'sha256', $url );

		// Store one row per cache key (many-to-many relationship).
		foreach ( $cache_keys as $cache_key ) {
			// Use INSERT IGNORE to handle duplicates gracefully.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table_name} (url_hash, url, query_hash, variables_hash, query_document, variables, cache_key) VALUES (%s, %s, %s, %s, %s, %s, %s)",
					$url_hash,
					$url,
					$query_hash,
					$variables_hash,
					$query_doc,
					$variables,
					$cache_key
				)
			);
		}
	}

	/**
	 * Get query document and variables by query hash and variables hash
	 *
	 * @param string $query_hash    SHA-256 hash of the normalized query document.
	 * @param string $variables_hash SHA-256 hash of the canonicalized variables JSON.
	 * @return array|null Array with 'query_document' and 'variables' keys, or null if not found.
	 */
	public function get_query( string $query_hash, string $variables_hash ): ?array {
		global $wpdb;

		$table_name = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT query_document, variables FROM {$table_name} WHERE query_hash = %s AND variables_hash = %s LIMIT 1",
				$query_hash,
				$variables_hash
			),
			ARRAY_A
		);

		if ( ! $result ) {
			return null;
		}

		return [
			'query_document' => $result['query_document'],
			'variables'      => $result['variables'],
		];
	}

	/**
	 * Get all URLs tagged with a specific cache key
	 *
	 * @param string $cache_key The cache key (e.g., 'post:123', 'list:post').
	 * @return array Array of URLs.
	 */
	public function get_urls_for_key( string $cache_key ): array {
		global $wpdb;

		$table_name = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT url FROM {$table_name} WHERE cache_key = %s",
				$cache_key
			)
		);

		return $results ?: [];
	}

	/**
	 * Delete all index entries for a specific cache key
	 *
	 * @param string $cache_key The cache key to delete.
	 * @return void
	 */
	public function delete_by_key( string $cache_key ): void {
		global $wpdb;

		$table_name = Schema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table_name,
			[ 'cache_key' => $cache_key ],
			[ '%s' ]
		);
	}
}
