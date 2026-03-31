<?php
/**
 * Garbage collection for old index entries
 *
 * @package WPGraphQL\PQC\Cron
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Cron;

use WPGraphQL\PQC\Database\Schema;

/**
 * Class GarbageCollection
 *
 * @package WPGraphQL\PQC\Cron
 */
class GarbageCollection {

	/**
	 * Initialize garbage collection
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpgraphql_pqc_garbage_collection', [ $this, 'run' ] );
	}

	/**
	 * Run garbage collection
	 *
	 * @return void
	 */
	public function run(): void {
		global $wpdb;

		$url_keys_table   = Schema::get_url_keys_table_name();
		$documents_table  = Schema::get_documents_table_name();
		$executions_table = Schema::get_executions_table_name();

		// Get TTL in days (default: 7 days).
		$ttl_days = apply_filters( 'wpgraphql_pqc_ttl_days', 7 );
		$ttl_days = absint( $ttl_days );

		if ( $ttl_days < 1 ) {
			$ttl_days = 7;
		}

		// Calculate cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$ttl_days} days" ) );

		// Delete old url_keys rows (Smart Cache tag index). Warm GET still resolves via executions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
				"DELETE FROM {$url_keys_table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		// Clean up orphaned documents (no execution row). Executions are not time-purged here so
		// long edge TTLs without origin traffic do not drop the persisted operation.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL -- table names from Schema.
		$wpdb->query(
			sprintf(
				'DELETE d FROM %s d LEFT JOIN %s e ON d.query_hash = e.query_hash WHERE e.query_hash IS NULL',
				$documents_table,
				$executions_table
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL
	}
}
