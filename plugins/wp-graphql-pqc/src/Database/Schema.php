<?php
/**
 * Database schema and migration handling
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
	 * Get the documents table name with WordPress prefix
	 *
	 * @return string
	 */
	public static function get_documents_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_documents';
	}

	/**
	 * Persisted URL rows (one per url_hash) for the key map and GC by last_seen_at.
	 *
	 * @return string
	 */
	public static function get_urls_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_urls';
	}

	/**
	 * Deduplicated Smart Cache key strings.
	 *
	 * @return string
	 */
	public static function get_cache_keys_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_cache_keys';
	}

	/**
	 * Junction: cache key id ↔ url id (many-to-many).
	 *
	 * @return string
	 */
	public static function get_key_urls_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_key_urls';
	}

	/**
	 * Legacy junction table name (pre–normalized key map).
	 *
	 * @deprecated Removed after migration to urls + cache_keys + key_urls. Do not use in new code.
	 * @return string
	 */
	public static function get_url_keys_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_url_keys';
	}

	/**
	 * Executions table: stable lookup for warm GET (query_hash + variables_hash → document variables).
	 * Purging removes key map rows only; executions stay until GC.
	 *
	 * @return string
	 */
	public static function get_executions_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_executions';
	}

	/**
	 * Get the table name with WordPress prefix (backward compatibility)
	 *
	 * @deprecated Use get_key_urls_table_name() or get_urls_table_name() instead.
	 * @return string
	 */
	public static function get_table_name(): string {
		return self::get_url_keys_table_name();
	}

	/**
	 * Whether the legacy url_keys table exists.
	 *
	 * @return bool
	 */
	private static function legacy_url_keys_table_exists(): bool {
		global $wpdb;

		$legacy = self::get_url_keys_table_name();

		return $legacy === $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$legacy
			)
		);
	}

	/**
	 * Whether the normalized urls table exists.
	 *
	 * @return bool
	 */
	private static function urls_table_exists(): bool {
		global $wpdb;

		$table = self::get_urls_table_name();

		return $table === $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);
	}

	/**
	 * Create normalized key map tables (urls, cache_keys, key_urls).
	 *
	 * @return void
	 */
	private static function create_keymap_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$urls_table       = self::get_urls_table_name();
		$keys_table       = self::get_cache_keys_table_name();
		$key_urls_table   = self::get_key_urls_table_name();

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
	 * Copy legacy url_keys rows into urls + cache_keys + key_urls, then drop legacy.
	 *
	 * @return void
	 */
	private static function migrate_legacy_url_keys_to_keymap(): void {
		global $wpdb;

		if ( ! self::legacy_url_keys_table_exists() ) {
			return;
		}

		// activate() creates executions before this runs; maybe_upgrade_executions_table() may have skipped
		// backfill because the table already existed empty. Populate from legacy before it is dropped.
		self::backfill_executions_from_legacy_tag_table();

		self::create_keymap_tables();

		$legacy = self::get_url_keys_table_name();
		$urls   = self::get_urls_table_name();
		$keys   = self::get_cache_keys_table_name();
		$ju     = self::get_key_urls_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$wpdb->query(
			"INSERT INTO {$urls} (url, url_hash, query_hash, variables_hash, last_seen_at)
			SELECT
				( SELECT l2.url FROM {$legacy} l2 WHERE l2.url_hash = x.url_hash ORDER BY l2.created_at DESC LIMIT 1 ),
				x.url_hash,
				( SELECT l2.query_hash FROM {$legacy} l2 WHERE l2.url_hash = x.url_hash LIMIT 1 ),
				( SELECT l2.variables_hash FROM {$legacy} l2 WHERE l2.url_hash = x.url_hash LIMIT 1 ),
				( SELECT MAX( l2.created_at ) FROM {$legacy} l2 WHERE l2.url_hash = x.url_hash )
			FROM ( SELECT DISTINCT url_hash FROM {$legacy} ) x"
		);

		$wpdb->query(
			"INSERT IGNORE INTO {$keys} (cache_key) SELECT DISTINCT cache_key FROM {$legacy}"
		);

		$wpdb->query(
			"INSERT IGNORE INTO {$ju} (key_id, url_id)
			SELECT k.id, u.id
			FROM {$legacy} l
			INNER JOIN {$urls} u ON u.url_hash = l.url_hash
			INNER JOIN {$keys} k ON k.cache_key = l.cache_key"
		);

		$wpdb->query( "DROP TABLE IF EXISTS {$legacy}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Run on init: migrate legacy url_keys after executions backfill has had a chance to read it.
	 *
	 * @since next-version
	 * @return void
	 */
	public static function maybe_migrate_url_keys_to_keymap(): void {
		if ( ! self::legacy_url_keys_table_exists() ) {
			return;
		}

		self::migrate_legacy_url_keys_to_keymap();
	}

	/**
	 * Create the database tables
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
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
		self::migrate_legacy_url_keys_to_keymap();

		$documents_exists  = $documents_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $documents_table ) );
		$executions_exists = $executions_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $executions_table ) );

		return $documents_exists
			&& $executions_exists
			&& self::keymap_tables_exist();
	}

	/**
	 * Whether urls, cache_keys, and key_urls exist.
	 *
	 * @since next-version
	 * @return bool
	 */
	public static function keymap_tables_exist(): bool {
		global $wpdb;

		foreach ( [ self::get_urls_table_name(), self::get_cache_keys_table_name(), self::get_key_urls_table_name() ] as $table ) {
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
		$legacy_url_keys  = self::get_url_keys_table_name();
		$executions_table = self::get_executions_table_name();
		$documents_table  = self::get_documents_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
		$wpdb->query( "DROP TABLE IF EXISTS {$key_urls}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$legacy_url_keys}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$urls}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$cache_keys}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$executions_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$documents_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return true;
	}

	/**
	 * Check if the tables exist
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$documents_table = self::get_documents_table_name();

		$documents_exists = $documents_table === $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$documents_table
			)
		);

		$executions_table  = self::get_executions_table_name();
		$executions_exists = $executions_table === $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$executions_table
			)
		);

		return $documents_exists && $executions_exists && self::keymap_tables_exist();
	}

	/**
	 * Create the executions table and backfill from legacy url_keys when upgrading older installs.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_executions_table(): void {
		global $wpdb;

		$executions_table = self::get_executions_table_name();
		$exists           = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$executions_table
			)
		);

		if ( $executions_table === $exists ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$executions_sql  = "CREATE TABLE {$executions_table} (
			query_hash varchar(64) NOT NULL,
			variables_hash varchar(64) NOT NULL,
			url varchar(2083) NOT NULL,
			variables longtext NOT NULL,
			last_executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (query_hash, variables_hash),
			INDEX idx_last_executed (last_executed_at)
		) {$charset_collate};";

		dbDelta( $executions_sql );

		self::backfill_executions_from_legacy_tag_table();
	}

	/**
	 * Copy distinct executions from legacy url_keys (before it is migrated away).
	 *
	 * @return void
	 */
	private static function backfill_executions_from_legacy_tag_table(): void {
		global $wpdb;

		$exec = self::get_executions_table_name();

		if ( self::legacy_url_keys_table_exists() ) {
			$uk = self::get_url_keys_table_name();
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
			$wpdb->query(
				"INSERT IGNORE INTO {$exec} (query_hash, variables_hash, url, variables, last_executed_at)
				SELECT query_hash, variables_hash, MIN(url), MIN(variables), MAX(created_at) FROM {$uk}
				GROUP BY query_hash, variables_hash"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}

		if ( self::urls_table_exists() ) {
			$urls = self::get_urls_table_name();
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from Schema.
			$wpdb->query(
				"INSERT IGNORE INTO {$exec} (query_hash, variables_hash, url, variables, last_executed_at)
				SELECT u.query_hash, u.variables_hash, MIN(u.url), '', MAX(u.last_seen_at)
				FROM {$urls} u
				GROUP BY u.query_hash, u.variables_hash"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Get the current database version
	 *
	 * @return string
	 */
	public static function get_db_version(): string {
		return get_option( 'wpgraphql_pqc_db_version', '0' );
	}

	/**
	 * Update the database version
	 *
	 * @param string $version Version number.
	 * @return void
	 */
	public static function update_db_version( string $version ): void {
		update_option( 'wpgraphql_pqc_db_version', $version );
	}
}
