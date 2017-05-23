<?php
/**
 * WPGraphQL Test Comment Object Queries
 * This tests comment queries (singular and plural) checking to see if the available fields return the expected response
 *
 * @package WPGraphQL
 * @since   0.0.5
 */

class WP_GraphQL_Test_Comment_Connection_Queries extends WP_UnitTestCase {
	public $admin;

	/**
	 * This function is run before each method
	 *
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory->user->create( [
			'role' => 'admin',
		] );
		$this->created_comment_ids = $this->create_comments();
	}

	/**
	 * Runs after each method.
	 *
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function createCommentObject( $args = [] ) {
		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'comment_author'   => $this->admin,
			'comment_content'  => 'Test comment content',
			'comment_approved' => 1,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$comment_id = $this->factory->comment->create( $args );

		/**
		 * Return the $id of the comment_object that was created
		 */
		return $comment_id;
	}

	/**
	 * Creates several comments (with different timestamps) for use in cursor query tests
	 *
	 * @return array
	 */
	public function create_comments() {
		// Create 20 comments
		$created_comments = [];
		for ( $i = 1; $i <= 20; $i ++ ) {
			$created_comments[ $i ] = $this->createCommentObject( [ 'comment_content' => $i ] );
		}

		return $created_comments;
	}

	/**
	 * This runs a comments query looking for the last 10 comments before the 10th created item from the create_comments method
	 */
	public function testCommentConnectionQueryWithBeforeCursor() {
		/**
		 * Get the array of created comment IDs
		 */
		$created_comments = $this->created_comment_ids;

		/**
		 * Get a cursor for the 10th comment to use in the next query
		 */
		$before_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $created_comments[10] );

		/**
		 * Query 10 comments, starting at the 10th one from $created_comments
		 */
		$query = '
		{
		  comments(last:10, before: "' . $before_cursor . '") {
		    edges {
		      node {
		        id
		        content
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
		 * Ensure we're getting comments back
		 */
		$edges = $actual['data']['comments']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Verify the node data
		 */
		$edge_count = 9;
		foreach ( $edges as $edge ) {

			// Ensure each edge has a node
			$this->assertArrayHasKey( 'node', $edge );

			// Ensure each node that was returned, matches the Id of the created comment in the order that
			// the comments were created
			$this->assertEquals( $edge['node']['content'], ( string ) $edge_count );

			$edge_count --;
		}
	}

	/**
	 * This runs a comments query looking for the last 10 comments after the 20th created item from the create_comments method
	 */
	public function testCommentConnectionQueryWithAfterCursor() {
		$created_comments = $this->created_comment_ids;

		/**
		 * Get a cursor for the 10th comment to use in the next query
		 */
		$after_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $created_comments[10] );

		/**
		 * Query 10 comments, starting at the 10th one from $created_comments
		 */
		$query = '
		{
		  comments(first:10, after: "' . $after_cursor . '") {
		    edges {
		      node {
		        id
		        content
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
		 * Ensure we're getting comments back
		 */
		$edges = $actual['data']['comments']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Verify the node data
		 */
		$edge_count = 11;
		foreach ( $edges as $edge ) {

			// Ensure each edge has a node
			$this->assertArrayHasKey( 'node', $edge );

			// Ensure each node that was returned, matches the Id of the created comment in the order that
			// the comments were created
			$this->assertEquals( $edge['node']['content'], ( string ) $edge_count );

			$edge_count ++;
		}
	}

	/**
	 * Tests a comments query, ensuring the 10 most recent comments come back
	 */
	public function testCommentConnectionQuery() {

		$created_comments = $this->created_comment_ids;

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		{
		  comments {
		    edges {
		      node {
		        id
		        content
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * Ensure we're getting comments back
		 */
		$edges = $actual['data']['comments']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Verify the node data
		 */
		$edge_count = 20;
		foreach ( $edges as $edge ) {

			// Ensure each edge has a node
			$this->assertArrayHasKey( 'node', $edge );


			// Ensure each node that was returned, matches the Id of the created comment in the order that
			// the comments were created
			$this->assertEquals( $edge['node']['content'], ( string ) $edge_count );

			$edge_count --;
		}
	}
}
