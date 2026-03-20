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
	 * Get the table name with WordPress prefix
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpgraphql_pqc_url_keys';
	}

	/**
	 * Create the database table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			url_hash varchar(64) NOT NULL,
			url varchar(2083) NOT NULL,
			query_hash varchar(64) NOT NULL,
			variables_hash varchar(64) NOT NULL,
			query_document longtext NOT NULL,
			variables longtext NOT NULL,
			cache_key varchar(255) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (url_hash, cache_key),
			INDEX idx_cache_key (cache_key),
			INDEX idx_query_lookup (query_hash, variables_hash)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $table_exists;
	}

	/**
	 * Drop the database table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function drop_table(): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		return true;
	}

	/**
	 * Check if the table exists
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $table_exists;
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
