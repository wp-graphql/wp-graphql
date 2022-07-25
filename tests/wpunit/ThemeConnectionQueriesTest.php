<?php

class ThemeConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $current_date_gmt;
	public $current_date;
	public $current_time;

	/**
	 * @var WP_Theme
	 */
	public $active_theme;

	public function setUp(): void {
		// before
		parent::setUp();
		$this->clearSchema();

		$this->current_time     = strtotime( 'now' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
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

	/**
	 * testThemesQuery
	 *
	 * @dataProvider dataProviderUser
	 * This tests querying for themes to ensure that we're getting back a proper connection
	 */
	public function testThemesQuery( $user ) {

		$query = '
		{
			themes{
				edges{
					node{
						id
						name
					}
				}
				nodes {
					id
				}
			}
		}
		';

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

		$actual = $this->graphql( [ 'query' => $query ] );

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

	/**
	 * Tests querying for theme with pagination args.
	 */
	public function testThemesQueryPagination() {

		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
		wp_set_current_user( $this->admin );

		$query = '
			query testThemes($first: Int, $after: String, $last: Int, $before: String ) {
				themes(first: $first, last: $last, before: $before, after: $after) {
					pageInfo {
						endCursor
						hasNextPage
						hasPreviousPage
						startCursor
					}
					nodes {
						id
						name
					}
				}
			}
		';

		// Get all for comparison
		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => null,
			'before' => null,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );

		$nodes = $actual['data']['themes']['nodes'];

		// Get first two themes
		$variables['first'] = 2;

		$expected = array_slice( $nodes, 0, $variables['first'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['themes']['nodes'] );

		// Test with empty `after`.
		$variables['after'] = '';
		$actual             = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['themes']['nodes'] );

		// Get last two themes
		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => 2,
			'before' => null,
		];

		$expected = array_slice( $nodes, count( $nodes ) - $variables['last'], null, true );
		codecept_debug( [ 'expected' => $expected ] );
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['themes']['nodes'] );

		// Test with empty `before`.
		$variables['before'] = '';
		$actual              = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['themes']['nodes'] );
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
}
