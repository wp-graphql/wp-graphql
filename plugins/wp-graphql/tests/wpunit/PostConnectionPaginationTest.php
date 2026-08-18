<?php

class PostConnectionPaginationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $created_post_ids;
	public $current_date_gmt;
	public $current_date;
	public $current_time;
	public $subscriber;

	public function setUp(): void {
		parent::setUp();

		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->subscriber       = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$this->created_post_ids = $this->create_posts();
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		foreach ( $this->created_post_ids as $id ) {
			wp_delete_post( $id, true );
		}

		parent::tearDown();
	}

	public function createPostObject( $args = [] ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'   => $this->admin,
			'post_content'  => 'Test page content',
			'post_excerpt'  => 'Test excerpt',
			'post_status'   => 'publish',
			'post_title'    => 'Test Page for PostConnectionPaginationTest',
			'post_type'     => 'post',
			'post_date'     => $this->current_date,
			'has_password'  => false,
			'post_password' => null,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory()->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 *
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;
	}

	/**
	 * Creates several posts for use in cursor query tests
	 *
	 * @param   int $count Number of posts to create.
	 *
	 * @return array
	 */
	public function create_posts( $count = 6 ) {
		$alphabet = range( 'A', 'Z' );

		// Create posts
		$created_posts = [];
		for ( $i = 1; $i <= $count; $i++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day -{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject(
				[
					'post_type'   => 'post',
					'post_date'   => $date,
					'post_status' => 'publish',
					'post_title'  => 'Test Post for ' . $alphabet[ $i ],
				]
			);
		}

		return $created_posts;
	}

	public function getQuery() {
		return '
			query getPosts($first: Int, $after: String, $last: Int, $before: String, $where: RootQueryToPostConnectionWhereArgs ) {
				posts(first: $first, last: $last, before: $before, after: $after, where: $where) {
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
							title
							date
						}
					}
					nodes {
						id
						databaseId
						title
						date
					}
				}
			}
		';
	}

	public function forwardPagination( $graphql_args = [], $query_args = [] ) {
		$query    = $this->getQuery();
		$wp_query = new WP_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge(
			[
				'first' => 2,
			],
			$graphql_args
		);

		// Set the variables to use in the WP query.
		$query_args = array_merge(
			[
				'posts_per_page' => 2,
				'offset'         => 0,
			],
			$query_args
		);

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$page_1   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $page_1 );
		$this->assertEquals( false, $page_1['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $page_1['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['after'] = '';
		$expected           = $page_1;

		$page_1 = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $page_1 );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $page_1['data']['posts']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );

		$page_2 = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $page_2 );
		$this->assertEquals( true, $page_2['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $page_2['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $page_2['data']['posts']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$page_3   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $page_3 );
		$this->assertEquals( true, $page_3['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $page_3['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = array_merge(
			[
				'last' => 2,
			],
			$graphql_args
		);
		unset( $variables['first'] );

		$last_page = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $page_3, $last_page );
	}

	public function backwardPagination( $graphql_args = [], $query_args = [] ) {
		$query    = $this->getQuery();
		$wp_query = new WP_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge(
			[
				'last' => 2,
			],
			$graphql_args
		);

		// Set the variables to use in the WP query.
		$query_args = array_merge(
			[
				'posts_per_page' => 2,
				'offset'         => 0,
				'order'          => 'ASC',
				'orderby'        => 'date',
			],
			$query_args
		);

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['posts']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['posts']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['posts']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the first two results are equal to `first:2`.
		 */
		$variables = array_merge(
			[
				'first' => 2,
			],
			$graphql_args
		);
		unset( $variables['last'] );

		$expected = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );
	}

	public function testForwardPaginationOrderedByDefault() {
		$this->forwardPagination();
	}
	public function testBackwardPaginationOrderedByDefault() {
		$this->backwardPagination();
	}

	public function testForwardPaginationOrderedByTitle() {
		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'first' => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'TITLE',
						'order' => 'DESC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'number'  => 2,
			'offset'  => 0,
			'orderby' => 'title',
			'order'   => 'DESC',
		];

		$this->forwardPagination( $graphql_args, $query_args );
	}

	public function testBackwardPaginationOrderedByTitle() {
		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'last'  => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'TITLE',
						'order' => 'DESC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'number'  => 2,
			'offset'  => 0,
			'orderby' => 'title',
			'order'   => 'ASC',
		];

		$this->backwardPagination( $graphql_args, $query_args );
	}

	public function testForwardPaginationWithSearch() {
		$search_string = 'uniqueString';

		// Create 6 posts all with the $search_string as the title
		$args = [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_author' => $this->admin,
			'post_title'  => $search_string,
		];

		$created_posts = [];

		for ( $i = 1; $i <= 6; $i++ ) {
			$date              = date( 'Y-m-d H:i:s', strtotime( "-1 day -{$i} minutes" ) );
			$args['post_date'] = $date;
			$created_posts[]   = $this->factory()->post->create( $args );
		}

		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'first' => 2,
			'where' => [
				'search' => $search_string,
			],
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'posts_per_page' => 2,
			'page'           => 1,
			's'              => $search_string,
		];

		$this->forwardPagination( $graphql_args, $query_args );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function testBackwardPaginationWithSearch() {
		$search_string = 'uniqueString';

		// Create 6 posts all with the $search_string as the title
		$args = [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_author' => $this->admin,
			'post_title'  => $search_string,
		];

		$created_posts = [];

		for ( $i = 1; $i <= 6; $i++ ) {
			$date              = date( 'Y-m-d H:i:s', strtotime( "-1 day -{$i} minutes" ) );
			$args['post_date'] = $date;
			$created_posts[]   = $this->factory()->post->create( $args );
		}

		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'last'  => 2,
			'where' => [
				'search' => $search_string,
			],
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'number' => 2,
			'offset' => 0,
			's'      => $search_string,
			'order'  => 'ASC',
		];

		$this->backwardPagination( $graphql_args, $query_args );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function testForwardPaginationWithPostIn() {
		$post_ids = $this->created_post_ids;
		shuffle( $post_ids );

		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'first' => 2,
			'where' => [
				'in' => $post_ids,
			],
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'number'   => 2,
			'offset'   => 0,
			'post__in' => $post_ids,
			'orderby'  => 'post__in',
		];

		$this->forwardPagination( $graphql_args, $query_args );
	}

	public function testBackwardPaginationWithPostIn() {
		$post_ids = $this->created_post_ids;
		shuffle( $post_ids );

		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'last'  => 2,
			'where' => [
				'in' => $post_ids,
			],
		];

		// Set the variables to use in the WP query.
		$query_args = [
			'number'   => 2,
			'offset'   => 0,
			'post__in' => array_reverse( $post_ids ),
			'orderby'  => 'post__in',
		];

		$this->backwardPagination( $graphql_args, $query_args );
	}

	public function testForwardPaginationWithDuplicateTitlesAndDates() {
		// Cleanup old posts.
		foreach ( $this->created_post_ids as $id ) {
			wp_delete_post( $id, true );
		}

		// Create duplicate posts
		$date          = date( 'Y-m-d H:i:s', strtotime( 'now' ) );
		$created_posts = [];

		for ( $i = 1; $i <= 6; $i++ ) {
			$created_posts[ $i ] = $this->createPostObject(
				[
					'post_type'   => 'post',
					'post_date'   => $date,
					'post_status' => 'publish',
					'post_title'  => 'a duplicate title',
				]
			);
		}

		// Set the variables to use in the WP query.
		$query_args = [
			'number' => 2,
			'offset' => 0,
		];

		$this->forwardPagination( [], $query_args );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function testBackwardPaginationWithDuplicateTitlesAndDates() {
		// Cleanup old posts.
		foreach ( $this->created_post_ids as $id ) {
			wp_delete_post( $id, true );
		}

		// Create duplicate posts
		$date          = date( 'Y-m-d H:i:s', strtotime( 'now' ) );
		$created_posts = [];

		$created_posts = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$created_posts[ $i ] = $this->createPostObject(
				[
					'post_type'   => 'post',
					'post_date'   => $date,
					'post_status' => 'publish',
					'post_title'  => 'a duplicate title',
				]
			);
		}

		// Set the variables to use in the WP query.
		$query_args = [
			'number' => 2,
			'offset' => 0,
			'order'  => 'ASC',
		];

		$this->backwardPagination( [], $query_args );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Creates posts where search relevance order differs from date order:
	 * title matches are older, content-only matches are newer. WP_Query orders
	 * search results by relevance (title matches first), so a date-based cursor
	 * silently drops the newer content matches.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/1818
	 *
	 * @param string $search_string The term to embed.
	 *
	 * @return int[]
	 */
	public function create_relevance_inversion_posts( $search_string ) {
		$created_posts = [];

		// 3 older posts that match in the TITLE (higher relevance).
		for ( $i = 1; $i <= 3; $i++ ) {
			$created_posts[] = $this->factory()->post->create(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_author'  => $this->admin,
					'post_title'   => "{$search_string} study part {$i}",
					'post_content' => "Older research article number {$i}",
					'post_date'    => date( 'Y-m-d H:i:s', strtotime( "-10 day -{$i} minutes" ) ),
				]
			);
		}

		// 3 newer posts that match only in the CONTENT (lower relevance).
		for ( $i = 1; $i <= 3; $i++ ) {
			$created_posts[] = $this->factory()->post->create(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_author'  => $this->admin,
					'post_title'   => "Recent news {$i}",
					'post_content' => "This article is all about the {$search_string}, item {$i}",
					'post_date'    => date( 'Y-m-d H:i:s', strtotime( "-1 day -{$i} minutes" ) ),
				]
			);
		}

		return $created_posts;
	}

	public function testForwardPaginationWithSearchWhenRelevanceAndDateOrderDiffer() {
		$search_string = 'zebracursor';
		$created_posts = $this->create_relevance_inversion_posts( $search_string );

		// Set the variables to use in the GraphQL query.
		$graphql_args = [
			'first' => 2,
			'where' => [
				'search' => $search_string,
			],
		];

		// Set the variables to use in the WP query. No orderby, so WP_Query
		// applies its native search (relevance) ordering, same as the resolver.
		$query_args = [
			'posts_per_page' => 2,
			'offset'         => 0,
			's'              => $search_string,
		];

		$this->forwardPagination( $graphql_args, $query_args );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function testForwardPaginationWithMultiTermSearchWhenRelevanceAndDateOrderDiffer() {
		$search_string = 'quagga unicorn';
		$created_posts = $this->create_relevance_inversion_posts( $search_string );

		$graphql_args = [
			'first' => 2,
			'where' => [
				'search' => $search_string,
			],
		];

		$query_args = [
			'posts_per_page' => 2,
			'offset'         => 0,
			's'              => $search_string,
		];

		$this->forwardPagination( $graphql_args, $query_args );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function testBackwardPaginationWithSearchWhenRelevanceAndDateOrderDiffer() {
		$search_string = 'zebracursor';
		$created_posts = $this->create_relevance_inversion_posts( $search_string );

		// The full result set in display (relevance) order.
		$forward = ( new WP_Query() )->query(
			[
				'posts_per_page' => -1,
				's'              => $search_string,
				'fields'         => 'ids',
			]
		);

		$this->assertCount( 6, $forward, 'Fixture should produce exactly 6 matching posts' );

		$query = $this->getQuery();

		// last:2 should return the final two results of the display order.
		$variables = [
			'last'  => 2,
			'where' => [
				'search' => $search_string,
			],
		];

		$page_1 = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $page_1 );
		$this->assertSame(
			[ $forward[4], $forward[5] ],
			array_column( $page_1['data']['posts']['nodes'], 'databaseId' ),
			'last:2 should return the final two results of the relevance-ordered set'
		);
		$this->assertEquals( true, $page_1['data']['posts']['pageInfo']['hasPreviousPage'] );

		// Page backward.
		$variables['before'] = $page_1['data']['posts']['pageInfo']['startCursor'];

		$page_2 = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $page_2 );
		$this->assertSame(
			[ $forward[2], $forward[3] ],
			array_column( $page_2['data']['posts']['nodes'], 'databaseId' ),
			'paging backward should return the middle two results'
		);
		$this->assertEquals( true, $page_2['data']['posts']['pageInfo']['hasPreviousPage'] );

		$variables['before'] = $page_2['data']['posts']['pageInfo']['startCursor'];

		$page_3 = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $page_3 );
		$this->assertSame(
			[ $forward[0], $forward[1] ],
			array_column( $page_3['data']['posts']['nodes'], 'databaseId' ),
			'paging backward should end with the first two results'
		);
		$this->assertEquals( false, $page_3['data']['posts']['pageInfo']['hasPreviousPage'] );

		foreach ( $created_posts as $id ) {
			wp_delete_post( $id, true );
		}
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

		$after_cursor  = $actual['data']['posts']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['posts']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['posts']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['posts']['nodes'][0] );

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
		$this->assertSame( $expected, $actual['data']['posts']['nodes'][0] );
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

		$this->assertEquals( 2, count( $actual['data']['posts']['edges'] ) );

		$first_post_id  = $expected[0]->ID;
		$second_post_id = $expected[1]->ID;

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_post_id );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_post_id );

		$this->assertEquals( $first_post_id, $actual['data']['posts']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first_post_id, $actual['data']['posts']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $second_post_id, $actual['data']['posts']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second_post_id, $actual['data']['posts']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['posts']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['posts']['pageInfo']['endCursor'] );
	}
}
