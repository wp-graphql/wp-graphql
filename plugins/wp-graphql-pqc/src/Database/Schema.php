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
	 * Get the url_keys table name with WordPress prefix
	 *
	 * @return string
	 */
	public static function get_url_keys_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_url_keys';
	}

	/**
	 * Get the table name with WordPress prefix (backward compatibility)
	 *
	 * @deprecated Use get_url_keys_table_name() instead
	 * @return string
	 */
	public static function get_table_name(): string {
		return self::get_url_keys_table_name();
	}

	/**
	 * Check if the old table structure exists (has query_document column in url_keys table)
	 *
	 * @return bool
	 */
	private static function has_old_structure(): bool {
		global $wpdb;

		$url_keys_table = self::get_url_keys_table_name();

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$url_keys_table
			)
		);

		if ( $url_keys_table !== $table_exists ) {
			return false;
		}

		// Check if query_document column exists in url_keys table (old structure).
		// Use DESCRIBE to check for the column.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results( "DESCRIBE {$url_keys_table}" );

		if ( empty( $columns ) ) {
			return false;
		}

		foreach ( $columns as $column ) {
			if ( 'query_document' === $column->Field ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create the database tables
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$documents_table = self::get_documents_table_name();
		$url_keys_table = self::get_url_keys_table_name();

		// If old structure exists, drop tables to recreate with normalized structure.
		// This is a breaking change for beta, so we can safely drop and recreate.
		if ( self::has_old_structure() ) {
			self::drop_table();
		}

		// Create documents table first (referenced by url_keys).
		$documents_sql = "CREATE TABLE {$documents_table} (
			query_hash varchar(64) NOT NULL PRIMARY KEY,
			query_document longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_query_hash (query_hash)
		) {$charset_collate};";

		dbDelta( $documents_sql );

		// Create url_keys table (references documents table).
		// Note: We don't use FOREIGN KEY constraints because dbDelta doesn't handle them well,
		// and many WordPress setups use MyISAM or have foreign key constraints disabled.
		// We rely on application-level integrity instead.
		$url_keys_sql = "CREATE TABLE {$url_keys_table} (
			url_hash varchar(64) NOT NULL,
			url varchar(2083) NOT NULL,
			query_hash varchar(64) NOT NULL,
			variables_hash varchar(64) NOT NULL,
			variables longtext NOT NULL,
			cache_key varchar(255) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (url_hash, cache_key),
			INDEX idx_cache_key (cache_key),
			INDEX idx_query_lookup (query_hash, variables_hash),
			INDEX idx_url_hash (url_hash)
		) {$charset_collate};";

		dbDelta( $url_keys_sql );

		// Check if both tables were created successfully.
		$documents_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$documents_table
			)
		);

		$url_keys_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$url_keys_table
			)
		);

		return ( $documents_table === $documents_exists ) && ( $url_keys_table === $url_keys_exists );
	}

	/**
	 * Drop the database tables
	 *
	 * @return bool True on success, false on failure
	 */
	public static function drop_table(): bool {
		global $wpdb;

		$url_keys_table = self::get_url_keys_table_name();
		$documents_table = self::get_documents_table_name();

		// Drop url_keys first (has foreign key to documents).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$url_keys_table}" );

		// Drop documents table.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$documents_table}" );

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
		$url_keys_table = self::get_url_keys_table_name();

		$documents_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$documents_table
			)
		);

		$url_keys_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$url_keys_table
			)
		);

		return ( $documents_table === $documents_exists ) && ( $url_keys_table === $url_keys_exists );
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
