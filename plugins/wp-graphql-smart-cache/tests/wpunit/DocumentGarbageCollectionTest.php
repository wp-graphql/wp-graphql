<?php
/**
 * @package Wp_Graphql_Smart_Cache
 *
 * Test saved query garbage collection clean up of queries after certain age.
 * Test cron job wakes and deletes as expected.
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document\GarbageCollection;
use WPGraphQL\SmartCache\Document\Group;

class DocumentGarbageCollectionTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		update_option(
			'graphql_persisted_queries_section',
			[ 
				'query_garbage_collect' => 'off',
			]
		);
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	public function updateAge( $age ) {
		update_option(
			'graphql_persisted_queries_section',
			[ 
				'query_garbage_collect' => 'on',
				'query_garbage_collect_age' => $age,
			]
		);
	}

	public function testQueriesAreDeletedByJob() {
		// Enable garbage collection for queries after an age
		$this->updateAge( 20 );

		$age_list = [
			'11 days ago',
			'21 days ago',
		];

		// Create saved queries with various modified dates/ages
		$counter = 0;
		foreach ( $age_list as $one_date ) {
			$date_string = date( 'Y-m-d H:i:s', strtotime( $one_date ) );
			// Create more than one saved query for this age
			for ( $i = 0; $i < 3; $i++ ) {
				$post_id = self::factory()->post->create(
					[
						'post_type' => 'graphql_document',
						'post_date' => $date_string,
						'post_content' => sprintf( "query Saved_%d { typename }", $counter ),
						'post_title' => sprintf( "query %d", $counter ),
					]
				);
				$counter++;
			}
		}

		// Create some queries that are excluded from garbage collection delete, in a 'group'
		for ( $i = 0; $i < 3; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_type' => 'graphql_document',
					'post_date' => $date_string,
					'post_content' => sprintf( "query Saved_%d { typename }", $counter ),
					'post_title' => sprintf( "query %d", $counter ),
				]
			);
			wp_set_post_terms( $post_id, 'foo-bar', Group::TAXONOMY_NAME );
			$counter++;
		}

		// Check number of posts we would delete older than 10 days.
		$this->updateAge( 10 );
		$this->assertCount( 6, GarbageCollection::get_documents_by_age() );

		// Check number of posts we would delete older than 20 days.
		$this->updateAge( 20 );
		$this->assertCount( 3, GarbageCollection::get_documents_by_age() );

		// Verify delete event is not scheduled before the garbage collection event runs
		$this->assertFalse( wp_next_scheduled( 'wpgraphql_smart_cache_query_garbage_collect_deletes' ) );

		do_action( 'wpgraphql_smart_cache_query_garbage_collect' );

		// Verify delete job scheduled, this WP api returns timestamp integer
		$this->assertIsInt( wp_next_scheduled( 'wpgraphql_smart_cache_query_garbage_collect_deletes' ) );

		// filter batch size to we only delete a few of our aged queries and reschedule the next job
		add_filter( 'wpgraphql_document_garbage_collect_batch_size', function () { return 2; } );

		// Fire the delete action and verify the number of posts deleted
		do_action( 'wpgraphql_smart_cache_query_garbage_collect_deletes' );
		$this->assertCount( 1, GarbageCollection::get_documents_by_age() );

		// Fire the delete action again, verify expected queries are removed.
		do_action( 'wpgraphql_smart_cache_query_garbage_collect_deletes' );
		$this->updateAge( 10 );
		$this->assertCount( 3, GarbageCollection::get_documents_by_age() );
		$this->updateAge( 20 );
		$this->assertCount( 0, GarbageCollection::get_documents_by_age( 20 ) );
	}

}
