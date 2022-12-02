<?php

use GraphQLRelay\Relay;
use WPGraphQL\Type\WPEnumType;

class MenuItemConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $menu_location;
	public $created_menu_items;


	public function setUp(): void {
		$this->clearSchema();
		parent::setUp();

		$this->menu_location = 'my-menu-location';

		add_theme_support( 'nav_menu_locations' );
		register_nav_menu( $this->menu_location, 'My Menu' );
		set_theme_mod( 'nav_menu_locations', [ $this->menu_location => 0 ] );

		$this->admin = $this->factory()->user->create( [
			'role'       => 'administrator',
			'user_email' => 'test@test.com',
		] );

		$this->created_menu_items = $this->create_menu_items( 'my-menu-items-test', $this->menu_location, 6 );

	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		parent::tearDown();
	}

	private function createMenuItem( $menu_id, $options ) {
		return wp_update_nav_menu_item( $menu_id, 0, $options );
	}

	private function create_menu_items( $slug, $location, $count ) {
		$menu_id       = wp_create_nav_menu( $slug );
		$menu_item_ids = [];
		$post_ids      = [];

		// Create some Post menu items.
		for ( $x = 1; $x <= $count; $x ++ ) {
			$post_id    = $this->factory()->post->create( [
				'post_status' => 'publish',
			] );
			$post_ids[] = $post_id;

			$menu_item_ids[] = $this->createMenuItem(
				$menu_id,
				[
					'menu-item-title'     => "Menu item {$x}",
					'menu-item-object'    => 'post',
					'menu-item-object-id' => $post_id,
					'menu-item-status'    => 'publish',
					'menu-item-type'      => 'post_type',
				]
			);
		}

		// Assign menu to location.
		set_theme_mod( 'nav_menu_locations', [ $location => $menu_id ] );

		// Make sure menu items were created.
		$this->assertEquals( $count, count( $menu_item_ids ) );
		$this->assertEquals( $count, count( $post_ids ) );

		return [
			'menu_id'       => $menu_id,
			'menu_item_ids' => $menu_item_ids,
			'post_ids'      => $post_ids,
		];
	}

	public function create_nested_menu( $child_count, $location ) {
		$count   = 10;
		$created = $this->create_menu_items( 'my-test-menu-with-child-items', $location, $count );

		// Add some child items to the fourth menu item.
		for ( $x = 1; $x <= $child_count; $x ++ ) {
			$options = [
				'menu-item-title'     => "Child menu item {$x}",
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $this->factory()->post->create(),
				'menu-item-parent-id' => $created['menu_item_ids'][3],
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			];

			$this->createMenuItem( $created['menu_id'], $options );
		}

		return $created;

	}

	/**
	 * Some common assertions repeated for each test.
	 *
	 * @param  array $created_menu_ids Created menu items.
	 * @param  array $created_post_ids Created connected posts.
	 * @param  array $query_results    Query results.
	 *
	 * @return void
	 */
	private function compareResults( $created_menu_ids, $created_post_ids, $query_result ) {
		$edges = $query_result['data']['menuItems']['edges'];

		// The returned menu items have the expected IDs in the expected order.
		$this->assertEquals(
			$created_menu_ids,
			array_map( function ( $menu_item ) {
				return $menu_item['node']['databaseId'];
			}, $edges )
		);

		// The connected posts have the expected IDs in the expected order.
		$this->assertEquals(
			$created_post_ids,
			array_map( function ( $menu_item ) {
				return $menu_item['node']['connectedObject']['databaseId'];
			}, $edges )
		);
	}

	public function getQuery() {
		return '
			query menuItemsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToMenuItemConnectionWhereArgs ){
				menuItems( first:$first last:$last after:$after before:$before where:$where ) {
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
							databaseId
							parentDatabaseId
							parentId
							locations
							connectedObject {
								... on Post {
									databaseId
									status
								}
							}
							connectedNode {
								node {
									...on Post {
											databaseId
											status
									}
								}
							}
							childItems {
								edges {
									node {
										databaseId
										connectedObject {
											... on Post {
												databaseId
											}
										}
									}
								}
							}
						}
					}
					nodes {
						id
						databaseId
						order
					}
				}
			}
		';
	}

	public function testForwardPagination() {
		$query    = $this->getQuery();
		$wp_query = wp_get_nav_menu_items( 'my-menu-items-test' );

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
		];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 0, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['menuItems']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['after'] = '';
		$expected           = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['menuItems']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 2, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['menuItems']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 4, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasPreviousPage'] );

		$this->assertEquals( false, $actual['data']['menuItems']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = [
			'last' => 2,
		];
		$expected  = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $actual );
	}

	public function testBackwardPagination() {
		$query    = $this->getQuery();
		$wp_query = wp_get_nav_menu_items( 'my-menu-items-test' );

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'last' => 2,
		];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 0, 2, false );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['menuItems']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['before'] = '';
		$expected            = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['menuItems']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 2, 2, false );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['menuItems']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 4, 2, false );
		$expected = array_reverse( $expected );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['menuItems']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menuItems']['pageInfo']['hasNextPage'] );

		/**
		 * Test the first two results are equal to `first:2`.
		 */
		$variables = [
			'first' => 2,
		];
		$expected  = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );
	}

	public function testQueryWithFirstAndLast() {
		$query = $this->getQuery();

		$variables = [
			'first' => 5,
		];

		/**
		 * Test `first`.
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$after_cursor  = $actual['data']['menuItems']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['menuItems']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['menuItems']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['menuItems']['nodes'][0] );

		/**
		 * Test `last`.
		 */
		$variables['last'] = 5;

		// Using first and last should throw an error.
		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		unset( $variables['first'] );

		// Get 5 items, but between the bounds of a before and after cursor.
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['menuItems']['nodes'][0] );
	}

	public function testIdWhereArgs() {
		$menu_item_id = intval( $this->created_menu_items['menu_item_ids'][2] );
		$post_id      = intval( $this->created_menu_items['post_ids'][2] );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'id' => $menu_item_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );

		$this->assertEquals( 1, count( $actual['data']['menuItems']['edges'] ) );
		$this->compareResults( [ $menu_item_id ], [ $post_id ], $actual );
	}

	public function testLocationWhereArgs() {
		$menu_item_id = intval( $this->created_menu_items['menu_item_ids'][2] );
		$post_id      = intval( $this->created_menu_items['post_ids'][2] );

		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_menu_items( 'menu-for-locaion-test', $menu_location, 1 );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'location' => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// The returned menu items have the expected number.
		$this->assertEquals( 1, count( $actual['data']['menuItems']['edges'] ) );

		// Perform some common assertions.
		$this->compareResults( $created['menu_item_ids'], $created['post_ids'], $actual );
	}

	public function testLocationWhereArgsWithChildItemsFlat() {
		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		// The nesting is added to the fourth item
		$parent_database_id = $created['menu_item_ids'][3];

		$query = $this->getQuery();

		$variables = [
			'first' => 99,
			'where' => [
				'location' => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 13, count( $actual['data']['menuItems']['edges'] ) );

		$child_items_via_database_id = [];
		$parent_id                   = null;

		foreach ( $actual['data']['menuItems']['edges'] as $edge ) {
			if ( $edge['node']['databaseId'] === $parent_database_id ) {
				$parent_id = $edge['node']['id'];
			}

			// Child items have the correct parenDatabaseId
			if ( $edge['node']['parentDatabaseId'] === $parent_database_id ) {
				$child_items_via_database_id[] = $edge['node'];
			}
		}

		$this->assertNotNull( $parent_id );
		$this->assertEquals( 3, count( $child_items_via_database_id ) );

		$child_items_via_relay_id = [];

		foreach ( $actual['data']['menuItems']['edges'] as $edge ) {
			// Child items have the correct relay parentId
			if ( $edge['node']['parentId'] === $parent_id ) {
				$child_items_via_relay_id[] = $edge['node'];
			}
		}

		$this->assertEquals( 3, count( $child_items_via_relay_id ) );

	}

	public function testParentIdWhereArgsNonExplicit() {
		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		$query = $this->getQuery();

		// Test non with global id
		$variables = [
			'where' => [
				'parentId' => '0',
				'location' => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// Perform some common assertions.
		$this->compareResults( $created['menu_item_ids'], $created['post_ids'], $actual );

		// The fourth menu item has the expected number of child items.
		$this->assertEquals( 3, count( $actual['data']['menuItems']['edges'][3]['node']['childItems']['edges'] ) );

		// Test with database id
		$variables['where']['parentId'] = 0;

		// Perform some common assertions.
		$this->compareResults( $created['menu_item_ids'], $created['post_ids'], $actual );

		// The fourth menu item has the expected number of child items.
		$this->assertEquals( 3, count( $actual['data']['menuItems']['edges'][3]['node']['childItems']['edges'] ) );
	}


	public function testParentIdWhereArgsExplicit() {
		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		$query = $this->getQuery();

		// The nesting is added to the fourth item
		$parent_id = $created['menu_item_ids'][3];

		// Test with database Id.
		$variables = [
			'where' => [
				'parentId' => $parent_id,
				'location'         => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 3, count( $actual['data']['menuItems']['edges'] ) );

		// The parentDatabaseId matches with the requested parent database id
		$this->assertEquals( $parent_id, $actual['data']['menuItems']['edges'][0]['node']['parentDatabaseId'] );

		// Test with global ID
		$variables['where']['parentId'] =  Relay::toGlobalId( 'post', $parent_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 3, count( $actual['data']['menuItems']['edges'] ) );

		// The parentDatabaseId matches with the requested parent database id
		$this->assertEquals( $parent_id, $actual['data']['menuItems']['edges'][0]['node']['parentDatabaseId'] );
	}

	public function testMenuItemsQueryWithExplicitParentId() {
		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		$query = $this->getQuery();

		// The nesting is added to the fourth item
		$parent_id = Relay::toGlobalId( 'post', $created['menu_item_ids'][3] );

		$variables = [
			'where' => [
				'parentId' => $parent_id,
				'location' => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// Perform some common assertions.
		$this->assertEquals( 3, count( $actual['data']['menuItems']['edges'] ) );
	}

	public function testDraftPostsVisibility() {

		wp_update_post(
			[
				'ID'          => $this->created_menu_items['post_ids'][0],
				'post_status' => 'draft',
			]
		);

		$query     = $this->getQuery();
		$variables = [
			'where' => [
				'location' => 'MY_MENU_LOCATION',
			],
		];

		// Test not visible for unauthenticated
		wp_set_current_user( 0 );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual, print_r( $actual, true ) );

		// Unauthenticated request still returns two _menu_ items
		$this->assertEquals( 6, count( $actual['data']['menuItems']['nodes'] ) );

		$this->assertEquals( null, $actual['data']['menuItems']['edges'][0]['node']['connectedObject'] );
		$this->assertEquals( null, $actual['data']['menuItems']['edges'][0]['node']['connectedNode'] );
		$this->assertEquals( 'publish', $actual['data']['menuItems']['edges'][1]['node']['connectedObject']['status'] );
		$this->assertEquals( 'publish', $actual['data']['menuItems']['edges'][1]['node']['connectedNode']['node']['status'] );

		// Test visible for admin.
		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 'draft', $actual['data']['menuItems']['edges'][0]['node']['connectedObject']['status'] );
		$this->assertEquals( 'draft', $actual['data']['menuItems']['edges'][0]['node']['connectedNode']['node']['status'] );

	}

	public function testMenuItemsOrder() {
		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		$query = $this->getQuery();

		// The nesting is added to the fourth item
		$parent_database_id = $created['menu_item_ids'][3];

		$variables = [
			'where' => [
				'parentDatabaseId' => $parent_database_id,
				'location'         => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// Assert that the `order` field actually exists and is an int
		$this->assertIsInt( 3, $actual['data']['menuItems']['nodes'][0]['order'] );

		$orders = array_map( function ( $node ) {
			return $node['order'];
		}, $actual['data']['menuItems']['nodes'] );

		// Make copy of the results that are sorted by the order
		$sorted_orders = $orders;
		asort( $sorted_orders );

		// Assert that the returned list was in the sorted order
		$this->assertEquals( $orders, $sorted_orders );

	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2409
	 *
	 * @return void
	 */
	public function testFilterMenuItemsByLocationDoesntBreakWhenTaxonomyNamedLocationExists() {
		// register a "location" taxonomy
		register_taxonomy( 'location', 'post', [
			'show_ui'             => true,
			'label'               => 'Location',
			'show_in_graphql'     => true,
			'graphql_single_name' => 'Location',
			'graphql_plural_name' => 'Locations',
		]);
		WPGraphQL::clear_schema();

		$query     = $this->getQuery();
		$variables = [
			'where' => [
				'location' => 'MY_MENU_LOCATION',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// the query for menu items filtered by location should return menu items
		// this was breaking when a taxonomy named location was set
		// as it was passing the location as a WP_Query arg
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['menuItems']['edges'] );

		foreach ( $this->created_menu_items['menu_item_ids'] as $menu_item_id ) {
			wp_delete_post( $menu_item_id, true );
		}

		unregister_taxonomy( 'location' );

	}

		/**
	 * Common asserts for testing pagination.
	 *
	 * @param array $expected An array of the results from WordPress. When testing backwards pagination, the order of this array should be reversed.
	 * @param array $actual The GraphQL results.
	 */
	public function assertValidPagination( $expected, $actual ) {
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 2, count( $actual['data']['menuItems']['edges'] ) );

		$first_post_id  = $expected[0]->ID;
		$second_post_id = $expected[1]->ID;

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_post_id );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_post_id );

		$this->assertEquals( $first_post_id, $actual['data']['menuItems']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first_post_id, $actual['data']['menuItems']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['menuItems']['edges'][0]['cursor'] );
		$this->assertEquals( $second_post_id, $actual['data']['menuItems']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second_post_id, $actual['data']['menuItems']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['menuItems']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['menuItems']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['menuItems']['pageInfo']['endCursor'] );
	}

	public function testQueryMenuItemsWithParentIdSetToZero() {

		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		$actual = $this->graphql([
			'query' => $this->getQuery(),
			'variables' => [
				'first' => 100,
				'after' => null,
				'where' => [
					'parentId' => 0
				],
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		codecept_debug( $actual );

		foreach ( $actual['data']['menuItems']['edges'] as $edge ) {
			$node = $edge['node'];
			$this->assertSame( 0, $node['parentDatabaseId'] );
			$this->assertNull( $node['parentId'] );
		}

	}

	public function testQueryMenuItemsWithParentDatabaseIdSetToZero() {

		$menu_location = 'my-menu-items-location';
		register_nav_menu( $menu_location, 'My MenuItems' );
		WPGraphQL::clear_schema();

		$created = $this->create_nested_menu( 3, $menu_location );

		$actual = $this->graphql([
			'query' => $this->getQuery(),
			'variables' => [
				'first' => 100,
				'where' => [
					'parentDatabaseId' => 0
				]
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		codecept_debug( $actual );

		foreach ( $actual['data']['menuItems']['edges'] as $edge ) {
			$node = $edge['node'];
			$this->assertSame( 0, $node['parentDatabaseId'] );
			$this->assertNull( $node['parentId'] );
		}

	}

}
