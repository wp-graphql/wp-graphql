<?php

class ContentTypeConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $created_content_type_ids;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		wp_set_current_user( $this->admin );
		$this->created_content_type_ids = $this->create_content_types();
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		foreach ( $this->created_content_type_ids as $id ) {
			unregister_post_type( $id );
		}
		WPGraphQL::clear_schema();

		parent::tearDown();
	}

	public function createContentTypeObject( $name, $args = [] ) {
		register_post_type(
			$name,
			array_merge(
				[
					'label'               => __( 'Test CPT', 'wp-graphql' ),
					'labels'              => [
						'name'          => __( 'Test CPT', 'wp-graphql' ),
						'singular_name' => __( 'Test CPT', 'wp-graphql' ),
					],
					'description'         => __( 'test-post-type', 'wp-graphql' ),
					'supports'            => [ 'title' ],
					'show_in_graphql'     => true,
					'graphql_single_name' => 'TestCpt',
					'graphql_plural_name' => 'TestCpts',
				],
				$args
			)
		);
		return $name;
	}

	/**
	 * Creates several content types for use in pagination tests.
	 *
	 * @return array
	 */
	public function create_content_types() {
		$alphabet = range( 'A', 'Z' );

		$created_content_types = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$created_content_types[ $i ] = $this->createContentTypeObject(
				"type_connection-$i",
				[
					'label'               => $alphabet[ $i ],
					'labels'              => [
						'name'          => $alphabet[ $i ],
						'singular_name' => $alphabet[ $i ],
					],
					'graphql_single_name' => 'Cpt' . $alphabet[ $i ],
					'graphql_plural_name' => 'Cpt' . $alphabet[ $i ] . 's',
				]
			);
		}

		return $created_content_types;
	}

	public function getQuery() {
		return '
			query testContentTypes($first: Int, $after: String, $last: Int, $before: String ) {
				contentTypes(first: $first, after: $after, last: $last, before: $before) {
					pageInfo {
						endCursor
						hasNextPage
						hasPreviousPage
						startCursor
					}
					edges {
						cursor
						node {
							name
						}
					}
					nodes {
						name
					}
				}
			}
		';
	}

	public function testForwardPagination() {
		$query    = $this->getQuery();
		$wp_query = WPGraphQL::get_allowed_post_types( 'names' );
		$wp_query = array_values( $wp_query );

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
		$this->assertEquals( false, $actual['data']['contentTypes']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['contentTypes']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 2, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['contentTypes']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected = array_slice( $wp_query, 4, 2, false );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasPreviousPage'] );

		$this->assertEquals( false, $actual['data']['contentTypes']['pageInfo']['hasNextPage'] );

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
		$wp_query = WPGraphQL::get_allowed_post_types( 'names' );
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
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['contentTypes']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['contentTypes']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 2, 2, false );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['contentTypes']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected = array_slice( array_reverse( $wp_query ), 4, 2, false );
		$expected = array_reverse( $expected );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['contentTypes']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['contentTypes']['pageInfo']['hasNextPage'] );

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

		$after_cursor  = $actual['data']['contentTypes']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['contentTypes']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['contentTypes']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['contentTypes']['nodes'][0] );

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
		$this->assertSame( $expected, $actual['data']['contentTypes']['nodes'][0] );
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

		$this->assertEquals( 2, count( $actual['data']['contentTypes']['edges'] ) );

		$first_content_type  = $expected[0];
		$second_content_type = $expected[1];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_content_type );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_content_type );

		$this->assertEquals( $first_content_type, $actual['data']['contentTypes']['edges'][0]['node']['name'] );
		$this->assertEquals( $first_content_type, $actual['data']['contentTypes']['nodes'][0]['name'] );
		$this->assertEquals( $start_cursor, $actual['data']['contentTypes']['edges'][0]['cursor'] );
		$this->assertEquals( $second_content_type, $actual['data']['contentTypes']['edges'][1]['node']['name'] );
		$this->assertEquals( $second_content_type, $actual['data']['contentTypes']['nodes'][1]['name'] );
		$this->assertEquals( $end_cursor, $actual['data']['contentTypes']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['contentTypes']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['contentTypes']['pageInfo']['endCursor'] );
	}
}
