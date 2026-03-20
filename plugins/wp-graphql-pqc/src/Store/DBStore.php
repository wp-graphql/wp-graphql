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

		$documents_table = Schema::get_documents_table_name();
		$url_keys_table = Schema::get_url_keys_table_name();
		$url_hash = hash( 'sha256', $url );

		// First, ensure the document exists in the documents table (INSERT IGNORE to handle duplicates).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$documents_table} (query_hash, query_document) VALUES (%s, %s)",
				$query_hash,
				$query_doc
			)
		);

		// Then, store one row per cache key in url_keys table (many-to-many relationship).
		foreach ( $cache_keys as $cache_key ) {
			// Use INSERT IGNORE to handle duplicates gracefully.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$url_keys_table} (url_hash, url, query_hash, variables_hash, variables, cache_key) VALUES (%s, %s, %s, %s, %s, %s)",
					$url_hash,
					$url,
					$query_hash,
					$variables_hash,
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

		$documents_table = Schema::get_documents_table_name();
		$url_keys_table = Schema::get_url_keys_table_name();

		// JOIN documents and url_keys tables to get both query_document and variables.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT d.query_document, uk.variables 
				FROM {$documents_table} d
				INNER JOIN {$url_keys_table} uk ON d.query_hash = uk.query_hash
				WHERE uk.query_hash = %s AND uk.variables_hash = %s
				LIMIT 1",
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

		$url_keys_table = Schema::get_url_keys_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT url FROM {$url_keys_table} WHERE cache_key = %s",
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

		$url_keys_table = Schema::get_url_keys_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$url_keys_table,
			[ 'cache_key' => $cache_key ],
			[ '%s' ]
		);

		// Note: Documents are not deleted here even if they become orphaned.
		// Garbage collection can clean up orphaned documents later if needed.
	}
}
