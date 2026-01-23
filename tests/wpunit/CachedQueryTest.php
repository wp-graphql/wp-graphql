<?php

namespace WPGraphQL\Cache;

use WPGraphQL\SmartCache\Cache\Query;
use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Utils;

/**
 * Test the content class
 */
class CachedQueryTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_cache_section' );
	}

	public function _after() {
		delete_option( 'graphql_cache_section' );
	}

	/**
	 * Put content in the cache.
	 * Make graphql request.
	 * Very we see the results from cache.
	 */
	public function testGetResultsFromCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		$results_object = new Results();
		$key = $results_object->the_results_key( null, $query );

		// Put something in the cache for the query key that proves it came from cache.
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$results_object->save( $key, $expected );

		// Verify the response contains what we put in cache
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($expected['data'], $response['data']);
	}

	public function testOperationNameAndVariablesGetResultsFromCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = "query GetPosts(\$count:Int){
			posts(first:\$count){
			 nodes{
			  id
			  title
			 }
			}
		  }
		  query GetPostsWithSlug(\$count:Int){
			posts(first:\$count){
			 nodes{
			  id
			  title
			  slug
			 }
			}
		  }
		";

		$results_object = new Results();

		// Cache for one operation and variables
		$key = $results_object->the_results_key( null, $query, [ "count" => 1 ], "GetPosts" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPosts. Count 1'
			]
		];
		$results_object->save( $key, $value );

		// Cache for one operation and variables
		$key = $results_object->the_results_key( null, $query, [ "count" => 2 ], "GetPosts" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPosts. Count 2'
			]
		];
		$results_object->save( $key, $value );

		// Cache for one operation and variables
		$key = $results_object->the_results_key( null, $query, [ "count" => 2 ], "GetPostsWithSlug" );
		$value = [
			'data' => [
				'foo' => 'Response for GetPostsWithSlug. Count 2'
			]
		];
		$results_object->save( $key, $value );

		// Verify the response contains what we put in cache
		$response = graphql([
			'query' => $query,
			'variables' => [ "count" => 1 ],
			'operationName' => 'GetPosts'
		]);
		$this->assertEquals( 'Response for GetPosts. Count 1', $response['data']['foo'] );

		$response = graphql([
			'query' => $query,
			'variables' => [ "count" => 2 ],
			'operationName' => 'GetPosts'
		]);
		$this->assertEquals( 'Response for GetPosts. Count 2', $response['data']['foo'] );

		$response = graphql([
			'query' => $query,
			'variables' => [ "count" => 2 ],
			'operationName' => 'GetPostsWithSlug'
		]);
		$this->assertEquals( 'Response for GetPostsWithSlug. Count 2', $response['data']['foo'] );

	}

	public function testQueryIdGetResultsFromCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";
		$query_id = "foo-bar-query";

		// Create/save persisted query for the query and query id
		$saved_query = new Document();
		$saved_query->save( $query_id, $query );

		$results_object = new Results();
		$key = $results_object->the_results_key( $query_id, null );

		// Put something in the cache for the query key that proves it came from cache.
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$results_object->save( $key, $expected );

		// Verify the response contains what we put in cache
		$response = graphql([ 'queryId' => $query_id ]);
		$this->assertEquals($expected['data'], $response['data']);
	}

	public function testPurgeCacheWhenNotEnabled() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'off' ] );

		$results_object = new Results();
		$response = $results_object->purge_all();
		$this->assertNotFalse( $response );
	}

	public function testPurgeCacheWhenNothingCached() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$results_object = new Results();
		$response = $results_object->purge_all();
		$this->assertTrue( $response );
	}

	public function testPurgeCache() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$results_object = new Results();

		// Put something in the cache for the query key that proves it came from cache.
		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";
		$key = $results_object->the_results_key( null, $query );
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$results_object->save( $key, $expected );

		// Query that we got from cache
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($expected['data'], $response['data']);

		// Clear the cache
		$this->assertEquals( $results_object->purge_all(), 1 );

		$real = [
			'data' => [
				'posts' => [
					'nodes' => []
				]
			]
		];
		$response = graphql([ 'query' => $query ]);
		$this->assertEquals($real['data'], $response['data']);
	}

	/**
	 * Set the global ttl setting.
	 * Make graphql request.
	 * Verifyy we see the results from cache.
	 * Verify we see transient expiration set.
	 */
	public function testExpirationTtlIsSetForCachedResults() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on', 'global_ttl' => '30' ] );

		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";

		// Thought, capture a before and after time around the graphql query. Add the ttl seconds to each and make sure the
		// transient timeout is between the two inclusively.
		$results_object = new Results();
		$key = $results_object->the_results_key( null, $query );
		$time_before = time();
		$response = graphql([ 'query' => $query ]);
		$time_after = time();

		$this->assertArrayHasKey( 'data', $response );
		$transient_timeout_option = get_option( '_transient_timeout_' . Query::GROUP_NAME . '_' . $key );
		$this->assertNotEmpty( $transient_timeout_option );

		$this->assertGreaterThanOrEqual( $time_before + 30, $transient_timeout_option );
		$this->assertLessThanOrEqual( $time_after + 30, $transient_timeout_option );
	}

	public function testPurgeAllCacheAction() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$results_object = new Results();

		// Put something in the cache for the query key that proves it came from cache.
		$query = "query GetPosts {
			posts {
				nodes {
					title
				}
			}
		}";
		$key = $results_object->the_results_key( null, $query );
		$expected = [
			'data' => [
				'__typename' => 'Foo Bar'
			]
		];
		$results_object->save( $key, $expected );

		$actual = $results_object->get( $key );
		$this->assertEquals( $expected, $actual );

		// Clear the cache
		do_action( 'wpgraphql_cache_purge_all' );

		// empty when pulled directly from cache
		$actual = $results_object->get( $key );
		$this->assertEmpty( $actual );
	}

}
