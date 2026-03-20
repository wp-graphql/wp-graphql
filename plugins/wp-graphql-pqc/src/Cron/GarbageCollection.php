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

		$url_keys_table = Schema::get_url_keys_table_name();
		$documents_table = Schema::get_documents_table_name();

		// Get TTL in days (default: 7 days).
		$ttl_days = apply_filters( 'wpgraphql_pqc_ttl_days', 7 );
		$ttl_days = absint( $ttl_days );

		if ( $ttl_days < 1 ) {
			$ttl_days = 7;
		}

		// Calculate cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$ttl_days} days" ) );

		// Delete old url_keys entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$url_keys_table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		// Clean up orphaned documents (documents with no url_keys entries).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE d FROM {$documents_table} d
			LEFT JOIN {$url_keys_table} uk ON d.query_hash = uk.query_hash
			WHERE uk.query_hash IS NULL"
		);
	}
}
