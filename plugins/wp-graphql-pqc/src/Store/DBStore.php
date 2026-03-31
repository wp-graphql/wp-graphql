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
	 * @param bool   $store_document Whether to store the document (if it doesn't exist). Default true.
	 * @return void
	 */
	public function store( string $url, string $query_hash, string $variables_hash, string $query_doc, string $variables, array $cache_keys, bool $store_document = true ): void {
		global $wpdb;

		$documents_table  = Schema::get_documents_table_name();
		$url_keys_table   = Schema::get_url_keys_table_name();
		$executions_table = Schema::get_executions_table_name();
		$url_hash         = hash( 'sha256', $url );
		$variables_hash   = $variables_hash ?: '';

		// Store document only if requested and it doesn't already exist.
		if ( $store_document ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
					"INSERT IGNORE INTO {$documents_table} (query_hash, query_document) VALUES (%s, %s)",
					$query_hash,
					$query_doc
				)
			);
		}

		// Stable execution row for warm GET lookup (independent of cache-key purges).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$executions_table} (query_hash, variables_hash, url, variables, last_executed_at)
				VALUES (%s, %s, %s, %s, UTC_TIMESTAMP())
				ON DUPLICATE KEY UPDATE url = VALUES(url), variables = VALUES(variables), last_executed_at = UTC_TIMESTAMP()",
				$query_hash,
				$variables_hash,
				$url,
				$variables
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Always store execution data (variables + cache keys) if document exists.
		// This allows tracking executions of pre-registered documents.
		foreach ( $cache_keys as $cache_key ) {
			// Use INSERT IGNORE to handle duplicates gracefully.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
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

		$documents_table  = Schema::get_documents_table_name();
		$executions_table = Schema::get_executions_table_name();
		$variables_hash   = $variables_hash ?: '';

		// JOIN documents + executions (execution survives cache-key purges).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT d.query_document, e.variables 
				FROM {$documents_table} d
				INNER JOIN {$executions_table} e ON d.query_hash = e.query_hash
				WHERE e.query_hash = %s AND e.variables_hash = %s
				LIMIT 1",
				$query_hash,
				$variables_hash
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $result ) {
			return null;
		}

		return [
			'query_document' => $result['query_document'],
			'variables'      => $result['variables'],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function touch_execution( string $query_hash, string $variables_hash ): void {
		global $wpdb;

		$executions_table = Schema::get_executions_table_name();
		$variables_hash   = $variables_hash ?: '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$executions_table} SET last_executed_at = UTC_TIMESTAMP() WHERE query_hash = %s AND variables_hash = %s",
				$query_hash,
				$variables_hash
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
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

	/**
	 * Delete all cache-key rows for this URL (purge-tag index only).
	 *
	 * @param string $url The URL to delete all entries for.
	 * @return void
	 */
	public function delete_by_url( string $url ): void {
		global $wpdb;

		$url_keys_table = Schema::get_url_keys_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$url_keys_table,
			[ 'url' => $url ],
			[ '%s' ]
		);

		// Note: Documents are not deleted here even if they become orphaned.
		// Garbage collection can clean up orphaned documents later if needed.
	}

	/**
	 * Check if a document exists for a given query hash
	 *
	 * @param string $query_hash SHA-256 hash of the normalized query document.
	 * @return bool True if document exists, false otherwise.
	 */
	public function document_exists( string $query_hash ): bool {
		global $wpdb;

		$documents_table = Schema::get_documents_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
				"SELECT COUNT(*) FROM {$documents_table} WHERE query_hash = %s",
				$query_hash
			)
		);

		return (int) $count > 0;
	}
}
