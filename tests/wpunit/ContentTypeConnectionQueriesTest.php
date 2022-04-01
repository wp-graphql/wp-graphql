<?php

class ContentTypeConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Tests querying for plugins with pagination args.
	 */
	public function testContentTypesQueryPagination() {
		$query = '
			query testContentTypes($first: Int, $after: String, $last: Int, $before: String ) {
				contentTypes(first: $first, last: $last, before: $before, after: $after) {
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

		$nodes = $actual['data']['contentTypes']['nodes'];

		// Get first two contentTypes
		$variables['first'] = 2;

		$expected = array_slice( $nodes, 0, $variables['first'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['contentTypes']['nodes'] );

		// Test with empty `after`.
		$variables['after'] = '';
		$actual             = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['contentTypes']['nodes'] );

		// Get last two contentTypes
		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => 2,
			'before' => null,
		];


		$expected = array_slice( array_reverse( $nodes ), null, $variables['last'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( [
			'expected' => $expected,
			'nodes' => $nodes,
			'actual' => $actual
		]);
		$this->assertEqualSets( $expected, $actual['data']['contentTypes']['nodes'] );

		// Test with empty `before`.
		$variables['before'] = '';
		$actual              = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['contentTypes']['nodes'] );
	}

}
