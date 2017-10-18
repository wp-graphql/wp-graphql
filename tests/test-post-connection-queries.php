<?php

/**
 * WPGraphQL Test Post Object Queries
 * This tests post queries (singular and plural) checking to see if the available fields return the expected response
 *
 * @package WPGraphQL
 * @since   0.0.5
 */
class WP_GraphQL_Test_Post_Connection_Queries extends WP_UnitTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;

	/**
	 * This function is run before each method
	 *
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory->user->create( [
			'role' => 'administrator',
		] );
		$this->created_post_ids = $this->create_posts();

	}

	/**
	 * Runs after each method.
	 *
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Title',
			'post_type'    => 'post',
			'post_date'    => $this->current_date,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 *
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;

	}

	/**
	 * Creates several posts (with different timestamps) for use in cursor query tests
	 *
	 * @return array
	 */
	public function create_posts() {

		// Create 20 posts
		$created_posts = [];
		for ( $i = 1; $i <= 200; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject( [
				'post_type'   => 'post',
				'post_date'   => $date,
				'post_status' => 'publish',
				'post_title'  => $i,
			] );
		}

		return $created_posts;

	}

	public function postsQuery( $variables ) {

		$query = 'query postsQuery($first:Int $last:Int $after:String $before:String $where:queryArgs){
			posts( first:$first last:$last after:$after before:$before where:$where ) {
				pageInfo {
					hasNextPage
					hasPreviousPage
					startCursor
					endCursor
				}
				edges {
					cursor
					node {
						id
						postId
						title
						date
					}
				}
				nodes {
				  id
				  postId
				}
			}
		}';

		return do_graphql_request( $query, 'postsQuery', $variables );

	}

	public function testfirstPost() {

		/**
		 * Here we're querying the first post in our dataset
		 */
		$variables = [
			'first' => 1,
		];
		$results   = $this->postsQuery( $variables );

		/**
		 * Let's query the first post in our data set so we can test against it
		 */
		$first_post      = new WP_Query( [
			'posts_per_page' => 1,
		] );
		$first_post_id   = $first_post->posts[0]->ID;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $first_post_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $first_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );
		$this->assertEquals( $first_post_id, $results['data']['posts']['nodes'][0]['postId'] );

		$this->forwardPagination( $expected_cursor );

	}

	public function testLastPost() {
		/**
		 * Here we're trying to query the last post in our dataset
		 */
		$variables = [
			'last' => 1,
		];
		$results   = $this->postsQuery( $variables );

		/**
		 * Let's query the last post in our data set so we can test against it
		 */
		$last_post    = new WP_Query( [
			'posts_per_page' => 1,
			'order'          => 'ASC',
		] );
		$last_post_id = $last_post->posts[0]->ID;

		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $last_post_id );

		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $last_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );

		$this->backwardPagination( $expected_cursor );

	}

	public function forwardPagination( $cursor ) {

		$variables = [
			'first' => 1,
			'after' => $cursor,
		];

		$results = $this->postsQuery( $variables );

		$second_post     = new WP_Query( [
			'posts_per_page' => 1,
			'paged'          => 2,
		] );
		$second_post_id  = $second_post->posts[0]->ID;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $second_post_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $second_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );
	}

	public function backwardPagination( $cursor ) {

		$variables = [
			'last'   => 1,
			'before' => $cursor,
		];

		$results = $this->postsQuery( $variables );

		$second_to_last_post    = new WP_Query( [
			'posts_per_page' => 1,
			'paged'          => 2,
			'order'          => 'ASC',
		] );
		$second_to_last_post_id = $second_to_last_post->posts[0]->ID;
		$expected_cursor        = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $second_to_last_post_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $second_to_last_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );

	}

	public function testMaxQueryAmount() {
		$variables = [
			'first' => 150,
		];
		$results   = $this->postsQuery( $variables );
		$this->assertNotEmpty( $results );

		/**
		 * The max that can be queried by default is 100 items
		 */
		$this->assertCount( 100, $results['data']['posts']['edges'] );
		$this->assertTrue( $results['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the filter to make sure it's capping the results properly
		 */
		add_filter( 'graphql_connection_max_query_amount', function() {
			return 20;
		} );

		$variables = [
			'first' => 150,
		];
		$results   = $this->postsQuery( $variables );

		add_filter( 'graphql_connection_max_query_amount', function() {
			return 100;
		} );

		$this->assertCount( 20, $results['data']['posts']['edges'] );
		$this->assertTrue( $results['data']['posts']['pageInfo']['hasNextPage'] );
	}

	public function testPageWithChildren() {

		$parent_id = $this->factory->post->create([
			'post_type' => 'page'
		]);

		$child_id = $this->factory->post->create([
			'post_type' => 'page',
			'post_parent' => $parent_id
		]);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $parent_id );
		$global_child_id = \GraphQLRelay\Relay::toGlobalId( 'page', $child_id );

		$query = '
		{
			page( id: "' . $global_id . '" ) {
				id
				pageId
				childPages {
					edges {
						node {
							id
							pageId
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * Make sure the query didn't return any errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );

		$parent = $actual['data']['page'];
		$child = $parent['childPages']['edges'][0]['node'];

		/**
		 * Make sure the child and parent data matches what we expect
		 */
		$this->assertEquals( $global_id, $parent['id'] );
		$this->assertEquals( $parent_id, $parent['pageId'] );
		$this->assertEquals( $global_child_id, $child['id'] );
		$this->assertEquals( $child_id, $child['pageId'] );


	}

}
