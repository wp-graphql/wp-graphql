<?php

class UserObjectPaginationTest extends \Codeception\TestCase\WPTestCase {
	
	public $current_time;
	public $current_date;
	public $created_user_ids;
	public $query;
	public $count;
	public $db;

	public function setUp() {

		parent::setUp();
		
		$this->delete_users();

		// Set admin as current user to authorize 'users' queries
		wp_set_current_user( 1 );

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		// Number of users to create. More created users will slow down the test. 
		$this->count				= 10;
		$this->create_users();

		$this->query = '
		query GET_POSTS($first: Int, $last: Int, $after: String, $before: String) {
		  users(last: $last, before: $before, first: $first, after: $after) {
				pageInfo {
					startCursor
					endCursor
				}
		    nodes {
		      userId
				}
		  }
		}
		';

	}

	public function tearDown() {
		$this->delete_users();
		parent::tearDown();
	}

	public function createUserObject( $args = [] ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'role' 		 => 'subscriber',
			'user_url' => 'http://www.test.test',
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$user_id = $this->factory->user->create( $args );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $user_id;

	}

	/**
	 * Creates several users (with different emails) for use in cursor query tests
	 */
	public function create_users() {

		// Initialize with the default user
		$created_user_ids = [ 1 ];
		// Create a few more users
		for ( $i = 1; $i < $this->count; $i ++ ) {
			$created_user_ids[ $i ]	= $this->createUserObject( [
					'user_email' => 'test_user_' . $i . '@test.com',
			] );
		}

		$this->created_user_ids = array_reverse( $created_user_ids );

	}

	/**
	 * Deletes all users that were created using create_users()
	 */
	public function delete_users() {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'users', array( 'user_url' => 'http://www.test.test' ) );
		$this->created_user_ids = [ 1 ];
	}

	/**
	 * @throws Exception
	 */
	public function testUsersForwardPagination() {

		codecept_debug($this->created_user_ids);

		$paged_count = ceil( $this->count / 2 );
		$actual = graphql( [
			'query' 		=> $this->query,
			'variables' => [
				'first' => $paged_count,
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Compare actual results to ground truth
		for ( $i = 0; $i < $paged_count; $i ++ ) {
			$this->assertEquals( $this->created_user_ids[$i], $actual['data']['users']['nodes'][$i]['userId'] );
		}

		$actual = graphql( [
			'query' 		=> $this->query,
			'variables' => [
				'first' => $paged_count,
				'after' => $actual['data']['users']['pageInfo']['endCursor'],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		for ( $i = 0; $i < $paged_count; $i ++ ) {
			$this->assertEquals( $this->created_user_ids[$paged_count + $i], $actual['data']['users']['nodes'][$i]['userId'] );
		}
	}

	/**
	 * Tests the backward pagination of connections
	 * @throws Exception
	 */
	public function testUsersBackwardPagination() {

		codecept_debug($this->created_user_ids);

		$paged_count = ceil( $this->count / 2 );
		$actual = graphql( [
			'query' 		=> $this->query,
			'variables' => [
				'last' => $paged_count,
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Compare actual results to ground truth
		for ( $i = 0; $i < $paged_count; $i ++ ) {
			$this->assertEquals( $this->created_user_ids[$paged_count + $i], $actual['data']['users']['nodes'][$i]['userId'] );
		}

		$actual = graphql( [
			'query' 		=> $this->query,
			'variables' => [
				'last' => $paged_count,
				'before' => $actual['data']['users']['pageInfo']['startCursor'],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		for ( $i = 0; $i < $paged_count; $i ++ ) {
			$this->assertEquals( $this->created_user_ids[$i], $actual['data']['users']['nodes'][$i]['userId'] );
		}
	}

}