<?php

class PostObjectSearchTest extends \Codeception\TestCase\WPTestCase {
	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;
	public $query;
	public $app_context;
	public $subscriber;

	public function setUp(): void {

		parent::setUp();

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'posts', array( 'post_type' => 'post' ) );

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->subscriber       = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$this->created_post_ids = $this->create_posts();

		$this->app_context = new \WPGraphQL\AppContext();


		$this->query = '
		query GET_POSTS($first: Int, $last: Int, $after: String, $before: String $where:RootQueryToPostConnectionWhereArgs) {
		  posts(last: $last, before: $before, first: $first, after: $after, where:$where) {
		    pageInfo {
		      hasPreviousPage
		      hasNextPage
		      startCursor
		      endCursor
		    }
		    edges {
		      cursor
		      node {
		        id
		        postId
		        date
		        title
		      }
		    }
		  }
		}
		';

	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'posts', array( 'post_type' => 'post' ) );
		parent::tearDown();
	}

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'   => $this->admin,
			'post_content'  => 'test',
			'post_excerpt'  => 'Test excerpt',
			'post_status'   => 'publish',
			'post_title'    => 'Test Title',
			'post_type'     => 'post',
			'post_date'     => $this->current_date,
			'has_password'  => false,
			'post_password' => null,
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
	 * @param  int $count Number of posts to create.
	 * @return array
	 */
	public function create_posts( $count = 20 ) {

		// Create posts
		$created_posts = [];
		for ( $i = 1; $i <= $count; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject(
				[
					'post_type'   => 'post',
					'post_date'   => $date,
					'post_status' => 'publish',
					'post_title'  => 'search | ' . $i,
				]
			);
		}

		return $created_posts;

	}

	/**
	 * @throws Exception
	 */
	public function testSearchPostsForwardPagination() {

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'first' => 2,
					'where' => [
						'search' => 'test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->created_post_ids[20], $actual['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $this->created_post_ids[19], $actual['data']['posts']['edges'][1]['node']['postId'] );
		$this->assertEquals( false, $actual['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasNextPage'] );

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'first' => 2,
					'after' => $actual['data']['posts']['pageInfo']['endCursor'],
					'where' => [
						'search' => 'test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->created_post_ids[18], $actual['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $this->created_post_ids[17], $actual['data']['posts']['edges'][1]['node']['postId'] );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasPreviousPage'] );

	}

	/**
	 * Tests the backward pagination of connections
	 *
	 * @throws Exception
	 */
	public function testSearchPostsBackwardPagination() {

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'last'  => 2,
					'where' => [
						'search' => 'test',
					],
				],
			]
		);

		codecept_debug( $this->created_post_ids );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $this->created_post_ids[2], $actual['data']['posts']['edges'][1]['node']['postId'] );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['posts']['pageInfo']['hasNextPage'] );

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'last'   => 2,
					'before' => $actual['data']['posts']['pageInfo']['startCursor'],
					'where'  => [
						'search' => 'test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->created_post_ids[2], $actual['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $this->created_post_ids[3], $actual['data']['posts']['edges'][1]['node']['postId'] );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasNextPage'] );

	}

}
