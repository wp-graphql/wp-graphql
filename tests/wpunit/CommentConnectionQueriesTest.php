<?php

use GraphQLRelay\Relay;

class CommentConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $created_comment_ids;
	public $current_date_gmt;
	public $current_date;
	public $current_time;
	public $post_id;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->post_id = $this->factory()->post->create();

		$this->current_time        = strtotime( '- 1 day' );
		$this->current_date        = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt    = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin               = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->created_comment_ids = $this->create_comments();
	}

	public function tearDown(): void {
		foreach ( $this->created_comment_ids as $comment ) {
			wp_delete_comment( $comment, true );
		}
		wp_delete_post( $this->post_id, true );

		// then
		parent::tearDown();
	}

	public function createCommentObject( $args = [] ) {

		$post_id = $this->factory()->post->create([
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Post for commenting...',
			'post_author' => $this->admin,
		]);

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'comment_post_ID'  => $post_id,
			'user_id'          => $this->admin,
			'comment_content'  => 'Test comment content',
			'comment_approved' => 1,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$comment_id = $this->factory()->comment->create( $args );

		/**
		 * Return the $id of the comment_object that was created
		 */
		return $comment_id;
	}

	/**
	 * Creates several comments (with different timestamps) for use in pagination tests.
	 *
	 * @return array
	 */
	public function create_comments() {
		// Create 6 comments
		$created_comments = [];
		for ( $i = 1; $i <= 6; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                   = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_comments[ $i ] = $this->createCommentObject(
				[
					'comment_content' => $i,
					'comment_date'    => $date,
				]
			);
		}

		return $created_comments;
	}

	public function getQuery() {
		return '
			query commentsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToCommentConnectionWhereArgs ){
				comments( first:$first last:$last after:$after before:$before where:$where ) {
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
							content
							date
						}
					}
					nodes {
						databaseId
					}
				}
			}
		';
	}

	public function forwardPagination( $graphql_args = [], $query_args = [] ) {
		$query    = $this->getQuery();
		$wp_query = new WP_Comment_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge( [
			'first' => 2,
		], $graphql_args );

		// Set the variables to use in the WP query.
		$query_args = array_merge( [
			'comment_status' => 'approved',
			'number'         => 2,
			'offset'         => 0,
			'order'          => 'DESC',
			'orderby'        => 'comment_date',
			'comment_parent' => 0,
		], $query_args );

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['comments']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['comments']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$page_3   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $page_3 );
		$this->assertEquals( true, $page_3['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $page_3['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = [
			'last' => 2,
		];

		$last_page = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( true, $last_page['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $last_page['data']['comments']['pageInfo']['hasNextPage'] );

		$this->assertEqualSets( $page_3, $last_page );
	}

	public function backwardPagination( $graphql_args = [], $query_args = [] ) {
		$query    = $this->getQuery();
		$wp_query = new WP_Comment_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge( [
			'last' => 2,
		], $graphql_args );

		// Set the variables to use in the WP query.
		$query_args = array_merge( [
			'comment_status' => 'approved',
			'number'         => 2,
			'offset'         => 0,
			'order'          => 'ASC',
			'orderby'        => 'comment_date',
			'comment_parent' => 0,
		], $query_args );

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['comments']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['comments']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

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

	public function testForwardPagination() {
		$this->forwardPagination();
	}

	public function testBackwardPagination() {
		$this->backwardPagination();
	}

	public function testQueryWithAfterAndBefore() {
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		$variables = [
			'first' => 5,
		];

		/**
		 * Test `first`.
		 */
		$page_1 = $this->graphql( compact( 'query', 'variables' ) );

		$after_cursor  = $page_1['data']['comments']['edges'][1]['cursor'];
		$before_cursor = $page_1['data']['comments']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $page_1['data']['comments']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['comments']['nodes'][0] );

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

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['comments']['nodes'][0] );

	}

	public function testAuthorWhereArgs() {
		$query = $this->getQuery();

		$author_one_id      = $this->factory()->user->create(
			[
				'role'       => 'subscriber',
				'user_email' => 'subscriber@wpgraphql.test',
			]
		);
		$author_two_id      = $this->factory()->user->create(
			[
				'role'       => 'author',
				'user_email' => 'author@wpgraphql.test',
			]
		);
		$author_three_email = 'guest@wpgraphql.test';
		$author_four_url    = 'https://myguestsite.test';

		$comment_ids = [
			$this->createCommentObject( [
				'user_id'              => $author_one_id,
				'comment_author_email' => 'subscriber@wpgraphql.test',
			] ),
			$this->createCommentObject( [
				'user_id'              => $author_two_id,
				'comment_author_email' => 'author@wpgraphql.test',
				'comment_author_url'   => 'https://myguestsite.test',
			] ),
		];

		// test authorEmail.
		$variables = [
			'where' => [
				'authorEmail' => 'subscriber@wpgraphql.test',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// test authorUrl.
		$variables = [
			'where' => [
				'authorUrl' => 'https://myguestsite.test',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// test authorIn with ID + databaseId
		$author_one_global_id = GraphQLRelay\Relay::toGlobalId( 'user', $author_one_id );

		$variables = [
			'where' => [
				'authorIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][1]['databaseId'] );

		// test authorNotIn with ID + databaseId

		$variables = [
			'where' => [
				'authorNotIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );
		$this->assertNotEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertNotEquals( $comment_ids[0], $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testCommentInWhereArgs() {
		$query = $this->getQuery();

		$comment_one_global_id = Relay::toGlobalId( 'comment', $this->created_comment_ids[1] );

		// Test commentIn with global + db IDs.
		$variables = [
			'where' => [
				'commentIn' => [ $comment_one_global_id, $this->created_comment_ids[2] ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $this->created_comment_ids[1], $actual['data']['comments']['nodes'][1]['databaseId'] );
		$this->assertEquals( $this->created_comment_ids[2], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// Test commentNotIn with global + db IDs

		$variables = [
			'where' => [
				'commentNotIn' => [ $comment_one_global_id, $this->created_comment_ids[2] ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 4, $actual['data']['comments']['nodes'] );
		$this->assertNotEquals( $this->created_comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertNotEquals( $this->created_comment_ids[2], $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testCommentTypeWhereArgs() {
		$query = $this->getQuery();

		$comment_type_one = 'custom-type-one';
		$comment_type_two = 'custom-type-two';
		$comment_ids      = [
			$this->createCommentObject( [ 'comment_type' => $comment_type_one ] ),
			$this->createCommentObject( [ 'comment_type' => $comment_type_two ] ),
		];

		// test commentType
		$variables = [
			'where' => [
				'commentType' => $comment_type_one,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// test commentTypeIn
		$variables = [
			'where' => [
				'commentTypeIn' => [ $comment_type_one, $comment_type_two ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][1]['databaseId'] );

		// test commentTypeNotIn
		$variables = [
			'where' => [
				'commentTypeNotIn' => 'comment',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][1]['databaseId'] );
	}

	public function testContentWhereArgs() {
		$query = $this->getQuery();

		$author_one_id = $this->factory->user->create( [ 'role' => 'author' ] );
		$author_two_id = $this->factory->user->create( [ 'role' => 'author' ] );

		$post_one_id = $this->factory->post->create( [
			'post_author' => $author_one_id,
			'post_type' => 'post',
		] );

		$post_two_id = $this->factory->post->create( [
			'post_author' => $author_two_id,
			'post_type' => 'page',
			'post_title' => 'Page for testing content where args',
			''
		] );

		$comment_one_id = $this->createCommentObject( [
			'comment_post_ID' => $post_one_id,
			'comment_approved' => true,
		] );

		$comment_two_id = $this->createCommentObject( [
			'comment_post_ID' => $post_two_id,
			'comment_approved' => true,
		] );

		$author_one_global_id = Relay::toGlobalId( 'user', $author_one_id );

		// Test contentAuthorIn with global + db IDs.
		$variables = [
			'where' => [
				'contentAuthorIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		// Test contentAuthorNotIn with global + db IDs.
		$variables = [
			'where' => [
				'contentAuthorNotIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// Test contentId with databaseId
		$variables = [
			'where' => [
				'contentId' => $post_one_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );


		// Test contentId with global Id
		$expected = $actual['data']['comments']['nodes'];

		$post_one_global_id = Relay::toGlobalId( 'post', $post_one_id );

		$variables = [
			'where' => [
				'contentId' => $post_one_global_id
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEqualSets( $expected, $actual['data']['comments']['nodes'] );

		// Test contentIdIn with global + db IDs.
		$variables = [
			'where' => [
				'contentIdIn' => [ $post_one_global_id, $post_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		// Test contentIdNotIn with global + db IDs.
		$variables = [
			'where' => [
				'contentIdNotIn' => [ $post_one_global_id, $post_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// Test contentName
		$post_two = get_post( $post_two_id );
		$variables = [
			'where' => [
				'contentName' => $post_two->post_name,
			],
		];
		
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );

		// Test contentType
		$variables = [
			'where' => [
				'contentType' => 'PAGE',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
	}

	public function testContentStatusWhereArgs() {
		$query = $this->getQuery();

		$post_id = $this->factory->post->create(
			[
				'post_status' => 'pending',
			]
		);

		$comment_id = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_approved' => true,
		] );

		$variables = [
			'where' => [
				'contentStatus' => 'PENDING',
			],
		];

		// Test logged out user.

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );

		// Test logged in user.
		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
	}

	public function testContentParentWhereArgs() {
		$query = $this->getQuery();

		$parent_post_id = $this->factory->post->create(
			[
				'post_status' => 'publish',
			]
		);

		$child_post_id_one = $this->factory->post->create(
			[
				'post_status' => 'publish',
				'post_parent' => $parent_post_id,
			]
		);

		$child_post_id_two = $this->factory->post->create(
			[
				'post_status' => 'publish',
				'post_parent' => $parent_post_id,
			]
		);

		$comment_one_id = $this->createCommentObject( [
			'comment_post_ID' => $child_post_id_one,
			'comment_approved' => true,
		] );

		$comment_id_two = $this->createCommentObject( [
			'comment_post_ID' => $child_post_id_two,
			'comment_approved' => true,
		] );

		// Test contentParent with global Id
		$variables = [
			'where' => [
				'contentParent' =>  $parent_post_id
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
	}

	public function testIncludeUnapprovedWhereArgs() {
		$query = $this->getQuery();

		$author_id      = $this->factory()->user->create(
			[
				'role'       => 'subscriber',
				'user_email' => 'subscriber@wpgraphql.test',
			]
		);

		$comment_ids = [
			$this->createCommentObject( [
				'user_id' => $this->admin,
				'comment_approved' => 0,
			] ),
			$this->createCommentObject( [
				'user_id' => $author_id,
				'comment_approved' => 0,
			] ),
		];

		// Test unapproved comments are excluded by default.
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// test includeUnapproved with global + db author ids.

		$author_global_id = Relay::toGlobalId( 'user', $author_id );

		$variables = [
			'where' => [
				'includeUnapproved' => [ $this->admin, $author_global_id ]
			],
		];

		// While user doesn't have moderate permissions, only the approved comments will be returned.

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// test user with permissions

		wp_set_current_user( $this->admin );
		
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 8, $actual['data']['comments']['nodes'] );
	}

	public function testOrderWhereArgs() {
		$query = $this->getQuery();

		// Test ascending by COMMENT_ID.
		$variables = [
			'where' => [
				'order' => 'ASC',
				'orderby' => 'COMMENT_ID'
			]
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );

		$this->assertCount( 6, $actual['data']['comments']['nodes'] );
		$this->assertGreaterThan( $actual['data']['comments']['nodes'][0]['databaseId'], $actual['data']['comments']['nodes'][1]['databaseId'] );

		// Test descending.
		$expected = array_reverse( $actual['data']['comments']['nodes'] );
		$variables['where']['order'] = 'DESC';

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );
		$this->assertEqualSets( $expected, $actual['data']['comments']['nodes'] );
	}



	public function testParentWhereArgs() {
		$query = $this->getQuery();

		$comment_one_id = $this->createCommentObject( [
			'comment_post_ID' => $this->post_id,
			'comment_parent'  => $this->created_comment_ids[1],
		] );

		$comment_two_id = $this->createCommentObject( [
			'comment_post_ID' => $this->post_id,
			'comment_parent'  => $this->created_comment_ids[2],
		] );

		// Test parent
		$variables = [
			'where' => [
				'parent' => $this->created_comment_ids[1],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_one_id, $actual['data']['comments']['nodes'][0]['databaseId'] );

		$parent_one_global_id = Relay::toGlobalId( 'comment', $this->created_comment_ids[1] );

		// Test parentIn with global + database Ids
		$variables = [
			'where' => [
				'parentIn' => [
					$parent_one_global_id,
					$this->created_comment_ids[2],
				],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		// Test parentNotIn with global + database Ids
		$variables = [
			'where' => [
				'parentNotIn' => [
					$parent_one_global_id,
					$this->created_comment_ids[2],
				],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );
	}

	public function testSearchWhereArgs() {
		$query = $this->getQuery();

		$comment_one_id = $this->createCommentObject( [
			'comment_post_ID' => $this->post_id,
			'comment_content' => 'This is a comment with a search term',
		] );

		$variables = [
			'where' => [
				'search' => 'search term',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_one_id, $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testStatusWhereArgs() {
		$query = $this->getQuery();

		$comment_one_id = $this->createCommentObject( [
			'comment_post_ID' => $this->post_id,
			'comment_approved' => 0,
		] );

		$variables = [
			'where' => [
				'status' => 'hold',
			],
		];

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_one_id, $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testUserIdWhereArgs() {
		$query = $this->getQuery();

		$user_id = $this->factory()->user->create( [
			'role' => 'author',
		] );

		$comment_id = $this->createCommentObject( [
			'comment_post_ID' => $this->post_id,
			'user_id' => $user_id,
		] );

		// Test by database ID
		$variables = [
			'where' => [
				'userId' => $user_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_id, $actual['data']['comments']['nodes'][0]['databaseId'] );

		// Test by global ID
		$comment_global_id = Relay::toGlobalId( 'comment', $comment_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_id, $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	/**
	 * Common asserts for testing pagination.
	 *
	 * @param array $expected An array of the results from WordPress. When testing backwards pagination, the order of this array should be reversed.
	 * @param array $actual The GraphQL results.
	 */
	public function assertValidPagination( $expected, $actual ) {
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 2, count( $actual['data']['comments']['edges'] ) );

		$first_comment  = $expected[0];
		$second_comment = $expected[1];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_comment->comment_ID );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_comment->comment_ID );

		$this->assertEquals( $first_comment->comment_ID, $actual['data']['comments']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first_comment->comment_ID, $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['comments']['edges'][0]['cursor'] );
		$this->assertEquals( $second_comment->comment_ID, $actual['data']['comments']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second_comment->comment_ID, $actual['data']['comments']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['comments']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['comments']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['comments']['pageInfo']['endCursor'] );
	}

}
