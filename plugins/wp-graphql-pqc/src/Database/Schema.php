<?php
/**
 * Database schema for PQC tables (documents, executions, normalized key map).
 *
 * @package WPGraphQL\PQC\Database
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Database;

/**
 * Class Schema
 *
 * @package WPGraphQL\PQC\Database
 */
class Schema {

	/**
	 * Legacy denormalized table suffix (pre-keymap). Not created by current code; dropped on uninstall if present.
	 */
	private const LEGACY_URL_KEYS_SUFFIX = 'wpgraphql_pqc_url_keys';

	/**
	 * Get the documents table name with WordPress prefix
	 */
	public static function get_documents_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_documents';
	}

	/**
	 * Persisted URL rows (one per url_hash) for the key map and GC by last_seen_at.
	 */
	public static function get_urls_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_urls';
	}

	/**
	 * Deduplicated Smart Cache key strings.
	 */
	public static function get_cache_keys_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_cache_keys';
	}

	/**
	 * Junction: cache key id ↔ url id (many-to-many).
	 */
	public static function get_key_urls_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_key_urls';
	}

	/**
	 * Executions table: stable lookup for warm GET (query_hash + variables_hash → document variables).
	 * Purging removes key map rows only; executions stay until GC.
	 */
	public static function get_executions_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_executions';
	}

	/**
	 * Legacy table name (uninstall only).
	 */
	private static function get_legacy_url_keys_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::LEGACY_URL_KEYS_SUFFIX;
	}

	/**
	 * Create or upgrade all PQC tables via dbDelta.
	 *
	 * Safe to call on every request; idempotent.
	 */
	public static function ensure_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate  = $wpdb->get_charset_collate();
		$documents_table  = self::get_documents_table_name();
		$executions_table = self::get_executions_table_name();

		$documents_sql = "CREATE TABLE {$documents_table} (
			query_hash varchar(64) NOT NULL PRIMARY KEY,
			query_document longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_query_hash (query_hash)
		) {$charset_collate};";

		dbDelta( $documents_sql );

		$executions_sql = "CREATE TABLE {$executions_table} (
			query_hash varchar(64) NOT NULL,
			variables_hash varchar(64) NOT NULL,
			url varchar(2083) NOT NULL,
			variables longtext NOT NULL,
			last_executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (query_hash, variables_hash),
			INDEX idx_last_executed (last_executed_at)
		) {$charset_collate};";

		dbDelta( $executions_sql );

		self::create_keymap_tables();
	}

	/**
	 * Create normalized key map tables (urls, cache_keys, key_urls).
	 */
	private static function create_keymap_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$urls_table      = self::get_urls_table_name();
		$keys_table      = self::get_cache_keys_table_name();
		$key_urls_table  = self::get_key_urls_table_name();

		$urls_sql = "CREATE TABLE {$urls_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2083) NOT NULL,
			url_hash char(64) NOT NULL,
			query_hash varchar(64) NOT NULL,
			variables_hash varchar(64) NOT NULL,
			last_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY url_hash (url_hash),
			KEY idx_query_hash (query_hash),
			KEY idx_last_seen (last_seen_at)
		) {$charset_collate};";

		dbDelta( $urls_sql );

		$keys_sql = "CREATE TABLE {$keys_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cache_key varchar(255) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY cache_key (cache_key)
		) {$charset_collate};";

		dbDelta( $keys_sql );

		$key_urls_sql = "CREATE TABLE {$key_urls_table} (
			key_id bigint(20) unsigned NOT NULL,
			url_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (key_id, url_id),
			KEY url_id (url_id)
		) {$charset_collate};";

		dbDelta( $key_urls_sql );
	}

	/**
	 * Create the database tables (activation hook).
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		self::ensure_schema();

		return self::table_exists();
	}

	/**
	 * Whether urls, cache_keys, and key_urls exist.
	 */
	public static function keymap_tables_exist(): bool {
		global $wpdb;

		foreach ( [ self::get_urls_table_name(), self::get_cache_keys_table_name(), self::get_key_urls_table_name() ] as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema introspection; table name from prepare().
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Drop the database tables
	 *
	 * @return bool True on success, false on failure
	 */
	public static function drop_table(): bool {
		global $wpdb;

		$key_urls         = self::get_key_urls_table_name();
		$urls             = self::get_urls_table_name();
		$cache_keys       = self::get_cache_keys_table_name();
		$legacy_url_keys  = self::get_legacy_url_keys_table_name();
		$executions_table = self::get_executions_table_name();
		$documents_table  = self::get_documents_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin uninstall; table names from Schema.
		$wpdb->query( "DROP TABLE IF EXISTS {$key_urls}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$legacy_url_keys}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$urls}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$cache_keys}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$executions_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$documents_table}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return true;
	}

	/**
	 * Check if the tables exist
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$documents_table = self::get_documents_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema introspection; table name from prepare().
		$documents_exists = $documents_table === $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$documents_table
			)
		);

		$executions_table = self::get_executions_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema introspection; table name from prepare().
		$executions_exists = $executions_table === $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$executions_table
			)
		);

		return $documents_exists && $executions_exists && self::keymap_tables_exist();
	}

	/**
	 * Get the current database version
	 */
	public static function get_db_version(): string {
		return get_option( 'wpgraphql_pqc_db_version', '0' );
	}

	/**
	 * Update the database version
	 *
	 * @param string $version Version number.
	 */
	public static function update_db_version( string $version ): void {
		update_option( 'wpgraphql_pqc_db_version', $version );
	}
}
