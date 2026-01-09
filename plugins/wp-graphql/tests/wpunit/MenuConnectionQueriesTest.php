<?php

use WPGraphQL\Type\WPEnumType;

class MenuConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $created_menus;

	public function setUp(): void {
		// before

		$this->clearSchema();
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		add_theme_support( 'nav_menu_locations' );
		register_nav_menu( 'my-menu-location', 'My Menu' );
		set_theme_mod( 'nav_menu_locations', [ 'my-menu-location' => 0 ] );

		$this->created_menus = $this->create_menus();
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		parent::tearDown();
	}

	public function createMenuObject( $slug = '' ) {
		$menu_slug = $slug ?: 'my-test-menu';

		return wp_create_nav_menu( $menu_slug );
	}

	/**
	 * Creates several menus (with different slugs) for use in pagination tests.
	 *
	 * @return array
	 */
	public function create_menus() {
		// Create 6 menus
		$created_menus = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			// Set the date 1 minute apart for each post
			$menu_slug           = 'my-test-menu-' . $i;
			$created_menus[ $i ] = $this->createMenuObject( $menu_slug );
		}

		return $created_menus;
	}

	public function getQuery() {
		return '
			query menusQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToMenuConnectionWhereArgs ){
				menus( first:$first last:$last after:$after before:$before where:$where ) {
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
							name
						}
					}
					nodes {
						databaseId
					}
				}
			}
		';
	}

	/**
	 * Tests querying for menus with id where arg.
	 */
	public function testMenusQueryById() {
		$menu_id = $this->created_menus[2];

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'id' => $menu_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );

		// a public request should get no menus if there are none associated with a location
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		wp_set_current_user( $this->admin );
		$expected = wp_get_nav_menu_object( $menu_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 1, count( $actual['data']['menus']['edges'] ) );
		$this->assertEquals( $menu_id, $actual['data']['menus']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $expected->name, $actual['data']['menus']['edges'][0]['node']['name'] );
	}

	public function testMenusQueryByLocation() {

		$menu_id = $this->created_menus[3];

		$menu_location = 'my-menu-location';

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'location' => WPEnumType::get_safe_name( $menu_location ),
			],
		];

		// Test when no menu is assigned to location.
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		// Test with menu is assigned to location.
		set_theme_mod( 'nav_menu_locations', [ $menu_location => $menu_id ] );
		$expected = wp_get_nav_menu_object( $menu_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 1, count( $actual['data']['menus']['edges'] ) );
		$this->assertEquals( $menu_id, $actual['data']['menus']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $expected->name, $actual['data']['menus']['edges'][0]['node']['name'] );
	}

	public function testMenusQueryBySlug() {
		$menu_id     = $this->created_menus[5];
		$menu_object = wp_get_nav_menu_object( $menu_id );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'slug' => $menu_object->slug,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// a public request should get no menus if there are none associated with a location
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 1, count( $actual['data']['menus']['edges'] ) );
		$this->assertEquals( $menu_id, $actual['data']['menus']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $menu_object->slug, $actual['data']['menus']['edges'][0]['node']['name'] );
	}

	public function testForwardPagination() {
		wp_set_current_user( $this->admin );

		$query    = $this->getQuery();
		$wp_query = wp_get_nav_menus();

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
		$this->assertEquals( false, $actual['data']['menus']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['menus']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 2, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['menus']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 4, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasPreviousPage'] );

		$this->assertEquals( false, $actual['data']['menus']['pageInfo']['hasNextPage'] );

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
		wp_set_current_user( $this->admin );

		$query    = $this->getQuery();
		$wp_query = wp_get_nav_menus();

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
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['menus']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['menus']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 2, 2, false );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['menus']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 4, 2, false );
		$expected = array_reverse( $expected );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['menus']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['menus']['pageInfo']['hasNextPage'] );

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
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		$variables = [
			'first' => 5,
		];

		/**
		 * Test `first`.
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$after_cursor  = $actual['data']['menus']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['menus']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['menus']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['menus']['nodes'][0] );

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

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['menus']['nodes'][0] );
	}

	/**
	 * Common asserts for testing pagination.
	 *
	 * @param array $expected An array of the results from WordPress. When testing backwards pagination, the order of this array should be reversed.
	 * @param array $actual The GraphQL results.
	 */
	public function assertValidPagination( $expected, $actual ) {
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 2, count( $actual['data']['menus']['edges'] ) );

		$first  = $expected[0];
		$second = $expected[1];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first->term_id );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second->term_id );

		$this->assertEquals( $first->term_id, $actual['data']['menus']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first->term_id, $actual['data']['menus']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['menus']['edges'][0]['cursor'] );
		$this->assertEquals( $second->term_id, $actual['data']['menus']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second->term_id, $actual['data']['menus']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['menus']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['menus']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['menus']['pageInfo']['endCursor'] );
	}
}
