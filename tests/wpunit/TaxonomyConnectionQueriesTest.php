<?php

class TaxonomyConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public $created_taxonomy_ids;

	/**
	 * @var array|string[]|\WP_Taxonomy[]
	 */
	public $taxonomies = [];

	public function setUp(): void {
		parent::setUp();

		$this->created_taxonomy_ids = $this->create_taxonomies();

		$this->clearSchema();

		$this->taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );
	}

	public function tearDown(): void {
		foreach ( $this->created_taxonomy_ids as $id ) {
			unregister_taxonomy( $id );
		}

		$this->clearSchema();

		parent::tearDown();
	}

	public function createTaxonomyObject( $name, $args = [] ) {
		register_taxonomy(
			$name,
			[ 'post' ],
			array_merge(
				[
					'show_in_graphql'     => true,
					'graphql_single_name' => 'TestTerm',
					'graphql_plural_name' => 'TestTerms',
				],
				$args
			)
		);

		return $name;
	}

	/**
	 * Creates several taxonomies for use in pagination tests.
	 *
	 * @return array
	 */
	public function create_taxonomies() {
		$alphabet = range( 'A', 'Z' );

		$created_taxonomies = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$created_taxonomies[ $i ] = $this->createTaxonomyObject(
				'tax_connection_queries-' . $alphabet[ $i ],
				[
					'graphql_single_name' => 'TestTerm' . $alphabet[ $i ],
					'graphql_plural_name' => 'TestTerm' . $alphabet[ $i ] . 's',
				]
			);
		}

		return $created_taxonomies;
	}

	public function getQuery() {
		return '
			query testTaxonomies($first: Int, $after: String, $last: Int, $before: String ) {
				taxonomies(first: $first, last: $last, before: $before, after: $after) {
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
							name
						}
					}
					nodes {
						id
						name
					}
				}
			}
		';
	}

	public function testForwardPagination() {
		$query    = $this->getQuery();
		$wp_query = WPGraphQL::get_allowed_taxonomies( 'names' );
		$wp_query = array_values( $wp_query );

		codecept_debug( $wp_query );

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
		$this->assertEquals( false, $actual['data']['taxonomies']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['taxonomies']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 2, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );

		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['taxonomies']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 4, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['taxonomies']['pageInfo']['hasNextPage'] );

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
		$wp_query = WPGraphQL::get_allowed_taxonomies( 'names' );
		$wp_query = array_values( $wp_query );

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
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['taxonomies']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['taxonomies']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 2, 2, false );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['taxonomies']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 4, 2, false );
		$expected = array_reverse( $expected );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['taxonomies']['pageInfo']['hasNextPage'] );
		$this->assertEquals( false, $actual['data']['taxonomies']['pageInfo']['hasPreviousPage'] );

		/**
		 * Test the last two results are equal to `first:2`.
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

		$after_cursor  = $actual['data']['taxonomies']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['taxonomies']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['taxonomies']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['taxonomies']['nodes'][0] );

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

		$this->assertSame( $expected, $actual['data']['taxonomies']['nodes'][0] );
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

		$this->assertEquals( 2, count( $actual['data']['taxonomies']['edges'] ) );

		$first_taxonomy  = $expected[0];
		$second_taxonomy = $expected[1];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_taxonomy );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_taxonomy );

		$this->assertEquals( $first_taxonomy, $actual['data']['taxonomies']['edges'][0]['node']['name'] );
		$this->assertEquals( $first_taxonomy, $actual['data']['taxonomies']['nodes'][0]['name'] );
		$this->assertEquals( $start_cursor, $actual['data']['taxonomies']['edges'][0]['cursor'] );
		$this->assertEquals( $second_taxonomy, $actual['data']['taxonomies']['edges'][1]['node']['name'] );
		$this->assertEquals( $second_taxonomy, $actual['data']['taxonomies']['nodes'][1]['name'] );
		$this->assertEquals( $end_cursor, $actual['data']['taxonomies']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['taxonomies']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['taxonomies']['pageInfo']['endCursor'] );
	}
}
