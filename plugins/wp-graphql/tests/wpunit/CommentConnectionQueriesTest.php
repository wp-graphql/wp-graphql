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

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Post for commenting...',
				'post_author' => $this->admin,
			]
		);

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
		return $this->factory()->comment->create( $args );
	}

	/**
	 * Creates several comments (with different timestamps) for use in pagination tests.
	 *
	 * @return array
	 */
	public function create_comments() {
		// Create 6 comments
		$created_comments = [];
		for ( $i = 1; $i <= 6; $i++ ) {
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
		$variables = array_merge(
			[
				'first' => 2,
			],
			$graphql_args
		);

		// Set the variables to use in the WP query.
		$query_args = array_merge(
			[
				'comment_status' => 'approved',
				'number'         => 2,
				'offset'         => 0,
				'order'          => 'DESC',
				'orderby'        => 'comment_date',
				'comment_parent' => 0,
			],
			$query_args
		);

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
		$variables = array_merge(
			[
				'last' => 2,
			],
			$graphql_args
		);

		// Set the variables to use in the WP query.
		$query_args = array_merge(
			[
				'comment_status' => 'approved',
				'number'         => 2,
				'offset'         => 0,
				'order'          => 'ASC',
				'orderby'        => 'comment_date',
				'comment_parent' => 0,
			],
			$query_args
		);

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

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
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
			$this->createCommentObject(
				[
					'user_id'              => $author_one_id,
					'comment_author_email' => 'subscriber@wpgraphql.test',
				]
			),
			$this->createCommentObject(
				[
					'user_id'              => $author_two_id,
					'comment_author_email' => 'author@wpgraphql.test',
					'comment_author_url'   => 'https://myguestsite.test',
				]
			),
		];

		wp_set_current_user( $this->admin );
		// test authorEmail.
		$variables = [
			'where' => [
				'authorEmail' => 'subscriber@wpgraphql.test',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// test authorUrl.
		$variables = [
			'where' => [
				'authorUrl' => 'https://myguestsite.test',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// test commentTypeIn
		$variables = [
			'where' => [
				'commentTypeIn' => [ $comment_type_one, $comment_type_two ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][1]['databaseId'] );
	}

	public function testContentWhereArgs() {
		$query = $this->getQuery();

		$author_one_id = $this->factory->user->create( [ 'role' => 'author' ] );
		$author_two_id = $this->factory->user->create( [ 'role' => 'author' ] );

		$post_one_id = $this->factory->post->create(
			[
				'post_author' => $author_one_id,
				'post_type'   => 'post',
			]
		);

		$post_two_id = $this->factory->post->create(
			[
				'post_author' => $author_two_id,
				'post_type'   => 'page',
				'post_title'  => 'Page for testing content where args',
			]
		);

		$comment_one_id = $this->createCommentObject(
			[
				'comment_post_ID'  => $post_one_id,
				'comment_approved' => true,
			]
		);

		$comment_two_id = $this->createCommentObject(
			[
				'comment_post_ID'  => $post_two_id,
				'comment_approved' => true,
			]
		);

		$author_one_global_id = Relay::toGlobalId( 'user', $author_one_id );

		// Test contentAuthorIn with global + db IDs.
		$variables = [
			'where' => [
				'contentAuthorIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		// Test contentAuthorNotIn with global + db IDs.
		$variables = [
			'where' => [
				'contentAuthorNotIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// Test contentId with databaseId
		$variables = [
			'where' => [
				'contentId' => $post_one_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );

		// Test contentId with global Id
		$expected = $actual['data']['comments']['nodes'];

		$post_one_global_id = Relay::toGlobalId( 'post', $post_one_id );

		$variables = [
			'where' => [
				'contentId' => $post_one_global_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		// Test contentIdNotIn with global + db IDs.
		$variables = [
			'where' => [
				'contentIdNotIn' => [ $post_one_global_id, $post_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// Test contentName
		$post_two  = get_post( $post_two_id );
		$variables = [
			'where' => [
				'contentName' => $post_two->post_name,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );

		// Test contentType
		$variables = [
			'where' => [
				'contentType' => 'PAGE',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
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

		$comment_id = $this->createCommentObject(
			[
				'comment_post_ID'  => $post_id,
				'comment_approved' => true,
			]
		);

		$variables = [
			'where' => [
				'contentStatus' => 'PENDING',
			],
		];

		// Test logged out user.

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );

		// Test logged in user.
		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
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

		$comment_one_id = $this->createCommentObject(
			[
				'comment_post_ID'  => $child_post_id_one,
				'comment_approved' => true,
			]
		);

		$comment_id_two = $this->createCommentObject(
			[
				'comment_post_ID'  => $child_post_id_two,
				'comment_approved' => true,
			]
		);

		// Test contentParent with global Id
		$variables = [
			'where' => [
				'contentParent' => $parent_post_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
	}

	public function testIncludeUnapprovedWhereArgs() {
		$query = $this->getQuery();

		$author_id = $this->factory()->user->create(
			[
				'role'       => 'subscriber',
				'user_email' => 'subscriber@wpgraphql.test',
			]
		);

		$comment_ids = [
			$this->createCommentObject(
				[
					'user_id'          => $this->admin,
					'comment_approved' => 0,
				]
			),
			$this->createCommentObject(
				[
					'user_id'          => $author_id,
					'comment_approved' => 0,
				]
			),
		];

		// Test unapproved comments are excluded by default.
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// test includeUnapproved with global + db author ids.

		$author_global_id = Relay::toGlobalId( 'user', $author_id );

		$variables = [
			'where' => [
				'includeUnapproved' => [ $this->admin, $author_global_id ],
			],
		];

		// While user doesn't have moderate permissions, only the approved comments will be returned.

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// test user with permissions

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertCount( 8, $actual['data']['comments']['nodes'] );
	}

	public function testOrderWhereArgs() {
		$query = $this->getQuery();

		// Test ascending by COMMENT_ID.
		$variables = [
			'where' => [
				'order'   => 'ASC',
				'orderby' => 'COMMENT_ID',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );

		$this->assertCount( 6, $actual['data']['comments']['nodes'] );
		$this->assertGreaterThan( $actual['data']['comments']['nodes'][0]['databaseId'], $actual['data']['comments']['nodes'][1]['databaseId'] );

		// Test descending.
		$expected                    = array_reverse( $actual['data']['comments']['nodes'] );
		$variables['where']['order'] = 'DESC';

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );
		$this->assertEqualSets( $expected, $actual['data']['comments']['nodes'] );
	}



	/**
	 * Paginating a comment connection ordered by COMMENT_PARENT past the first
	 * page must not throw an InvariantViolation.
	 *
	 * When all comments share the same comment_parent (the common case of
	 * top-level comments, where comment_parent is 0), the orderby comparison
	 * adds no field and the cursor falls back to comparing by comment_date. A
	 * malformed fallback field (using the "by" key instead of "value") caused
	 * the cursor compare validator to throw on the second page.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3068
	 */
	public function testCommentParentOrderbyPaginationDoesNotThrow() {
		$query = $this->getQuery();

		// First page, ordered by COMMENT_PARENT (all top-level => comment_parent 0).
		$variables = [
			'first' => 2,
			'where' => [
				'order'   => 'ASC',
				'orderby' => 'COMMENT_PARENT',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertResponseIsValid( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		$first_page_ids = wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' );
		$end_cursor     = $actual['data']['comments']['pageInfo']['endCursor'];

		// Second page via the cursor. This is the path that previously threw.
		$variables['after'] = $end_cursor;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertResponseIsValid( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );

		// The second page must not repeat nodes from the first page.
		$second_page_ids = wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' );
		$this->assertEmpty( array_intersect( $first_page_ids, $second_page_ids ) );
	}

	public function testParentWhereArgs() {
		$query = $this->getQuery();

		$comment_one_id = $this->createCommentObject(
			[
				'comment_post_ID' => $this->post_id,
				'comment_parent'  => $this->created_comment_ids[1],
			]
		);

		$comment_two_id = $this->createCommentObject(
			[
				'comment_post_ID' => $this->post_id,
				'comment_parent'  => $this->created_comment_ids[2],
			]
		);

		// Test parent
		$variables = [
			'where' => [
				'parent' => $this->created_comment_ids[1],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
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

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );
	}

	public function testSearchWhereArgs() {
		$query = $this->getQuery();

		$comment_one_id = $this->createCommentObject(
			[
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'This is a comment with a search term',
			]
		);

		$variables = [
			'where' => [
				'search' => 'search term',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_one_id, $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testStatusWhereArgs() {
		$query = $this->getQuery();

		$comment_one_id = $this->createCommentObject(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 0,
			]
		);

		$variables = [
			'where' => [
				'status' => 'hold',
			],
		];

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_one_id, $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testUserIdWhereArgs() {
		$query = $this->getQuery();

		$user_id = $this->factory()->user->create(
			[
				'role' => 'author',
			]
		);

		$comment_id = $this->createCommentObject(
			[
				'comment_post_ID' => $this->post_id,
				'user_id'         => $user_id,
			]
		);

		// Test by database ID
		$variables = [
			'where' => [
				'userId' => $user_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_id, $actual['data']['comments']['nodes'][0]['databaseId'] );

		// Test by global ID
		$comment_global_id = Relay::toGlobalId( 'comment', $comment_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_id, $actual['data']['comments']['nodes'][0]['databaseId'] );
	}

	public function testStatusFilterOnlyMatchesApprovedCommentsForNonModerators() {
		$query = $this->getQuery();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->createCommentObject( [ 'comment_approved' => '0' ] );
		}

		$variables = [
			'first' => 1,
			'where' => [
				'statusIn' => [ 'HOLD' ],
			],
		];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertTrue( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		// Asking for a viewable status alongside a hidden one still returns the viewable comments.
		$variables = [
			'first' => 20,
			'where' => [
				'statusIn' => [ 'APPROVE', 'HOLD' ],
			],
		];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );
		foreach ( $actual['data']['comments']['nodes'] as $node ) {
			$this->assertContains( $node['databaseId'], $this->created_comment_ids );
		}
	}

	public function testDeprecatedStatusFilterOnlyMatchesApprovedCommentsForNonModerators() {
		$query = $this->getQuery();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->createCommentObject( [ 'comment_approved' => '0' ] );
		}

		$variables = [
			'first' => 1,
			'where' => [
				'status' => 'hold',
			],
		];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		// "all" can only match approved comments for this viewer.
		$variables = [
			'first' => 20,
			'where' => [
				'status' => 'all',
			],
		];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 6, $actual['data']['comments']['nodes'] );

		// An empty status is no filter at all, so it gets the approved comments rather than nothing.
		foreach ( [ [ 'status' => '' ], [ 'statusIn' => [] ] ] as $where ) {
			$variables = [
				'first' => 20,
				'where' => $where,
			];

			wp_set_current_user( 0 );
			$actual = $this->graphql( compact( 'query', 'variables' ) );

			$this->assertArrayNotHasKey( 'errors', $actual );
			$this->assertCount( 6, $actual['data']['comments']['nodes'] );
			$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );
		}
	}

	public function testContentStatusFilterIgnoresNonPublicStatusesForNonModerators() {
		$query = $this->getQuery();

		$draft_id          = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$draft_comment_ids = [];
		for ( $i = 0; $i < 2; $i++ ) {
			$draft_comment_ids[] = $this->createCommentObject( [ 'comment_post_ID' => $draft_id ] );
		}

		$variables = [
			'first' => 1,
			'where' => [
				'contentStatus' => [ 'DRAFT' ],
			],
		];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertTrue( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		$variables = [
			'first' => 20,
			'where' => [
				'contentStatus' => [ 'PUBLISH', 'DRAFT' ],
			],
		];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['comments']['nodes'] );
		foreach ( $actual['data']['comments']['nodes'] as $node ) {
			$this->assertNotContains( $node['databaseId'], $draft_comment_ids );
		}
	}

	public function testCommentsOnHiddenContentAreNotCountedForNonModerators() {
		$query = $this->getQuery();

		$draft_id          = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$draft_comment_ids = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$draft_comment_ids[] = $this->createCommentObject( [ 'comment_post_ID' => $draft_id ] );
		}

		// Delete the comments setUp() made, so the only approved comments are the ones on the draft.
		foreach ( $this->created_comment_ids as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}
		$this->created_comment_ids = [];

		$cases = [
			'no filter'      => null,
			'content id'     => [ 'contentId' => $draft_id ],
			'empty statuses' => [ 'contentStatus' => [] ],
		];

		foreach ( $cases as $label => $where ) {
			$variables = [
				'first' => 1,
				'where' => $where,
			];

			wp_set_current_user( 0 );
			$actual = $this->graphql( compact( 'query', 'variables' ) );

			$this->assertArrayNotHasKey( 'errors', $actual, $label );
			$this->assertEmpty( $actual['data']['comments']['nodes'], $label );
			$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'], $label );

			wp_set_current_user( $this->admin );
			$actual = $this->graphql( compact( 'query', 'variables' ) );

			$this->assertArrayNotHasKey( 'errors', $actual, $label );
			$this->assertCount( 1, $actual['data']['comments']['nodes'], $label );
			$this->assertContains( $actual['data']['comments']['nodes'][0]['databaseId'], $draft_comment_ids, $label );
			$this->assertTrue( $actual['data']['comments']['pageInfo']['hasNextPage'], $label );
		}

		// Comments on published content are still returned without a filter.
		$published_comment_id = $this->createCommentObject();

		wp_set_current_user( 0 );
		$variables = [ 'first' => 10 ];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [ [ 'databaseId' => $published_comment_id ] ], $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );
	}

	public function testMediaCommentsFollowTheContentTheMediaIsAttachedTo() {
		// Build a fresh schema, so the MediaItem comments connection is registered.
		$this->clearSchema();

		$query = $this->getQuery();

		$published_id = $this->factory()->post->create();
		$draft_id     = $this->factory()->post->create( [ 'post_status' => 'draft' ] );

		$attachment = function ( $parent_id ) {
			return $this->factory()->attachment->create_object(
				[
					'file'           => 'image.jpg',
					'post_parent'    => $parent_id,
					'post_mime_type' => 'image/jpeg',
				]
			);
		};

		$on_published = $attachment( $published_id );
		$on_draft     = $attachment( $draft_id );
		$unattached   = $attachment( 0 );

		$comment_ids = [];
		foreach ( [ $on_published, $on_draft, $unattached ] as $media_id ) {
			for ( $i = 0; $i < 2; $i++ ) {
				$comment_ids[ $media_id ][] = $this->createCommentObject( [ 'comment_post_ID' => $media_id ] );
			}
		}

		$database_ids = static function ( $actual ) {
			return wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' );
		};

		wp_set_current_user( 0 );

		// The comments of a media item.
		$media_query = '
			query MediaComments( $id: ID! ) {
				mediaItem( id: $id, idType: DATABASE_ID ) {
					comments( first: 10 ) {
						nodes {
							databaseId
						}
					}
				}
			}
		';

		foreach ( [ $on_published, $unattached ] as $media_id ) {
			$actual = $this->graphql(
				[
					'query'     => $media_query,
					'variables' => [ 'id' => $media_id ],
				]
			);

			$this->assertArrayNotHasKey( 'errors', $actual );
			$this->assertEqualSets( $comment_ids[ $media_id ], wp_list_pluck( $actual['data']['mediaItem']['comments']['nodes'], 'databaseId' ) );
		}

		// Comments filtered to a media item follow the content it is attached to.
		foreach ( [ $on_published, $unattached ] as $media_id ) {
			$variables = [ 'where' => [ 'contentId' => $media_id ] ];
			$actual    = $this->graphql( compact( 'query', 'variables' ) );

			$this->assertArrayNotHasKey( 'errors', $actual );
			$this->assertEqualSets( $comment_ids[ $media_id ], $database_ids( $actual ) );
		}

		$variables = [
			'first' => 1,
			'where' => [ 'contentId' => $on_draft ],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		$variables = [
			'first' => 10,
			'where' => [ 'contentIdIn' => [ $on_published, $on_draft ] ],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEqualSets( $comment_ids[ $on_published ], $database_ids( $actual ) );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		// Without a filter, only comments on content with a public status are listed.
		$variables = [ 'first' => 20 ];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEqualSets( $this->created_comment_ids, $database_ids( $actual ) );

		// Moderators get every comment.
		wp_set_current_user( $this->admin );

		$variables = [
			'first' => 10,
			'where' => [ 'contentId' => $on_draft ],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEqualSets( $comment_ids[ $on_draft ], $database_ids( $actual ) );
	}

	public function testRepliesAreLimitedToTheContentOfTheirParentComment() {
		$published_id = $this->factory()->post->create();
		$draft_id     = $this->factory()->post->create( [ 'post_status' => 'draft' ] );

		$parent_id = $this->createCommentObject( [ 'comment_post_ID' => $published_id ] );
		$reply_id  = $this->createCommentObject(
			[
				'comment_post_ID' => $published_id,
				'comment_parent'  => $parent_id,
			]
		);

		// Replies stored on other content than their parent comment.
		$stray_reply_ids = [];
		for ( $i = 0; $i < 2; $i++ ) {
			$stray_reply_ids[] = $this->createCommentObject(
				[
					'comment_post_ID' => $draft_id,
					'comment_parent'  => $parent_id,
				]
			);
		}

		$query = '
			query Replies( $id: ID! ) {
				comment( id: $id, idType: DATABASE_ID ) {
					replies( first: 1 ) {
						nodes {
							databaseId
						}
						pageInfo {
							hasNextPage
						}
					}
				}
			}
		';

		$variables = [ 'id' => $parent_id ];

		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [ [ 'databaseId' => $reply_id ] ], $actual['data']['comment']['replies']['nodes'] );
		$this->assertFalse( $actual['data']['comment']['replies']['pageInfo']['hasNextPage'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['comment']['replies']['nodes'] );
		$this->assertTrue( $actual['data']['comment']['replies']['pageInfo']['hasNextPage'] );
	}

	public function testAuthorEmailFilterMatchesNothingForNonModerators() {
		$query = $this->getQuery();

		$comment_id = $this->createCommentObject( [ 'comment_author_email' => 'private@example.test' ] );
		$subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		$variables = [
			'where' => [
				'authorEmail' => 'private@example.test',
			],
		];

		wp_set_current_user( 0 );
		$matching = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $matching );
		$this->assertEmpty( $matching['data']['comments']['nodes'] );
		$this->assertFalse( $matching['data']['comments']['pageInfo']['hasNextPage'] );

		wp_set_current_user( $subscriber );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );

		// A matching and a non-matching address get the same response.
		$variables = [
			'where' => [
				'authorEmail' => 'nobody@example.test',
			],
		];

		wp_set_current_user( 0 );
		$not_matching = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $not_matching );
		$this->assertSame( $not_matching['data'], $matching['data'] );

		wp_set_current_user( $this->admin );
		$variables = [
			'where' => [
				'authorEmail' => 'private@example.test',
			],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

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
		$this->assertResponseIsValid( $actual );
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

	/**
	 * Runs a query as the given user and returns the response, asserting it has no errors.
	 *
	 * @param int    $user_id   The user to run the query as, 0 for an anonymous request.
	 * @param string $query     The query.
	 * @param array  $variables The query variables.
	 */
	private function query_as( int $user_id, string $query, array $variables = [] ): array {
		wp_set_current_user( $user_id );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual, isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' );

		return $actual;
	}

	/**
	 * For users who can't moderate comments, `search` only matches the fields they can read: the
	 * author's name and URL, and the comment's content.
	 */
	public function testSearchOnlyMatchesVisibleFieldsForNonModerators() {
		$comment_id = $this->createCommentObject(
			[
				'user_id'              => 0,
				'comment_author'       => 'Searchable Author',
				'comment_author_email' => 'searchable@example.test',
				'comment_author_url'   => 'https://searchable.example.test',
				'comment_author_IP'    => '198.51.100.23',
				'comment_agent'        => 'SearchableAgent/1.0',
				'comment_content'      => 'A distinctive phrase',
			]
		);
		$subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		$query = 'query ( $search: String ) { comments( where: { search: $search } ) { nodes { databaseId } } }';

		$visible = [ 'Searchable Author', 'searchable.example.test', 'distinctive phrase' ];
		$hidden  = [ 'searchable@example.test', 'searchable@', '198.51.100.23', 'SearchableAgent' ];

		foreach ( [ 0, $subscriber ] as $user_id ) {
			foreach ( $visible as $term ) {
				$actual = $this->query_as( $user_id, $query, [ 'search' => $term ] );
				$this->assertSame( [ $comment_id ], wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' ), $term );
			}

			foreach ( $hidden as $term ) {
				$actual = $this->query_as( $user_id, $query, [ 'search' => $term ] );
				$this->assertSame( [], $actual['data']['comments']['nodes'], $term );
			}
		}

		// Moderators search every field, as before.
		$actual = $this->query_as( $this->admin, $query, [ 'search' => 'searchable@example.test' ] );
		$this->assertSame( [ $comment_id ], wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' ) );
	}

	/**
	 * For users who can't moderate comments, ordering by a field only moderators can read falls
	 * back to the comment date.
	 */
	public function testOrderingByHiddenFieldsFallsBackToDateForNonModerators() {
		$post_id = $this->factory()->post->create( [ 'post_status' => 'publish' ] );

		// Ordered by date: $first, $second. Ordered by any of the hidden fields: $second, $first.
		$first  = $this->factory()->comment->create(
			[
				'comment_post_ID'      => $post_id,
				'comment_approved'     => '1',
				'comment_date'         => '2020-01-01 00:00:00',
				'comment_author_email' => 'zz@example.test',
				'comment_author_IP'    => '203.0.113.200',
				'comment_agent'        => 'Zz',
			]
		);
		$second = $this->factory()->comment->create(
			[
				'comment_post_ID'      => $post_id,
				'comment_approved'     => '1',
				'comment_date'         => '2020-01-02 00:00:00',
				'comment_author_email' => 'aa@example.test',
				'comment_author_IP'    => '203.0.113.100',
				'comment_agent'        => 'Aa',
			]
		);

		$query = 'query ( $id: ID, $orderby: CommentsConnectionOrderbyEnum ) { comments( where: { contentId: $id, orderby: $orderby, order: ASC } ) { nodes { databaseId } } }';

		foreach ( [ 'COMMENT_AUTHOR_EMAIL', 'COMMENT_AUTHOR_IP', 'COMMENT_AGENT' ] as $orderby ) {
			$variables = [
				'id'      => $post_id,
				'orderby' => $orderby,
			];

			$actual = $this->query_as( 0, $query, $variables );
			$this->assertSame( [ $first, $second ], wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' ), $orderby );

			$actual = $this->query_as( $this->admin, $query, $variables );
			$this->assertSame( [ $second, $first ], wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' ), $orderby . ' as a moderator' );
		}
	}

	/**
	 * A cursor that points at a comment the user can't see is treated like a cursor that points at
	 * no comment at all.
	 */
	public function testCursorOnACommentTheUserCannotSeeIsIgnored() {
		$hidden = $this->createCommentObject( [ 'comment_approved' => 'spam' ] );

		$query = 'query ( $after: String, $before: String ) { comments( first: 2, after: $after, before: $before ) { pageInfo { hasNextPage hasPreviousPage } nodes { databaseId } } }';

		foreach ( [ 'after', 'before' ] as $direction ) {
			$hidden_cursor  = $this->query_as( 0, $query, [ $direction => base64_encode( 'arrayconnection:' . $hidden ) ] );
			$missing_cursor = $this->query_as( 0, $query, [ $direction => base64_encode( 'arrayconnection:999999' ) ] );

			$this->assertSame( $missing_cursor['data'], $hidden_cursor['data'], $direction );
		}
	}

	/**
	 * Comments on published content of a post type the user can't see aren't queried, so they
	 * aren't counted.
	 */
	public function testCommentsOnNonPublicContentTypesAreNotCountedForNonModerators() {
		// Reusable blocks are a post type that's neither public nor publicly queryable.
		$block_id = $this->factory()->post->create(
			[
				'post_type'   => 'wp_block',
				'post_status' => 'publish',
			]
		);
		$this->factory()->comment->create(
			[
				'comment_post_ID'  => $block_id,
				'comment_approved' => '1',
			]
		);

		$query = 'query ( $first: Int ) { comments( first: $first ) { pageInfo { hasNextPage } nodes { databaseId } } }';

		$actual = $this->query_as( 0, $query, [ 'first' => count( $this->created_comment_ids ) ] );

		$this->assertCount( count( $this->created_comment_ids ), $actual['data']['comments']['nodes'] );
		$this->assertFalse( $actual['data']['comments']['pageInfo']['hasNextPage'] );
	}

	/**
	 * Blocking the `authorEmail` filter doesn't depend on the query being skipped: it matches no
	 * comments even when a filter forces the query to run.
	 */
	public function testAuthorEmailFilterMatchesNothingEvenWhenTheQueryIsForcedToRun() {
		$this->createCommentObject( [ 'comment_author_email' => 'forced@example.test' ] );

		$force = static function () {
			return true;
		};
		add_filter( 'graphql_connection_should_execute', $force );

		$actual = $this->query_as( 0, 'query { comments( where: { authorEmail: "forced@example.test" } ) { nodes { databaseId } } }' );

		remove_filter( 'graphql_connection_should_execute', $force );

		$this->assertSame( [], $actual['data']['comments']['nodes'] );
	}

	/**
	 * Editorial notes are only visible to users who can edit them, in lists and by ID.
	 */
	public function testNotesAreOnlyVisibleToUsersWhoCanEditThem() {
		// This file doesn't reset the schema between tests, and the content and user comment fields need a fresh one.
		$this->clearSchema();

		$post_id = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);
		$note_id = $this->factory()->comment->create(
			[
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => '1',
				'comment_content'  => 'An editorial note',
				'user_id'          => $this->admin,
			]
		);

		$subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		$author     = $this->factory()->user->create( [ 'role' => 'author' ] );

		$list_queries = [
			'commentType'   => 'query { comments( where: { commentType: "note" } ) { nodes { databaseId } } }',
			'commentTypeIn' => 'query { comments( where: { commentTypeIn: [ "note" ] } ) { nodes { databaseId } } }',
			'all types'     => 'query { comments( first: 100, where: { commentType: "all" } ) { nodes { databaseId } } }',
		];

		$content_query = 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { comments( where: { commentType: "note" } ) { nodes { databaseId } } } }';
		$single_query  = 'query ( $id: ID! ) { comment( id: $id, idType: DATABASE_ID ) { databaseId } }';

		foreach ( [ 'anonymous' => 0, 'subscriber' => $subscriber, 'author' => $author ] as $label => $user_id ) {
			foreach ( $list_queries as $name => $query ) {
				$actual = $this->query_as( $user_id, $query );
				$this->assertNotContains( $note_id, wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' ), $label . ': ' . $name );
			}

			$actual = $this->query_as( $user_id, $content_query, [ 'id' => $post_id ] );
			$this->assertSame( [], $actual['data']['post']['comments']['nodes'], $label . ': content' );

			$actual = $this->query_as( $user_id, $single_query, [ 'id' => $note_id ] );
			$this->assertNull( $actual['data']['comment'], $label . ': by ID' );
		}

		// Users who can edit the note see it.
		foreach ( $list_queries as $name => $query ) {
			$actual = $this->query_as( $this->admin, $query );
			$this->assertContains( $note_id, wp_list_pluck( $actual['data']['comments']['nodes'], 'databaseId' ), 'moderator: ' . $name );
		}

		$actual = $this->query_as( $this->admin, $single_query, [ 'id' => $note_id ] );
		$this->assertSame( $note_id, $actual['data']['comment']['databaseId'] );
	}

	/**
	 * `viewer { comments }` follows the same rules as the root list: a user who can't moderate
	 * comments only gets their comments on published content.
	 */
	public function testUserCommentsOnlyIncludeCommentsOnPublishedContentForNonModerators() {
		// This file doesn't reset the schema between tests, and the content and user comment fields need a fresh one.
		$this->clearSchema();

		$subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		$draft_id   = $this->factory()->post->create( [ 'post_status' => 'draft' ] );

		$published_comment = $this->createCommentObject( [ 'user_id' => $subscriber ] );
		$this->factory()->comment->create(
			[
				'comment_post_ID'  => $draft_id,
				'comment_approved' => '1',
				'user_id'          => $subscriber,
			]
		);

		$actual = $this->query_as( $subscriber, 'query { viewer { comments { nodes { databaseId } } } }' );

		$this->assertSame( [ $published_comment ], wp_list_pluck( $actual['data']['viewer']['comments']['nodes'], 'databaseId' ) );
	}

	/**
	 * A comment's `parent` is limited to the content of the comment it's queried from, like its
	 * replies.
	 */
	public function testParentIsLimitedToTheContentOfTheComment() {
		$draft_id      = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$hidden_parent = $this->factory()->comment->create(
			[
				'comment_post_ID'  => $draft_id,
				'comment_approved' => '1',
			]
		);
		$child         = $this->createCommentObject( [ 'comment_parent' => $hidden_parent ] );

		$actual = $this->query_as( 0, 'query ( $id: ID! ) { comment( id: $id, idType: DATABASE_ID ) { parent { node { databaseId } } } }', [ 'id' => $child ] );

		$this->assertNull( $actual['data']['comment']['parent'] );
	}
}
