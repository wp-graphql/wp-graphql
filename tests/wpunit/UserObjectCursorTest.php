<?php

class UserObjectCursorTest extends \Codeception\TestCase\WPTestCase {

	public $current_time;
	public $current_date;
	public $created_user_ids;
	public $query;
	public $count;
	public $db;
	public $admin;

	public function setUp(): void {

		parent::setUp();

		$this->delete_users();

		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);

		// Set admin as current user to authorize 'users' queries
		wp_set_current_user( $this->admin );

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		// Number of users to create. More created users will slow down the test.
		$this->count = 10;
		$this->create_users();

		$this->query = '
		query GET_USERS($first: Int, $last: Int, $after: String, $before: String, $where: RootQueryToUserConnectionWhereArgs) {
			users(last: $last, before: $before, first: $first, after: $after, where: $where) {
				pageInfo {
					startCursor
					endCursor
					hasNextPage
					hasPreviousPage
				}
			    nodes {
			      	userId
				}
		  	}
		}
		';

	}

	public function tearDown(): void {
		$this->delete_users();
		parent::tearDown();
	}

	public function createUserObject( $args = [] ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'role'     => 'subscriber',
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
		 * Return the $id of the user that was created
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
			$created_user_ids[ $i ] = $this->createUserObject(
				[
					'user_email' => 'test_user_' . $i . '@test.com',
					'role'       => 'administrator'
				]
			);
		}

		$this->created_user_ids = array_reverse( $created_user_ids );

	}

	/**
	 * Deletes all users that were created using create_users()
	 */
	public function delete_users() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}users WHERE ID <> %d",
				array( 1 )
			)
		);
		$this->created_user_ids = [ 1 ];
	}

	/**
	 * @throws Exception
	 */
	public function testUsersForwardPagination() {

		$paged_count = ceil( $this->count / 2 );
		$expected    = array_slice( $this->created_user_ids, 0, $paged_count );

		codecept_debug( $expected );

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'first' => $paged_count,
					'where' => [
						'search' => 'http://www.test.test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasNextPage'] );

		// Compare actual results to ground truth
		for ( $i = 0; $i < $paged_count; $i ++ ) {
			$this->assertEquals( $expected[ $i ], $actual['data']['users']['nodes'][ $i ]['userId'] );
		}

		$expected = array_slice( $this->created_user_ids, $paged_count, $paged_count );

		codecept_debug( $expected );

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'first' => $paged_count,
					'after' => $actual['data']['users']['pageInfo']['endCursor'],
					'where' => [
						'search' => 'http://www.test.test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasNextPage'] );

		for ( $i = 0; $i < $paged_count - 1; $i ++ ) {
			$this->assertEquals( $expected[ $i ], $actual['data']['users']['nodes'][ $i ]['userId'] );
		}
	}

	/**
	 * Tests the backward pagination of connections
	 *
	 * @throws Exception
	 */
	public function testUsersBackwardPagination() {

		$paged_count = ceil( $this->count / 2 );
		$expected    = array_slice( $this->created_user_ids, $paged_count - 1, $paged_count );
		$expected = array_reverse( $expected );
		codecept_debug( $expected );

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'last'  => $paged_count,
					'where' => [
						'search' => 'http://www.test.test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasNextPage'] );

		// Compare actual results to ground truth
		for ( $i = 0; $i < $paged_count; $i ++ ) {
			$this->assertEquals( $expected[ $i ], $actual['data']['users']['nodes'][ $i ]['userId'] );
		}

		$paged_count = ceil( $this->count / 2 );
		$expected    = array_slice( $this->created_user_ids, 0, $paged_count - 1 );

		codecept_debug( $expected );

		$actual = graphql(
			[
				'query'     => $this->query,
				'variables' => [
					'last'   => $paged_count,
					'before' => $actual['data']['users']['pageInfo']['startCursor'],
					'where'  => [
						'search' => 'http://www.test.test',
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasNextPage'] );

	}

	private function formatNumber( $num ) {
		return sprintf( '%08d', $num );
	}

	private function numberToMysqlDate( $num ) {
		return sprintf( '2019-03-%02d', $num );
	}

	private function deleteByMetaKey( $key, $value ) {
		$args = array(
			'meta_query' => array(
				array(
					'key'     => $key,
					'value'   => $value,
					'compare' => '=',
				),
			),
		);

		 $query = new WP_User_Query( $args );

		foreach ( $query->results as $user ) {
			wp_delete_user( $user->ID, true );
		}
	}

	/**
	 * Assert given 'orderby' field by comparing the GraphQL query against a plain WP_User_Query
	 *
	 * @throws Exception
	 */
	public function assertQueryInCursor( $graphql_args = [], $wp_user_query_args, $order_by_meta_field = false ) {

		$graphql_args = ! empty( $graphql_args ) ? $graphql_args : [];


		// Meta field orderby is not supported in schema and needs to be passed in through hook
		if ( $order_by_meta_field ) {
			add_filter(
				'graphql_map_input_fields_to_wp_user_query',
				function( $query_args ) use ( $wp_user_query_args ) {
					return array_merge( $query_args, $wp_user_query_args );
				},
				10,
				1
			);
		}

		$users_per_page = ceil( $this->count / 2 );

		codecept_debug( $graphql_args );


		$query = '
		query getUsers($cursor: String $first: Int $where:RootQueryToUserConnectionWhereArgs) {
			users(after: $cursor, first: $first, where: $where) {
				pageInfo {
					endCursor
				}
				edges {
					node {
						name
						userId
					}
				}
			}
		  }
		';

		codecept_debug( $query );

		$first = do_graphql_request( $query, 'getUsers', array_merge( $graphql_args, [
			'first' => $users_per_page,
			'cursor' => null
		]) );

		codecept_debug( $first );

		$this->assertArrayNotHasKey( 'errors', $first, print_r( $first, true ) );

		$first_page_actual = array_map(
			function( $edge ) {
				return $edge['node']['userId'];
			},
			$first['data']['users']['edges']
		);

		$cursor = $first['data']['users']['pageInfo']['endCursor'];
		$second = do_graphql_request( $query, 'getUsers', array_merge( $graphql_args, [
			'first' => $users_per_page,
			'cursor' => $cursor
		]) );

		codecept_debug( $second );

		$this->assertArrayNotHasKey( 'errors', $second, print_r( $second, true ) );

		$second_page_actual = array_map(
			function( $edge ) {
				return $edge['node']['userId'];
			},
			$second['data']['users']['edges']
		);

		// Make corresponding WP_User_Query
		WPGraphQL::set_is_graphql_request( true );
		$first_page = new WP_User_Query(
			array_merge(
				$wp_user_query_args,
				[
					'number' => $users_per_page,
					'paged'  => 1,
				]
			)
		);

		$second_page = new WP_User_Query(
			array_merge(
				$wp_user_query_args,
				[
					'number' => $users_per_page,
					'paged'  => 2,
				]
			)
		);
		WPGraphQL::set_is_graphql_request( false );

	}

	/**
	 * Test default order
	 */
	public function testDefaultUserOrdering() {
		$this->assertQueryInCursor( null, [] );
	}

	/**
	 * Simple login__in ordering test
	 */
	public function testUserOrderingByLoginInDefault() {
		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'LOGIN_IN',
				],
			],
			[
				'orderby' => 'login__in',
			]
		);
	}

	/**
	 * Simple login__in ordering test by ASC
	 */
	public function testUserOrderingByLoginInASC() {
		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'LOGIN_IN',
					'order' => 'ASC',
				],
			],
			[
				'orderby' => 'login__in',
				'order'   => 'ASC',
			]
		);
	}

	/**
	 * Simple login__in ordering test by DESC
	 */
	public function testUserOrderingByLoginInDESC() {
		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'LOGIN_IN',
					'order' => 'DESC',
				],
			],
			[
				'orderby' => 'login__in',
				'order'   => 'DESC',
			]
		);
	}

	/**
	 * Simple nice name ordering test by ASC
	 */
	public function testUserOrderingByNiceNameASC() {
		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'NICE_NAME',
					'order' => 'ASC',
				],
			],
			[
				'orderby' => 'nicename',
				'order'   => 'ASC',
			]
		);
	}

	/**
	 * Simple nice name ordering test by DESC
	 */
	public function testUserOrderingByNiceNameDESC() {
		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'NICE_NAME',
					'order' => 'DESC',
				],
			],
			[
				'orderby' => 'nicename',
				'order'   => 'DESC',
			]
		);
	}

	public function testUserOrderingByDuplicateUserNames() {
		foreach ( $this->created_user_ids as $index => $user_id ) {
			wp_update_user(
				[
					'ID'            => $user_id,
					'user_nicename' => 'dupname',

				]
			);
		}

		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'NICE_NAME',
					'order' => 'DESC',
				],
			],
			[
				'orderby' => 'nicename',
				'order'   => 'DESC',
			]
		);
	}

	public function testUserOrderingByMetaString() {

		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 9 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_user_meta( $this->created_user_ids[9], 'test_meta', $this->formatNumber( 6 ) );

		// Must use dummy where args here to force
		// graphql_map_input_fields_to_wp_query to be executed
		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'  => [ 'meta_value' => 'ASC' ],
				'meta_key' => 'test_meta',
			],
			true
		);

	}

	public function testUserOrderingByMetaDate() {

		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		// Move number 9 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->numberToMysqlDate( 6 ) );
		update_user_meta( $this->created_user_ids[9], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'ASC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'DATE',
			],
			true
		);
	}

	public function testUserOrderingByMetaDateDESC() {

		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		$this->deleteByMetaKey( 'test_meta', $this->numberToMysqlDate( 14 ) );
		update_user_meta( $this->created_user_ids[2], 'test_meta', $this->numberToMysqlDate( 14 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'DESC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'DATE',
			],
			true
		);
	}

	public function testUserOrderingByMetaNumber() {

		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $index );
		}

		// Move number 9 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 6 );
		update_user_meta( $this->created_user_ids[9], 'test_meta', 6 );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'ASC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'UNSIGNED',
			],
			true
		);
	}

	public function testUserOrderingByMetaNumberDESC() {

		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $index );
		}

		$this->deleteByMetaKey( 'test_meta', 4 );
		update_user_meta( $this->created_user_ids[2], 'test_meta', 4 );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'DESC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'UNSIGNED',
			],
			true
		);
	}

	public function testUserOrderingWithMetaFiltering() {
		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $index );
		}

		// Move number 2 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 7 );
		update_user_meta( $this->created_user_ids[2], 'test_meta', 7 );

		wp_set_current_user( $this->admin );
		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'    => [ 'meta_value' => 'ASC' ],
				'meta_key'   => 'test_meta',
				'meta_type'  => 'UNSIGNED',
				'meta_query' => [
					[
						'key'     => 'test_meta',
						'compare' => '>',
						'value'   => 10,
						'type'    => 'UNSIGNED',
					],
				],
			],
			true
		);

	}

	public function testUserOrderingByMetaQueryClause() {

		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 9 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_user_meta( $this->created_user_ids[9], 'test_meta', $this->formatNumber( 6 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'    => [ 'test_clause' => 'ASC' ],
				'meta_query' => [
					'test_clause' => [
						'key'     => 'test_meta',
						'compare' => 'EXISTS',
					],
				],
			],
			true
		);
	}

	public function testUserOrderingByMetaQueryClauseString() {

		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 9 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_user_meta( $this->created_user_ids[9], 'test_meta', $this->formatNumber( 6 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'    => 'test_clause',
				'order'      => 'ASC',
				'meta_query' => [
					'test_clause' => [
						'key'     => 'test_meta',
						'compare' => 'EXISTS',
					],
				],
			],
			true
		);

	}

	/**
	 * When ordering users with the same meta value the returned order can vary if
	 * there isn't a second ordering field. This test does not fail every time
	 * so it tries to execute the assertion multiple times to make happen more often
	 */
	public function testUserOrderingStability() {

		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		update_user_meta( $this->created_user_ids[9], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'ASC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'DATE',
			],
			true
		);

		update_user_meta( $this->created_user_ids[7], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'ASC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'DATE',
			],
			true
		);

		update_user_meta( $this->created_user_ids[8], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'   => [ 'meta_value' => 'ASC' ],
				'meta_key'  => 'test_meta',
				'meta_type' => 'DATE',
			],
			true
		);

	}

	/**
	 * Test support for meta_value_num
	 */
	public function testUserOrderingByMetaValueNum() {

		// Add user meta to created users
		foreach ( $this->created_user_ids as $index => $user_id ) {
			update_user_meta( $user_id, 'test_meta', $index );
		}

		// Move number 8 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 6 );
		update_user_meta( $this->created_user_ids[8], 'test_meta', 6 );

		$this->deleteByMetaKey( 'test_meta', 6 );
		update_user_meta( $this->created_user_ids[2], 'test_meta', 6 );

		$this->assertQueryInCursor(
			[
				'roleIn' => [
					'ADMINISTRATOR',
					'SUBSCRIBER',
				],
			],
			[
				'orderby'  => 'meta_value_num',
				'order'    => 'ASC',
				'meta_key' => 'test_meta',
			],
			true
		);
	}

	/**
	 * Test orderby nicename__in should work even with order parameter added by mistake
	 *
	 * @throws Exception
	 */
	public function testOrderbyNiceNameIn() {
		$this->assertQueryInCursor(
			[
				'orderby' => [
					'field' => 'NICE_NAME_IN',
					'order' => 'DESC',
				],
			],
			[
				'orderby' => 'nicename__in',
				'order'   => 'DESC',
			]
		);
	}

}
