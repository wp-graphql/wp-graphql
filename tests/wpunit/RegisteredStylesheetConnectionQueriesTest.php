<?php

class RegisteredStylesheetConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Tests querying for plugins with pagination args.
	 */
	public function testRegisteredStylesheetsQueryPagination() {
		wp_set_current_user( $this->admin );

		global $wp_styles;
		do_action( 'wp_enqueue_scripts' );

		$all_registered = array_keys( $wp_styles->registered );

		$query = '
			query testRegisteredStylesheets($first: Int, $after: String, $last: Int, $before: String ) {
				registeredStylesheets(first: $first, last: $last, before: $before, after: $after) {
					pageInfo {
						endCursor
						hasNextPage
						hasPreviousPage
						startCursor
					}
					nodes {
						id
						handle
					}
				}
			}
		';

		// Get all for comparison
		$variables = [
			'first'  => 100,
			'after'  => null,
			'last'   => null,
			'before' => null,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );

		$nodes = $actual['data']['registeredStylesheets']['nodes'];

		// there's more than 200 stylesheets registered, so we query for ALL of them to make sure we have
		// all the nodes when doing the tests
		// this doesn't feel like it scales well, but can be refactored later
		if ( $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] ) {
			$variables['after'] = $actual['data']['registeredStylesheets']['pageInfo']['endCursor'];
			$actual = $this->graphql( compact( 'query', 'variables' ) );
			$nodes = array_merge( $nodes, $actual['data']['registeredStylesheets']['nodes'] );
		}

		if ( $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] ) {
			$variables['after'] = $actual['data']['registeredStylesheets']['pageInfo']['endCursor'];
			$actual = $this->graphql( compact( 'query', 'variables' ) );
			$nodes = array_merge( $nodes, $actual['data']['registeredStylesheets']['nodes'] );
		}

		// Get first two registeredStylesheets
		$variables['first'] = 2;
		$variables['after'] = null;

		$expected = array_slice( $nodes, 0, $variables['first'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );


		codecept_debug( [
			'expected' => $expected,
			'actual' => $actual['data']['registeredStylesheets']['nodes']
		]);

		$this->assertEqualSets( $expected, $actual['data']['registeredStylesheets']['nodes'] );

		// Test with empty `after`.
		$variables['after'] = '';
		$actual             = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['registeredStylesheets']['nodes'] );

		// Get last two registeredStylesheets
		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => 2,
			'before' => null,
		];

		$expected = array_slice( array_reverse( $nodes ), null, $variables['last'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( [
			'nodes' => $nodes,
			'actual' => $actual,
			'expected' => $expected
		]);

		$this->assertEqualSets( $expected, $actual['data']['registeredStylesheets']['nodes'] );



		// Test with empty `before`.
		$variables['before'] = '';
		$actual              = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['registeredStylesheets']['nodes'] );
	}

}
