<?php

class RegisteredScriptConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
	public function testRegisteredScriptsQueryPagination() {
		wp_set_current_user( $this->admin );

		$query = '
			query testRegisteredScripts($first: Int, $after: String, $last: Int, $before: String ) {
				registeredScripts(first: $first, last: $last, before: $before, after: $after) {
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

		codecept_debug( $actual );

		$this->assertIsValidQueryResponse( $actual );

		$nodes = $actual['data']['registeredScripts']['nodes'];

		// there's more than 200 stylesheets registered, so we query for ALL of them to make sure we have
		// all the nodes when doing the tests
		// this doesn't feel like it scales well, but can be refactored later
		if ( $actual['data']['registeredScripts']['pageInfo']['hasNextPage'] ) {
			$variables['after'] = $actual['data']['registeredScripts']['pageInfo']['endCursor'];
			$actual = $this->graphql( compact( 'query', 'variables' ) );
			$nodes = array_merge( $nodes, $actual['data']['registeredScripts']['nodes'] );
		}

		if ( $actual['data']['registeredScripts']['pageInfo']['hasNextPage'] ) {
			$variables['after'] = $actual['data']['registeredScripts']['pageInfo']['endCursor'];
			$actual = $this->graphql( compact( 'query', 'variables' ) );
			$nodes = array_merge( $nodes, $actual['data']['registeredScripts']['nodes'] );
		}

		// Get first two registeredScripts
		$variables['first'] = 2;
		$variables['after'] = null;

		$expected = array_slice( $nodes, 0, $variables['first'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['registeredScripts']['nodes'] );

		// Test with empty `after`.
		$variables['after'] = '';
		$actual             = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['registeredScripts']['nodes'] );

		// Get last two registeredScripts
		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => 2,
			'before' => null,
		];

		$expected = array_slice( array_reverse( $nodes ), null, $variables['last'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['registeredScripts']['nodes'] );

		// Test with empty `before`.
		$variables['before'] = '';
		$actual              = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['registeredScripts']['nodes'] );
	}

}
