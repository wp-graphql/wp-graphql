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
		$this->created_post_ids    = $this->create_posts();

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
		for ( $i = 1; $i <= 20; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject( [
				'post_type'   => 'post',
				'post_date'   => $date,
				'post_status' => 'publish',
			] );
		}

		return $created_posts;

	}

	/**
	 * This runs a posts query looking for the last 10 posts before the 10th created item from the create_posts method
	 */
	public function testPostConnectionQueryWithBeforeCursor() {

		/**
		 * Get the array of created post IDs
		 */
		$created_posts = $this->created_post_ids;

		/**
		 * Get a cursor for the 10th post to use in the next query
		 */
		$before_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $created_posts[10] );

		/**
		 * Query 10 posts, starting at the 10th one from $created_posts
		 */
		$query = '
		{
		  posts(last:10, before: "' . $before_cursor . '") {
		    edges {
		      node {
		        id
		        title
		        postId
		        date
		      }
		    }
		  }
		}
		';

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Ensure we're getting posts back
		 */
		$edges = $actual['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Verify the node data
		 */
		$edge_count = 1;
		foreach ( $edges as $edge ) {

			// Ensure each edge has a node
			$this->assertArrayHasKey( 'node', $edge );

			// Ensure each node that was returned, matches the Id of the created post in the order that
			// the posts were created
			$this->assertEquals( $edge['node']['postId'], $created_posts[ $edge_count ] );

			$edge_count ++;
		}

	}

	/**
	 * This runs a posts query looking for the last 10 posts after the 20th created item from the create_posts method
	 */
	public function testPostConnectionQueryWithAfterCursor() {

		$created_posts = $this->created_post_ids;

		/**
		 * Get a cursor for the 10th post to use in the next query
		 */
		$after_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $created_posts[10] );

		/**
		 * Query 10 posts, starting at the 10th one from $created_posts
		 */
		$query = '
		{
		  posts(first:10, after: "' . $after_cursor . '") {
		    edges {
		      node {
		        id
		        title
		        postId
		        date
		      }
		    }
		  }
		}
		';

		/**
		 * Run the GraphQL query
		 */
		$actual = $actual = do_graphql_request( $query );

		/**
		 * Ensure we're getting posts back
		 */
		$edges = $actual['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Verify the node data
		 */
		$edge_count = 20;
		foreach ( $edges as $edge ) {

			// Ensure each edge has a node
			$this->assertArrayHasKey( 'node', $edge );

			// Ensure each node that was returned, matches the Id of the created post in the order that
			// the posts were created
			$this->assertEquals( $edge['node']['postId'], $created_posts[ $edge_count ] );

			$edge_count --;
		}

	}

	/**
	 * Tests a posts query, ensuring the 10 most recent posts come back
	 */
	public function testPostConnectionQuery() {

		$created_posts = $this->created_post_ids;

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		{
		  posts {
		    edges {
		      node {
		        id
		        title
		        postId
		        date
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * Ensure we're getting posts back
		 */
		$edges = $actual['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Verify the node data
		 */
		$edge_count = 11;
		foreach ( $edges as $edge ) {

			// Ensure each edge has a node
			$this->assertArrayHasKey( 'node', $edge );


			// Ensure each node that was returned, matches the Id of the created post in the order that
			// the posts were created
			$this->assertEquals( $edge['node']['postId'], $created_posts[ $edge_count ] );

			$edge_count ++;
		}

	}

}