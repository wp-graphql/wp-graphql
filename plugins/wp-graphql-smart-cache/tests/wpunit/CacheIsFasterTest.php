<?php

namespace WPGraphQL\SmartCache;

use SebastianBergmann\Timer\Timer;
use SebastianBergmann\Timer\Duration;
use WPGraphQL\Cache\Query;

/**
 * Test the wp-graphql request to cached query is faster
 */

class CacheIsFasterTest extends \Codeception\TestCase\WPTestCase {
	public $timer;

	public function _before() {
		$this->timer = new Timer;
	}

	public function _after() {
	}

	public function testCachedQueryIsFaster() {
		$this->timer->start();
		$query_string = '{ __typename }';
		$result = graphql( [ 'query' => $query_string ] );
		$duration1 = $this->timer->stop();
		codecept_debug( sprintf("\nDuration time %f seconds\n", $duration1->asSeconds() ) );

		$this->timer->start();
		$query_string = '{ __typename }';
		$result = graphql( [ 'query' => $query_string ] );
		$duration2 = $this->timer->stop();
		codecept_debug( sprintf("\nDuration time %f seconds\n", $duration2->asSeconds() ) );

		// Intentionally make it bigger for this example.
		$this->assertLessThan( $duration1->asSeconds()+10, $duration2->asSeconds() );
	}

	public function testCompareCachedVsNotCached() {
		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		$magic_number = 30;

		// Create a post
		// Make a bunch of requests
		// Average the time for requests
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish'
			]
		);
		// First query not included in average results. First query of a new post is heavy.
		$response = graphql([ 'query' => $query ]);
		for( $i=0; $i<$magic_number; $i++ ) {
			$this->timer->start();
			$response = graphql([ 'query' => $query ]);
			$duration = $this->timer->stop();
			$duration_cached[] = $duration->asSeconds();
		}
		$avg_cached = array_sum( $duration_cached ) / $magic_number;
		\wp_delete_post( $post_id );

		delete_option( 'graphql_cache_section' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish'
			]
		);
		// First query not included in average results. First query of a new post is heavy.
		$response = graphql([ 'query' => $query ]);
		for( $i=0; $i<$magic_number; $i++ ) {
			$this->timer->start();
			$response = graphql([ 'query' => $query ]);
			$duration = $this->timer->stop();
			$duration_not[] = $duration->asSeconds();
		}
		$avg_not = array_sum( $duration_not ) / $magic_number;
		\wp_delete_post( $post_id );

		codecept_debug( sprintf("\nDuration time %d requests. Cached %f vs not cached %f\n", $magic_number, $avg_cached, $avg_not ) );
		$this->assertLessThan( $avg_not, $avg_cached );
	}
}
