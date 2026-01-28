<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Document\Group;
use GraphQL\Server\RequestError;

class GarbageCollection {

	/**
	 * @param integer $number_of_posts  Number of post ids matching criteria.
	 *
	 * @return int[]  Array of post ids
	 */
	public static function get_documents_by_age( $number_of_posts = 100 ) {
		// $days_ago  Posts older than this many days ago
		$days_ago = get_graphql_setting( 'query_garbage_collect_age', null, 'graphql_persisted_queries_section' );
		if ( 1 > $days_ago || ! is_numeric( $days_ago ) ) {
			return [];
		}

		// Query for saved query documents that are older than age and not skipping garbage collection.
		// Get documents where no group taxonmy term is set.
		$wp_query = new \WP_Query(
			[
				'post_type'      => Document::TYPE_NAME,
				'post_status'    => 'publish',
				'posts_per_page' => $number_of_posts,
				'fields'         => 'ids',
				'date_query'     => [
					[
						'column' => 'post_modified_gmt',
						'before' => $days_ago . ' days ago',
					],
				],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => [
					[
						'taxonomy' => Group::TAXONOMY_NAME,
						'field'    => 'name',
						'operator' => 'NOT EXISTS',
					],
				],
			]
		);

		/**
		 * Because 'fields' returns 'ids', this returns array of post ints. Satisfy phpstan.
		 *
		 * @var int[]
		 */
		return $wp_query->get_posts();
	}
}
