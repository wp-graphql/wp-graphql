<?php

class TermObjectConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $current_date_gmt;
	public $current_date;
	public $created_term_ids;
	public $current_time;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = self::factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->created_term_ids = $this->create_terms();
	}

	public function tearDown(): void {
		// your tear down methods here

		// then
		parent::tearDown();
	}

	public function createTermObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'taxonomy'    => 'category',
			'description' => 'just a description',
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		return self::factory()->term->create( $args );
	}

	/**
	 * Creates several posts (with different timestamps) for use in cursor query tests
	 *
	 * @return array
	 */
	public function create_terms() {
		$alphabet = range( 'A', 'Z' );

		// Create 20 posts
		$created_terms = [
			1 => 1, // id 1 is reserved for 'uncategorized'
		];

		for ( $i = 2; $i <= 6; $i++ ) {
			$term_id             = $this->createTermObject(
				[
					'taxonomy'    => 'category',
					'description' => 'term description',
					'name'        => 'Term-' . $alphabet[ $i ],
					'slug'        => 'term-' . $alphabet[ $i ],
				]
			);
			$created_terms[ $i ] = $term_id;
		}

		return $created_terms;
	}

	public function getQuery() {
		return '
			query categoriesQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToCategoryConnectionWhereArgs){
				categories( first:$first last:$last after:$after before:$before where:$where ) {
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
							description
							slug
							parentDatabaseId
						}
					}
					nodes {
						databaseId
					}
				}
			}
		';
	}

	public function testConnectedTermsConnectionReturnsTermNodesOfExpectedTaxonomies() {
		$term_one_id = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Term One',
			]
		);

		$term_two_id = self::factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => 'Term Two',
			]
		);

		$query = '
		query GetConnectedTerms {
			taxonomies {
				nodes {
					name
					connectedTerms {
						nodes {
							__typename
							databaseId
							name
							uri
						}
					}
				}
			} 
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// codecept_debug( [ '$actual' => $actual ]);
		$this->assertArrayNotHasKey( 'errors', $actual );

		// map over the connected terms nodes and assert that all of them are category
		$connected_categories = $actual['data']['taxonomies']['nodes'][0]['connectedTerms']['nodes'];
		foreach ( $connected_categories as $connected_category ) {
			$this->assertEquals( 'Category', $connected_category['__typename'] );
		}

		// map over the connected terms nodes and assert that all of them are tags
		$connected_tags = $actual['data']['taxonomies']['nodes'][1]['connectedTerms']['nodes'];
		foreach ( $connected_tags as $connected_tag ) {
			$this->assertEquals( 'Tag', $connected_tag['__typename'] );
		}

		wp_delete_term( $term_one_id, 'category' );
		wp_delete_term( $term_two_id, 'post_tag' );
	}

	public function testForwardPagination() {
		$query    = $this->getQuery();
		$wp_query = new WP_Term_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'graphql_cursor_compare' => '>',
			'taxonomy'               => 'category',
			'number'                 => 2,
			'offset'                 => 0,
			'order'                  => 'ASC',
			'orderby'                => 'name',
			'parent'                 => 0,
			'hide_empty'             => false,
		];

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['categories']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['categories']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );

		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['categories']['pageInfo']['hasNextPage'] );

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
		$wp_query = new WP_Term_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'last' => 2,
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'graphql_cursor_compare' => '>',
			'taxonomy'               => 'category',
			'number'                 => 2,
			'offset'                 => 0,
			'order'                  => 'DESC',
			'orderby'                => 'name',
			'parent'                 => 0,
			'hide_empty'             => false,
		];

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['categories']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['categories']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['categories']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['categories']['pageInfo']['hasNextPage'] );
		$this->assertEquals( false, $actual['data']['categories']['pageInfo']['hasPreviousPage'] );

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

		$after_cursor  = $actual['data']['categories']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['categories']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['categories']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['categories']['nodes'][0] );

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
		$this->assertSame( $expected, $actual['data']['categories']['nodes'][0] );
	}

	public function testWhereArgs() {
		$query = $this->getQuery();

		$parent_id = self::factory()->term->create(
			[
				'taxonomy'    => 'category',
				'name'        => 'Parent Category',
				'description' => 'parent category term_description',
			]
		);

		$child_id = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Child Category',
				'parent'   => $parent_id,
			]
		);

		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		wp_set_object_terms( $post_id, [ $child_id ], 'category' );

		// test without childless

		$variables = [
			'where' => [
				'childless' => false,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 8, $actual['data']['categories']['nodes'] );

		// test with childless
		$variables['where']['childless'] = true;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 7, $actual['data']['categories']['nodes'] );

		// test childOf
		$variables = [
			'where' => [
				'childOf' => $parent_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $child_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test descriptionLike
		$variables = [
			'where' => [
				'descriptionLike' => 'term_description',
			],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $parent_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test exclude with global + db id
		$parent_global_id = \GraphQLRelay\Relay::toGlobalId( 'term', $parent_id );

		$variables = [
			'where' => [
				'exclude' => [ $this->created_term_ids[1], $parent_global_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $child_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test excludeTree with global + db id
		$variables = [
			'where' => [
				'excludeTree' => [ $this->created_term_ids[1], $parent_global_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 5, $actual['data']['categories']['nodes'] );

		// test hideEmpty
		$variables = [
			'where' => [
				'hideEmpty' => true,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['categories']['nodes'] );

		// test hideEmpty without hierarchical
		$variables['where']['hierarchical'] = false;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );

		// test include with global + db id
		$variables = [
			'where' => [
				'include' => [ $this->created_term_ids[1], $parent_global_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['categories']['nodes'] );

		// test name
		$variables = [
			'where' => [
				'name' => 'Parent Category',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $parent_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test nameLike
		$variables = [
			'where' => [
				'nameLike' => 'Parent',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $parent_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test parent
		$variables = [
			'where' => [
				'parent' => $parent_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $child_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test search
		$variables = [
			'where' => [
				'search' => 'child',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $child_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test slug
		$variables = [
			'where' => [
				'slug' => 'parent-category',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['categories']['nodes'] );
		$this->assertEquals( $parent_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		// test termTaxonomyId with global + db id.
		$term_taxonomy_one_id = get_term_by( 'id', $parent_id, 'category' )->term_taxonomy_id;
		$term_taxonomy_two_id = get_term_by( 'id', $child_id, 'category' )->term_taxonomy_id;

		$term_taxonomy_one_global_id = \GraphQLRelay\Relay::toGlobalId( 'term', $term_taxonomy_one_id );

		$variables = [
			'where' => [
				'termTaxonomyId' => [ $term_taxonomy_two_id, $term_taxonomy_one_global_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['categories']['nodes'] );
	}

	public function testObjectIdsWhereArgs() {
		$query = $this->getQuery();

		$term_one_id = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'ObjectIdOne Category',
			]
		);
		$term_two_id = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'ObjectIdTwo Category',
			]
		);

		$post_one_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$post_two_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		wp_set_object_terms( $post_one_id, [ $term_one_id ], 'category' );
		wp_set_object_terms( $post_two_id, [ $term_two_id ], 'category' );

		// test objectIds with global + db id
		$post_one_global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_one_id );

		$variables = [
			'where' => [
				'objectIds' => [ $post_one_global_id, $post_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['categories']['nodes'] );
	}

	public function testOrderWhereArgs() {

		$category_id = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'high count',
			]
		);

		for ( $x = 0; $x <= 10; $x++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_type'   => 'post',
					'post_status' => 'publish',
				]
			);

			wp_set_object_terms( $post_id, [ $category_id ], 'category' );
		}

		$query = '
		query GetCategoriesWithCustomOrder( $order:OrderEnum ){
			categories( where: { orderby: COUNT order: $order } ) {
				nodes {
					id
					databaseId
					name
					count
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'order' => 'DESC',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $category_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'order' => 'ASC',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $category_id !== $actual['data']['categories']['nodes'][0]['databaseId'] );
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

		$expected_count = count( $expected ) ?? 2;
		$this->assertEquals( $expected_count, count( $actual['data']['categories']['edges'] ) );
		$expected = array_values( $expected );

		$first  = $expected[0];
		$second = $expected[1];
		$last   = end( $expected );

		$start_cursor = $this->toRelayId( 'arrayconnection', $first->term_id );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $last->term_id );

		$this->assertEquals( $first->term_id, $actual['data']['categories']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first->term_id, $actual['data']['categories']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['categories']['edges'][0]['cursor'] );
		$this->assertEquals( $second->term_id, $actual['data']['categories']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second->term_id, $actual['data']['categories']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['categories']['edges'][ $expected_count - 1 ]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['categories']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['categories']['pageInfo']['endCursor'] );
	}

	public function testQueryForAncestorsIsInCorrectOrder() {

		$parent = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'A Parent', // name starts with A to trip up default ordering
			]
		);

		$child = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Child',
				'parent'   => $parent,
			]
		);

		$grandchild = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Grandchild',
				'parent'   => $child,
			]
		);

		codecept_debug(
			[
				'parent'     => $parent,
				'child'      => $child,
				'grandchild' => $grandchild,
			]
		);

		// update the parent post. the default ordering (by date) might

		$query = '
		query GetCategoryAncestors($id:ID!){
			category(id:$id idType:DATABASE_ID) {
				databaseId
				name
				ancestors {
					nodes {
						databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $grandchild,
				],
			]
		);

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'category.ancestors.nodes',
					[
						'databaseId' => $parent,
					]
				),
				$this->expectedNode(
					'category.ancestors.nodes',
					[
						'databaseId' => $child,
					]
				),
			]
		);

		$actual_ancestor_ids = [];

		foreach ( $actual['data']['category']['ancestors']['nodes'] as $ancestor ) {
			$actual_ancestor_ids[] = $ancestor['databaseId'];
		}

		$expected_ancestor_ids = get_ancestors( $grandchild, 'category', 'taxonomy' );

		$this->assertSame( $actual_ancestor_ids, $expected_ancestor_ids );

		wp_delete_term( $parent, 'category' );
		wp_delete_term( $child, 'category' );
		wp_delete_term( $grandchild, 'category' );
	}

	public function testPaginateTermsWithDuplicateNamesAndOrderbyName() {

		$parent_category = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Parent Category',
			]
		);

		$child_categories = [];

		for ( $x = 0; $x <= 10; $x++ ) {
			$child_categories[] = self::factory()->term->create_and_get(
				[
					'taxonomy' => 'category',
					'slug'     => 'child-term-' . $x,
					'name'     => 'Child Term',
					'parent'   => $parent_category,
				]
			);
		}

		$child_categories = array_reverse( $child_categories );

		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'first' => 3,
					'after' => null,
					'where' => [
						'parent'  => $parent_category,
						'order'   => 'ASC',
						'orderby' => 'NAME',
					],
				],
			]
		);

		$expected = [
			$child_categories[0],
			$child_categories[1],
			$child_categories[2],
		];

		codecept_debug(
			[
				'$actual'   => $actual,
				'$expected' => $expected,
			]
		);

		$this->assertValidPagination( $expected, $actual );

		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'first' => 3,
					'after' => $actual['data']['categories']['pageInfo']['endCursor'],
					'where' => [
						'parent'  => $parent_category,
						'order'   => 'ASC',
						'orderby' => 'NAME',
					],
				],
			]
		);

		$expected = [
			$child_categories[3],
			$child_categories[4],
			$child_categories[5],
		];

		$this->assertValidPagination( $expected, $actual );

		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'first' => 3,
					'after' => $actual['data']['categories']['pageInfo']['endCursor'],
					'where' => [
						'parent'  => $parent_category,
						'order'   => 'ASC',
						'orderby' => 'NAME',
					],
				],
			]
		);

		$expected = [
			$child_categories[6],
			$child_categories[7],
			$child_categories[8],
		];

		$this->assertValidPagination( $expected, $actual );

		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'last'   => 3,
					'before' => null,
					'where'  => [
						'parent'  => $parent_category,
						'order'   => 'ASC',
						'orderby' => 'NAME',
					],
				],
			]
		);

		$expected = [
			$child_categories[8],
			$child_categories[9],
			$child_categories[10],
		];

		$this->assertValidPagination( $expected, $actual );

		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'last'   => 3,
					'before' => $actual['data']['categories']['pageInfo']['startCursor'],
					'where'  => [
						'parent'  => $parent_category,
						'order'   => 'ASC',
						'orderby' => 'NAME',
					],
				],
			]
		);

		$expected = [
			$child_categories[5],
			$child_categories[6],
			$child_categories[7],
		];

		$this->assertValidPagination( $expected, $actual );

		foreach ( $child_categories as $child_category ) {
			wp_delete_term( $child_category->term_id, 'category' );
		}

		wp_delete_term( $parent_category, 'category' );
	}
}
