<?php

class CommentObjectQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;
	public $subscriber;

	public function setUp(): void {
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
		$this->subscriber       = $this->factory()->user->create( [
			'role' => 'subscriber',
		]);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function createCommentObject( $args = [] ) {

		$post_id = $this->factory()->post->create([
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Post for CommentObjectQueries',
			'post_author' => $this->admin,
		]);

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'comment_post_ID'      => $post_id,
			'comment_parent'       => 0,
			'comment_author'       => get_user_by( 'id', $this->admin )->user_email,
			'comment_author_email' => get_user_by( 'id', $this->admin )->user_email,
			'comment_content'      => 'Test comment content',
			'comment_approved'     => '1',
			'comment_date'         => $this->current_date,
			'comment_date_gmt'     => $this->current_date_gmt,
			'user_id'              => $this->admin,
			'comment_type'         => 'comment',
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
	 * testCommentQuery
	 *
	 * This tests creating a single comment with data and retrieving said comment via a GraphQL
	 * query
	 *
	 * @since 0.0.5
	 */
	public function testCommentQuery() {

		/**
		 * Create a comment
		 */
		wp_set_current_user( $this->admin );
		$comment_id = $this->createCommentObject();

		/**
		 * Create the global ID based on the comment_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		query testCommentQuery( $id: ID!, $idType: CommentNodeIdTypeEnum ) {
			comment(id: $id, idType: $idType ) {
				agent
				author{
					node {
						__typename
						...on User {
							userId
						}
					}
				}
				authorIp
				databaseId
				replies {
					edges {
						node {
							id
							databaseId
							parent {
								node {
									databaseId
								}
							}
						}
					}
				}
				commentedOn {
					node {
						__typename
					}
				}
				content
				date
				dateGmt
				id
				karma
				parent {
					node {
						id
					}
				}
				status
			}
		}';

		// Test with database_id.
		$variables = [
			'id'     => $comment_id,
			'idType' => 'DATABASE_ID',
		];

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'comment' => [
				'agent'       => null,
				'author'      => [
					'node' => [
						'__typename' => 'User',
						'userId'     => $this->admin,
					],
				],
				'authorIp'    => null,
				'replies'     => [
					'edges' => [],
				],
				'databaseId'  => $comment_id,
				'commentedOn' => [
					'node' => [
						'__typename' => 'Post',
					],
				],
				'content'     => apply_filters( 'comment_text', 'Test comment content' ),
				'date'        => $this->current_date,
				'dateGmt'     => $this->current_date_gmt,
				'id'          => $global_id,
				'karma'       => null,
				'parent'      => null,
				'status' => 'APPROVE',
			],
		];

		$this->assertEqualSets( $expected, $actual['data'] );

		// Test with global_id.
		$variables = [
			'id'     => $global_id,
			'idType' => 'ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $actual['data'] );
	}

	/**
	 * testCommentQuery
	 *
	 * This tests creating a single comment with data and retrieving said comment via a GraphQL
	 * query
	 *
	 * @since 0.0.5
	 */
	public function testCommentWithCommentAuthor() {

		/**
		 * Create a comment
		 */
		$comment_id = $this->createCommentObject( [
			'comment_author'       => 'Author Name',
			'comment_author_email' => 'test@test.com',
			'comment_author_url'   => 'http://example.com',
			'user_id'              => 0,
		] );

		codecept_debug( $comment_id );

		/**
		 * Create the global ID based on the comment_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', (string) $comment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		query testCommentWithCommentAuthor ( $id: ID! ) {
			comment(id: $id ) {
				agent
				author {
					node {
						...on CommentAuthor {
							id
							databaseId
							name
							email
							url
						}
					}
				}
				status
			}
		}';

		$variables = [
			'id' => $global_id,
		];

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'comment' => [
				'agent'    => null,
				'author'   => [
					'node' => [
						'id'         => \GraphQLRelay\Relay::toGlobalId( 'comment_author', $comment_id ),
						'databaseId' => absint( $comment_id ),
						'name'       => get_comment_author( $comment_id ),
						'email'      => null, // Email is restricted to users with moderate_comments capability
						'url'        => get_comment_author_url( $comment_id ),
					],
				],
				'status' => 'APPROVE'
			],
		];

		$this->assertEqualSets( $expected, $actual['data'] );

		wp_set_current_user( $this->admin );

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'comment' => [
				'agent'    => null,
				'author'   => [
					'node' => [
						'id'         => \GraphQLRelay\Relay::toGlobalId( 'comment_author', $comment_id ),
						'databaseId' => absint( $comment_id ),
						'name'       => get_comment_author( $comment_id ),
						'email'      => get_comment_author_email( $comment_id ),
						'url'        => get_comment_author_url( $comment_id ),
					],
				],
				'status' => 'APPROVE'
			],
		];

		$this->assertEqualSets( $expected, $actual['data'] );

	}

	/**
	 * testCommentQuery
	 *
	 * This tests creating a single comment with data and retrieving said comment via a GraphQL
	 * query
	 *
	 * @since 0.0.5
	 */
	public function testCommentQueryWithChildrenAssignedPostAndParent() {

		// Post object to assign comments to.
		$post_id = $this->factory()->post->create( [
			'post_content' => 'Post object',
			'post_author'  => $this->admin,
			'post_status'  => 'publish',
		] );

		// Parent comment.
		$parent_comment = $this->createCommentObject(
			[
				'comment_post_ID' => $post_id,
				'comment_content' => apply_filters( 'comment_text', 'Parent comment' ),
			]
		);

		/**
		 * Create a comment
		 */
		$comment_id = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_content' => apply_filters( 'comment_text', 'Test comment' ),
			'comment_parent'  => $parent_comment,
		] );

		// Create child comments.
		$child_1 = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_content' => apply_filters( 'comment_text', 'Child 1' ),
			'comment_parent'  => $comment_id,
		] );

		$child_2 = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_content' => apply_filters( 'comment_text', 'Child 2' ),
			'comment_parent'  => $comment_id,
		] );

		/**
		 * Create the global ID based on the comment_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			comment(id: \"{$global_id}\") {
				replies {
					edges {
						node {
							commentId
							content
						}
					}
				}
				commentId
				commentedOn {
					node {
						... on Post {
							content
						}
					}
				}
				content
				parent {
					node {
						commentId
						content
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( [ 'query' => $query ] );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'comment' => [
				'replies'     => [
					'edges' => [
						[
							'node' => [
								'commentId' => $child_2,
								'content'   => apply_filters( 'comment_text', 'Child 2' ),
							],
						],
						[
							'node' => [
								'commentId' => $child_1,
								'content'   => apply_filters( 'comment_text', 'Child 1' ),
							],
						],
					],
				],
				'commentId'   => $comment_id,
				'commentedOn' => [
					'node' => [
						'content' => apply_filters( 'the_content', 'Post object' ),
					],
				],
				'content'     => apply_filters( 'comment_text', 'Test comment' ),
				'parent'      => [
					'node' => [
						'commentId' => $parent_comment,
						'content'   => apply_filters( 'comment_text', 'Parent comment' ),
					],
				],
			],
		];

		$this->assertEqualSets( $expected, $actual['data'] );
	}

	/**
	 * Assert that fields containing sensitive data are not exposed to users without proper caps
	 *
	 * @dataProvider dataProviderSwitchUser
	 *
	 * @param $user
	 * @param $should_display
	 *
	 * @throws Exception
	 */
	public function testCommentQueryHiddenFields( $user, $should_display ) {

		$post_id = $this->factory->post->create();

		$admin_args         = [
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Admin Comment',
			'comment_author_email' => 'admin@test.com',
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'Admin Agent',
		];
		$admin_comment      = $this->createCommentObject( $admin_args );
		$subscriber_args    = [
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Subscriber Comment',
			'comment_author_email' => 'subscriber@test.com',
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'Subscriber Agent',
		];
		$subscriber_comment = $this->createCommentObject( $subscriber_args );

		$query = '
		query commentQuery( $id:ID! ) {
			comment(id: $id) {
				commentId
				id
				authorIp
				agent
				karma
				content
				status
				commentedOn{
					node {
						... on Post{
							postId
						}
					}
				}
			}
		}
		';

		wp_set_current_user( $this->{$user} );

		$variables    = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $admin_comment ),
		];
		$admin_actual = $this->graphql( compact( 'query', 'variables' ) );

		$variables         = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $subscriber_comment ),
		];
		$subscriber_actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $admin_actual );
		$this->assertArrayNotHasKey( 'errors', $subscriber_actual );

		$this->assertEquals( $admin_comment, $admin_actual['data']['comment']['commentId'] );
		$this->assertEquals( $subscriber_comment, $subscriber_actual['data']['comment']['commentId'] );

		$this->assertEquals( apply_filters( 'comment_text', $subscriber_args['comment_content'] ), $subscriber_actual['data']['comment']['content'] );
		$this->assertEquals( apply_filters( 'comment_text', $admin_args['comment_content'] ), $admin_actual['data']['comment']['content'] );

		if ( true === $should_display ) {
			$this->assertNotNull( $admin_actual['data']['comment']['authorIp'] );
			$this->assertNotNull( $admin_actual['data']['comment']['agent'] );
		} else {
			$this->assertNull( $admin_actual['data']['comment']['authorIp'] );
			$this->assertNull( $admin_actual['data']['comment']['agent'] );
		}

	}

	/**
	 * Assert that non-approved posts are hidden from users without proper caps
	 *
	 * @dataProvider dataProviderSwitchUser
	 * @param $user
	 * @param $should_display
	 * @throws Exception
	 */
	public function testUnapprovedCommentsNotQueryableWithoutAuth( $user, $should_display ) {

		$post_id = $this->factory->post->create();

		$admin_args         = [
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Admin Comment',
			'comment_approved'     => 0,
			'comment_author_email' => 'admin@test.com',
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'Admin Agent',
		];
		$admin_comment      = $this->createCommentObject( $admin_args );
		$subscriber_args    = [
			'comment_post_ID'      => $post_id,
			'comment_approved'     => 0,
			'comment_content'      => 'Subscriber Comment',
			'comment_author_email' => 'subscriber@test.com',
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'Subscriber Agent',
		];
		$subscriber_comment = $this->createCommentObject( $subscriber_args );

		$query = '
		query commentQuery( $id:ID! ) {
			comment(id: $id) {
				commentId
				id
				authorIp
				agent
				status
				karma
				content
				commentedOn{
					node {
						... on Post{
							postId
						}
					}
				}
			}
		}
		';

		wp_set_current_user( $this->{$user} );

		$variables    = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $admin_comment ),
		];
		$admin_actual = $this->graphql( compact( 'query', 'variables' ) );

		$variables         = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $subscriber_comment ),
		];
		$subscriber_actual = $this->graphql( compact( 'query', 'variables' ) );

		if ( true === $should_display ) {
			$this->assertArrayNotHasKey( 'errors', $admin_actual );
			$this->assertArrayNotHasKey( 'errors', $subscriber_actual );
			$this->assertNotNull( $admin_actual['data']['comment']['authorIp'] );
			$this->assertNotNull( $admin_actual['data']['comment']['agent'] );
			$this->assertEquals( $admin_comment, $admin_actual['data']['comment']['commentId'] );
			$this->assertEquals( $subscriber_comment, $subscriber_actual['data']['comment']['commentId'] );
			$this->assertEquals( apply_filters( 'comment_text', $subscriber_args['comment_content'] ), $subscriber_actual['data']['comment']['content'] );
			$this->assertEquals( apply_filters( 'comment_text', $admin_args['comment_content'] ), $admin_actual['data']['comment']['content'] );
			$this->assertEquals( 'HOLD', $admin_actual['data']['comment']['status'] );
		} else {
			$this->assertEmpty( $admin_actual['data']['comment'] );
		}

	}

	/**
	 * Assert that comments attached to private posts are hidden from users
	 * without proper caps
	 *
	 * @throws Exception
	 */
	public function testPrivatePostCommentsNotQueryableWithoutAuth() {

		$post_id = $this->factory()->post->create( [
			'post_status'  => 'private',
			'post_content' => 'Test',
		] );

		$comment_args = [
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Private Post Comment',
			'comment_approved'     => '1',
			'comment_author_email' => 'admin@test.com',
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'Admin Agent',
		];
		$comment      = $this->createCommentObject( $comment_args );

		$query = '
		query commentQuery( $id:ID! ) {
			comment(id: $id) {
				commentId
				id
				authorIp
				agent
				status
				karma
				content
				commentedOn{
					node {
						... on Post{
							postId
						}
					}
				}
			}
		}
		';

		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment ),
		];

		$public_actual = $this->graphql( compact( 'query', 'variables' ) );

		wp_set_current_user( $this->admin );
		$admin_actual = $this->graphql( compact( 'query', 'variables' ) );

		// Verify there are no errors.
		$this->assertArrayNotHasKey( 'errors', $public_actual );
		$this->assertArrayNotHasKey( 'errors', $admin_actual );

		// Verify the Public request is empty.
		$this->assertEmpty( $public_actual['data']['comment'] );

		// Verify the Admin request has the correct comment.
		$this->assertNotNull( $admin_actual['data']['comment']['authorIp'] );
		$this->assertNotNull( $admin_actual['data']['comment']['agent'] );
		$this->assertEquals( $comment, $admin_actual['data']['comment']['commentId'] );
		$this->assertEquals( apply_filters( 'comment_text', $comment_args['comment_content'] ), $admin_actual['data']['comment']['content'] );

	}

	public function dataProviderSwitchUser() {
		return [
			[
				'user'           => 'admin',
				'should_display' => true,
			],
			[
				'user'           => 'subscriber',
				'should_display' => false,
			],
		];
	}

	/**
	 * @throws Exception
	 */
	public function testQueryingAvatarOnUserAuthorsIsValidForPublicAndAuthenticatedRequests() {

		// create a comment with a guest author as the author
		$comment_by_comment_author_id = $this->createCommentObject([
			'user_id'              => 0,
			'comment_author_email' => 'guest@email.test',
		]);

		$comment_by_user_id = $this->createCommentObject();

		$query = '
		query GetCommentAuthorWithAvatar($id:ID!){
			comment( id: $id ) {
				author {
					node {
						__typename
						url
						name
						avatar {
							url
						}
					}
				}
			}
		}
		';

		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_by_comment_author_id ),
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$typename = $actual['data']['comment']['author']['node']['__typename'];

		$this->assertSame( 'CommentAuthor', $typename );
		$this->assertNotEmpty( $actual['data']['comment']['author']['node']['avatar']['url'] );

		// Ensure avatar is the same when logged in.
		$expected = $actual['data']['comment']['author']['node']['avatar']['url'];

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['comment']['author']['node']['avatar']['url'] );

		// Test with user ID.

		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_by_user_id ),
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$typename = $actual['data']['comment']['author']['node']['__typename'];

		$this->assertSame( 'User', $typename );
		$this->assertNotEmpty( $actual['data']['comment']['author']['node']['avatar']['url'] );

		// Ensure avatar is the same when logged in.
		$expected = $actual['data']['comment']['author']['node']['avatar']['url'];

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['comment']['author']['node']['avatar']['url'] );

		wp_delete_comment( $comment_by_user_id );
		wp_delete_comment( $comment_by_comment_author_id );

	}


	/**
	 * @throws Exception
	 */
	public function testQueryingAvatarOnCommentAuthorsIsValid() {

		// create a comment with a guest author as the author
		$comment_id = $this->createCommentObject([
			'comment_author'       => 0,
			'comment_author_email' => 'test@gmail.com',
			'user_id'              => 0,
		]);

		$query = '
		query GetCommentAuthorWithAvatar($id:ID!){
			comment( id: $id ) {
				author {
					node {
						__typename
						url
						name
						avatar {
							url
						}
					}
				}
			}
		}
		';

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		$actual = $this->graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $global_id,
			],
		] );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$typename = $actual['data']['comment']['author']['node']['__typename'];

		$this->assertSame( 'CommentAuthor', $typename );
		$this->assertNotEmpty( $actual['data']['comment']['author']['node']['avatar']['url'] );
	}

	/**
	 * Tests that the comment_text filter properly applies to the text contnet
	 *
	 * @see: https://github.com/wp-graphql/wp-graphql/pull/2319
	 */
	public function testFilteringCommentTextWorksProperly() {

		$content = uniqid();

		wp_set_current_user( $this->admin );
		$comment_id = $this->createCommentObject([
			'comment_content' => $content,
		]);
		$global_id  = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		$query = '
		query GetComment($id:ID!){
			comment(id:$id) {
				id
				databaseId
				content
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $global_id,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( apply_filters( 'comment_text', $content, null ), $actual['data']['comment']['content'] );

		$filtered = 'filtered...';

		// test that filtering the comment text with 2 arguments works properly
		add_filter( 'comment_text', function ( $text, $comment ) use ( $filtered ) {
			return $filtered;
		}, 10, 2 );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $global_id,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( apply_filters( 'comment_text', $filtered, null ), $actual['data']['comment']['content'] );

	}


}
