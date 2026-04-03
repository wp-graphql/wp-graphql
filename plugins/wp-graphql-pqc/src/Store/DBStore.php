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
	 * @param string   $url            The full URL (e.g., /graphql/persisted/{queryHash}/variables/{variablesHash}).
	 * @param string   $query_hash     SHA-256 hash of the normalized query document.
	 * @param string   $variables_hash SHA-256 hash of the canonicalized variables JSON.
	 * @param string   $query_doc      The full GraphQL query document.
	 * @param string   $variables      The variables JSON string.
	 * @param string[] $cache_keys     Cache keys from X-GraphQL-Keys header.
	 * @param bool     $store_document Whether to store the document (if it doesn't exist). Default true.
	 * @param bool     $record_cache_tags Whether to write URL ↔ cache key associations (edge purge index).
	 */
	public function store( string $url, string $query_hash, string $variables_hash, string $query_doc, string $variables, array $cache_keys, bool $store_document = true, bool $record_cache_tags = true ): void {
		global $wpdb;

		$documents_table  = Schema::get_documents_table_name();
		$executions_table = Schema::get_executions_table_name();
		$urls_table       = Schema::get_urls_table_name();
		$keys_table       = Schema::get_cache_keys_table_name();
		$key_urls_table   = Schema::get_key_urls_table_name();
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

		if ( ! $record_cache_tags || empty( $cache_keys ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$urls_table} (url, url_hash, query_hash, variables_hash, last_seen_at)
				VALUES (%s, %s, %s, %s, UTC_TIMESTAMP())
				ON DUPLICATE KEY UPDATE
					last_seen_at = UTC_TIMESTAMP(),
					url = VALUES(url),
					query_hash = VALUES(query_hash),
					variables_hash = VALUES(variables_hash)",
				$url,
				$url_hash,
				$query_hash,
				$variables_hash
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$url_id = (int) $wpdb->insert_id;
		if ( $url_id <= 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$url_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
					"SELECT id FROM {$urls_table} WHERE url_hash = %s LIMIT 1",
					$url_hash
				)
			);
		}

		if ( $url_id <= 0 ) {
			return;
		}

		foreach ( $cache_keys as $cache_key ) {
			if ( '' === $cache_key ) {
				continue;
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$keys_table} (cache_key) VALUES (%s)",
					$cache_key
				)
			);

			$key_id = (int) $wpdb->insert_id;
			if ( $key_id <= 0 ) {
				$key_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$keys_table} WHERE cache_key = %s LIMIT 1",
						$cache_key
					)
				);
			}

			if ( $key_id <= 0 ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$key_urls_table} (key_id, url_id) VALUES (%d, %d)",
					$key_id,
					$url_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Get query document and variables by query hash and variables hash
	 *
	 * @param string $query_hash     SHA-256 hash of the normalized query document.
	 * @param string $variables_hash SHA-256 hash of the canonicalized variables JSON.
	 * @return array{query_document: string, variables: string}|null
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
	 * @param string $query_hash     Query hash.
	 * @param string $variables_hash Variables hash (empty string if none).
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
	 * @return string[]
	 */
	public function get_urls_for_key( string $cache_key ): array {
		global $wpdb;

		$urls_table     = Schema::get_urls_table_name();
		$keys_table     = Schema::get_cache_keys_table_name();
		$key_urls_table = Schema::get_key_urls_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.url FROM {$key_urls_table} ku
				INNER JOIN {$keys_table} k ON k.id = ku.key_id
				INNER JOIN {$urls_table} u ON u.id = ku.url_id
				WHERE k.cache_key = %s",
				$cache_key
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results ?: [];
	}

	/**
	 * @param string $query_hash SHA-256 hash of the normalized query document.
	 * @return string[]
	 */
	public function get_urls_for_query_hash( string $query_hash ): array {
		global $wpdb;

		$urls_table = Schema::get_urls_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
				"SELECT DISTINCT url FROM {$urls_table} WHERE query_hash = %s",
				$query_hash
			)
		);

		return $results ?: [];
	}

	/**
	 * Delete all index entries for a specific cache key
	 *
	 * @param string $cache_key The cache key to delete.
	 */
	public function delete_by_key( string $cache_key ): void {
		global $wpdb;

		$keys_table     = Schema::get_cache_keys_table_name();
		$key_urls_table = Schema::get_key_urls_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE ku FROM {$key_urls_table} ku
				INNER JOIN {$keys_table} k ON k.id = ku.key_id
				WHERE k.cache_key = %s",
				$cache_key
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->delete_orphan_cache_keys();
		$this->delete_orphan_urls();
	}

	/**
	 * Delete all cache-key rows for this URL (key map only).
	 *
	 * @param string $url The URL to delete all entries for.
	 */
	public function delete_by_url( string $url ): void {
		global $wpdb;

		$urls_table     = Schema::get_urls_table_name();
		$key_urls_table = Schema::get_key_urls_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$url_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
				"SELECT id FROM {$urls_table} WHERE url = %s LIMIT 1",
				$url
			)
		);

		if ( $url_id <= 0 ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$key_urls_table} WHERE url_id = %d",
				$url_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$urls_table} WHERE id = %d",
				$url_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->delete_orphan_cache_keys();
	}

	/**
	 * Remove cache_keys rows that no longer appear in key_urls.
	 */
	private function delete_orphan_cache_keys(): void {
		global $wpdb;

		$keys_table     = Schema::get_cache_keys_table_name();
		$key_urls_table = Schema::get_key_urls_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$wpdb->query(
			"DELETE k FROM {$keys_table} k
			LEFT JOIN {$key_urls_table} ku ON ku.key_id = k.id
			WHERE ku.key_id IS NULL"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Remove urls rows that have no key_urls (defensive; normal deletes already remove the url row).
	 */
	private function delete_orphan_urls(): void {
		global $wpdb;

		$urls_table     = Schema::get_urls_table_name();
		$key_urls_table = Schema::get_key_urls_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$wpdb->query(
			"DELETE u FROM {$urls_table} u
			LEFT JOIN {$key_urls_table} ku ON ku.url_id = u.id
			WHERE ku.url_id IS NULL"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
