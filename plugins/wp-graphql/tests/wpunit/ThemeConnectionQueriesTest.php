<?php

class ThemeConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $current_date_gmt;
	public $current_date;
	public $current_time;

	/**
	 * @var \WP_Theme
	 */
	public $active_theme;

	public function setUp(): void {
		// before
		parent::setUp();
		$this->clearSchema();

		$this->current_time     = strtotime( 'now' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$themes                 = wp_get_themes();
		$this->active_theme     = $themes[ array_key_first( $themes ) ]->get_stylesheet();
		update_option( 'template', $this->active_theme );
		update_option( 'stylesheet', $this->active_theme );
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();

		// then
		wp_logout();
		parent::tearDown();
	}

	public function getQuery() {
		return '
			query themesQuery( $first: Int, $last:Int, $after:String, $before:String ) {
				themes( first: $first, last: $last, after: $after, before: $before ) {
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
							slug
							name
						}
					}
					nodes {
						id
						slug
						name
					}
				}
			}
		';
	}

	/**
	 * testThemesQuery
	 *
	 * @dataProvider dataProviderUser
	 * This tests querying for themes to ensure that we're getting back a proper connection
	 */
	public function testThemesQuery( $user ) {
		$query  = $this->getQuery();
		$themes = wp_get_themes( [ 'allowed' => null ] );

		if ( ! empty( $user ) ) {
			$current_user = $this->admin;
			$return_count = count( $themes );
		} else {
			$current_user = 0;
			$return_count = 1;
		}

		if ( is_multisite() ) {
			grant_super_admin( $current_user );
		}

		wp_set_current_user( $current_user );

		// Request all themes explicitly (default is 10, but wp-env has 15 themes)
		$actual = $this->graphql( [
			'query'     => $query,
			'variables' => [ 'first' => 100 ],
		] );

		/**
		 * We don't really care what the specifics are because the default theme could change at any time
		 * and we don't care to maintain the exact match, we just want to make sure we are
		 * properly getting a theme back in the query
		 */
		$this->assertNotEmpty( $actual['data']['themes']['edges'] );
		$this->assertNotEmpty( $actual['data']['themes']['edges'][0]['node']['id'] );
		$this->assertNotEmpty( $actual['data']['themes']['edges'][0]['node']['name'] );
		$this->assertNotEmpty( $actual['data']['themes']['nodes'][0]['id'] );
		$this->assertEquals( $actual['data']['themes']['nodes'][0]['id'], $actual['data']['themes']['edges'][0]['node']['id'] );
		$this->assertCount( $return_count, $actual['data']['themes']['edges'] );

		foreach ( $actual['data']['themes']['edges'] as $key => $edge ) {
			$this->assertEquals( $actual['data']['themes']['nodes'][ $key ]['id'], $edge['node']['id'] );
		}
	}

	public function testForwardPagination() {
		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of themes might change, so we'll reuse this to check later.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		// Confirm its valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['themes']['edges'][0]['node']['slug'] );

		// Store for use by $expected.
		$wp_query    = $actual['data']['themes'];
		$total_count = count( $wp_query['edges'] );

		// We need at least 3 themes for meaningful pagination tests
		$this->assertGreaterThanOrEqual( 3, $total_count, 'Need at least 3 themes for pagination tests' );

		/**
		 * Test the first two results.
		 */
		$variables = [
			'first' => 2,
		];

		$expected = $wp_query;
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertFalse( $actual['data']['themes']['pageInfo']['hasPreviousPage'] );
		// There should be more pages since we have at least 3 items
		$this->assertTrue( $actual['data']['themes']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['after'] = '';
		$expected           = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next page of results.
		 */
		$variables['after'] = $actual['data']['themes']['pageInfo']['endCursor'];

		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['themes']['pageInfo']['hasPreviousPage'] );

		// Verify returned items exist in expected results
		$actual_slugs   = array_column( $actual['data']['themes']['nodes'], 'slug' );
		$expected_slugs = array_column( $expected['nodes'], 'slug' );
		foreach ( $actual_slugs as $slug ) {
			$this->assertContains( $slug, $expected_slugs, "Theme slug {$slug} should be in expected results" );
		}

		// hasNextPage depends on how many items are left
		$items_seen = 2 + count( $actual['data']['themes']['edges'] );
		$this->assertEquals( $items_seen < $total_count, $actual['data']['themes']['pageInfo']['hasNextPage'] );

		/**
		 * Test that fetching all with last:100 returns the same as first:100.
		 */
		$variables = [
			'last' => 100,
		];
		$expected  = $wp_query;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['themes'] );
	}

	public function testBackwardPagination() {
		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of themes might change, so we'll reuse this to check later.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'last' => 100,
				],
			]
		);

		// Confirm its valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['themes']['edges'][0]['node']['slug'] );

		$wp_query    = $actual['data']['themes'];
		$total_count = count( $wp_query['edges'] );

		// We need at least 3 themes for meaningful pagination tests
		$this->assertGreaterThanOrEqual( 3, $total_count, 'Need at least 3 themes for pagination tests' );

		/**
		 * Test the last two results (backward pagination starts from the end).
		 */
		$variables = [
			'last' => 2,
		];

		// Expected: the last 2 items from the full list
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], -2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], -2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		// There should be previous pages since we have at least 3 items
		$this->assertTrue( $actual['data']['themes']['pageInfo']['hasPreviousPage'] );
		$this->assertFalse( $actual['data']['themes']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['before'] = '';
		$expected            = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test paginating backwards from the current position.
		 */
		$variables['before'] = $actual['data']['themes']['pageInfo']['startCursor'];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['themes']['pageInfo']['hasNextPage'] );

		// Verify returned items exist in the full list
		$actual_slugs = array_column( $actual['data']['themes']['nodes'], 'slug' );
		$all_slugs    = array_column( $wp_query['nodes'], 'slug' );
		foreach ( $actual_slugs as $slug ) {
			$this->assertContains( $slug, $all_slugs, "Theme slug {$slug} should be in results" );
		}

		// hasPreviousPage depends on how many items are left before current position
		$items_from_end = 2 + count( $actual['data']['themes']['edges'] );
		$this->assertEquals( $items_from_end < $total_count, $actual['data']['themes']['pageInfo']['hasPreviousPage'] );

		/**
		 * Test that fetching all with first:100 returns the same as last:100.
		 */
		$variables = [
			'first' => 100,
		];
		$expected  = $wp_query;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['themes'] );
	}

	public function testQueryWithFirstAndLast() {
		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of themes might change, so we'll reuse this to check late.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		$after_cursor  = $actual['data']['themes']['edges'][0]['cursor'];
		$before_cursor = $actual['data']['themes']['edges'][2]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['themes']['nodes'][1];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( $expected, $actual['data']['themes']['nodes'][0] );

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
		$this->assertSame( $expected, $actual['data']['themes']['nodes'][0] );
	}

	public function dataProviderUser() {
		return [
			[
				'user' => 'admin',
			],
			[
				'user' => null,
			],
		];
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

		$this->assertEquals( 2, count( $actual['data']['themes']['edges'] ) );

		$first_theme_slug  = $expected['nodes'][0]['slug'];
		$second_theme_slug = $expected['nodes'][1]['slug'];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_theme_slug );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_theme_slug );

		$this->assertEquals( $first_theme_slug, $actual['data']['themes']['edges'][0]['node']['slug'] );
		$this->assertEquals( $first_theme_slug, $actual['data']['themes']['nodes'][0]['slug'] );
		$this->assertEquals( $start_cursor, $actual['data']['themes']['edges'][0]['cursor'] );
		$this->assertEquals( $second_theme_slug, $actual['data']['themes']['edges'][1]['node']['slug'] );
		$this->assertEquals( $second_theme_slug, $actual['data']['themes']['nodes'][1]['slug'] );
		$this->assertEquals( $end_cursor, $actual['data']['themes']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['themes']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['themes']['pageInfo']['endCursor'] );
	}
}
