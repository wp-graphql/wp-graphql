<?php

class CommentMutationsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $title;
	public $content;
	public $client_mutation_id;
	public $admin;
	public $subscriber;
	public $author;

	public function setUp(): void {

		// before
		parent::setUp();

		$this->title              = 'some title';
		$this->content            = 'some content';
		$this->client_mutation_id = 'someUniqueId';

		$this->author = $this->factory()->user->create(
			[
				'role' => 'author',
			]
		);

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->subscriber = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		WPGraphQL::clear_schema();
	}


	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}


	public function createComment( &$post_id, &$comment_id, $postCreator, $commentCreator ) {

		wp_set_current_user( $postCreator );
		$post_args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Post for CommentMutationsTest',
			'post_content' => 'Post Content',
		];

		/**
		 * Create a page to test against
		 */
		$post_id = $this->factory()->post->create( $post_args );

		wp_set_current_user( $commentCreator );
		$user         = wp_get_current_user();
		$comment_args = [
			'user_id'              => $user->ID,
			'comment_author'       => $user->display_name,
			'comment_author_url'   => $user->user_url,
			'comment_author_email' => $user->user_email,
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Comment Content',
		];

		/**
		 * Create a comment to test against
		 */
		$comment_id = $this->factory()->comment->create( $comment_args );
		return $comment_id;
	}

	public function trashComment( &$comment_id ) {
		wp_trash_comment( $comment_id );
	}

	// tests
	public function testCreateComment() {
		add_filter( 'duplicate_comment_id', '__return_false' );
		add_filter( 'comment_flood_filter', '__return_false' );

		$args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for CommentMutationsTest',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against
		 */
		$post_id = $this->factory()->post->create( $args );

		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '0' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Original Title for CommentMutationsTest' );
		$this->assertEquals( $new_post->post_content, 'Original Content' );

		$query = '
		mutation createCommentTest( $commentOn:Int!, $author:String, $email: String, $content:String! ){
			createComment(
				input: {
					commentOn: $commentOn
					content: $content
					author: $author
					authorEmail: $email
				}
			)
			{
				success
				comment {
					content
					author {
						node {
							name
							... on User {
								id
								databaseId
								username
							}
						}
					}
					status
				}
			}
		}
		';

		$expected_content = apply_filters( 'comment_text', $this->content );

		// test with logged in user
		$variables = [
			'commentOn' => $post_id,
			'content'   => $this->content,
			'author'    => null,
			'email'     => null,
		];
		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertSame( $this->admin, $actual['data']['createComment']['comment']['author']['node']['databaseId'] );
		$this->assertEquals( $expected_content, $actual['data']['createComment']['comment']['content'] );
		$this->assertEquals( 'APPROVE', $actual['data']['createComment']['comment']['status'] );

		$count = wp_count_comments( $post_id );
		$this->assertEquals( '1', $count->total_comments );

		// Test logged in user without `moderate_comments`.
		wp_set_current_user( $this->subscriber );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertEquals( $this->subscriber, $actual['data']['createComment']['comment']['author']['node']['databaseId'] );
		$this->assertEquals( 'HOLD', $actual['data']['createComment']['comment']['status'] );

		// Test logged in user different than author.
		wp_set_current_user( $this->admin );

		$variables = [
			'commentOn' => $post_id,
			'content'   => $this->content,
			'author'    => 'Comment Author',
			'email'     => 'comment_author@example.com',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertEquals( $variables['author'], $actual['data']['createComment']['comment']['author']['node']['name'] );

		// Test logged in user different than author without `moderate_comments`.
		wp_set_current_user( $this->subscriber );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertEquals( $this->subscriber, $actual['data']['createComment']['comment']['author']['node']['databaseId'] );
	}


	public function testCreateChildComment() {
		// Create parent comment.
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );

		$query = '
			mutation createChildCommentTest( $commentOn: Int!, $parent: ID, $content: String!){
				createComment(
					input: {
						commentOn: $commentOn,
						content: $content,
						parent: $parent,
					}
				){
					success
					comment {
						databaseId
						parent {
							node {
								databaseId
							}
						}
					}
				}
			}
		';

		wp_set_current_user( $this->admin );

		// Test with database Id
		$variables = [
			'commentOn' => $post_id,
			'content'   => $this->content,
			'parent'    => $comment_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertEquals( $comment_id, $actual['data']['createComment']['comment']['parent']['node']['databaseId'] );

		// Test with global Id
		$variables = [
			'commentOn' => $post_id,
			'content'   => 'Testing with global Id',
			'parent'    => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertEquals( $comment_id, $actual['data']['createComment']['comment']['parent']['node']['databaseId'] );
	}

	public function testUpdateCommentWithAuthorConnection() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );

		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '1' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post for CommentMutationsTest' );
		$this->assertEquals( $new_post->post_content, 'Post Content' );

		$new_comment = $this->factory()->comment->get_object_by_id( $comment_id );

		$this->assertEquals( $new_comment->user_id, get_current_user_id() );
		$this->assertEquals( $new_comment->comment_post_ID, $post_id );
		$this->assertEquals( $new_comment->comment_content, 'Comment Content' );

		$content = 'Updated Content';

		$query = '
		mutation updateCommentTest( $id: ID!, $content: String! ) {
			updateComment(
				input: {
					id: $id
					content: $content
				}
			)
			{
				comment {
					id
					databaseId
					content
				}
			}
		}
		';

		$expected = [
			'updateComment' => [
				'comment' => [
					'id'         => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'databaseId' => $comment_id,
					'content'    => apply_filters( 'comment_text', $content ),
				],
			],
		];

		// Test with database ID.
		$variables = [
			'id'      => $comment_id,
			'content' => $content,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

		// Test with global ID
		$content   = 'Updated via Global ID';
		$variables = [
			'id'      => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
			'content' => $content,
		];
		$expected['updateComment']['comment']['content'] = apply_filters( 'comment_text', $content );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testUpdateCommentWithStatus() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );

		$comment = get_comment( $comment_id );

		$query = '
		mutation updateCommentStatus( $id: ID!, $status: CommentStatusEnum ) {
			updateComment(
				input: {
					id: $id
					status: $status
				}
			)
			{
				comment {
					databaseId
					status
				}
			}
		}
		';

		// Test HOLD status.
		$variables = [
			'id'     => $comment_id,
			'status' => 'HOLD',
		];

		// Test without permissions.
		wp_set_current_user( 0 );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		// Test with permissions.
		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'HOLD', $actual['data']['updateComment']['comment']['status'] );

		// Test with SPAM status.
		$variables['status'] = 'SPAM';

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'SPAM', $actual['data']['updateComment']['comment']['status'] );

		// Test with TRASH status.
		$variables['status'] = 'TRASH';

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'TRASH', $actual['data']['updateComment']['comment']['status'] );
	}

	/**
	 * Non-moderator comment owner must not be able to change comment status (e.g. self-approve).
	 * Only users with moderate_comments may set status/approved on updateComment.
	 */
	public function testUpdateCommentStatusRequiresModerateCommentsCapability() {
		// Create post as author, then a pending comment owned by subscriber (no moderate_comments).
		wp_set_current_user( $this->author );
		$post_id = $this->factory()->post->create( [
			'post_type'       => 'post',
			'post_status'     => 'publish',
			'post_title'      => 'Post for status cap test',
			'post_content'    => 'Content',
			'comment_status'  => 'open',
		] );

		$subscriber_user = get_user_by( 'id', $this->subscriber );
		$comment_id     = $this->factory()->comment->create( [
			'user_id'           => $this->subscriber,
			'comment_author'    => $subscriber_user->display_name,
			'comment_author_url' => $subscriber_user->user_url,
			'comment_author_email' => $subscriber_user->user_email,
			'comment_post_ID'   => $post_id,
			'comment_content'   => 'Pending comment',
			'comment_approved'  => '0',
		] );

		$comment_before = get_comment( $comment_id );
		$this->assertEquals( '0', $comment_before->comment_approved, 'Comment should start pending.' );

		wp_set_current_user( $this->subscriber );

		$query = '
		mutation updateCommentStatus( $id: ID!, $status: CommentStatusEnum ) {
			updateComment( input: { id: $id, status: $status } ) {
				success
				comment { databaseId status }
			}
		}
		';
		$variables = [
			'id'     => $comment_id,
			'status' => 'APPROVE',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual, 'Non-moderator owner must not be allowed to set comment status to APPROVE.' );
		$comment_after = get_comment( $comment_id );
		$this->assertNotEquals( '1', $comment_after->comment_approved, 'Comment must remain unapproved when non-moderator tries to approve.' );
	}

	public function testDeleteCommentWithPostConnection() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '1' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post for CommentMutationsTest' );
		$this->assertEquals( $new_post->post_content, 'Post Content' );

		$new_comment = $this->factory()->comment->get_object_by_id( $comment_id );
		$content     = 'Comment Content';
		$this->assertEquals( $new_comment->user_id, get_current_user_id() );
		$this->assertEquals( $new_comment->comment_post_ID, $post_id );
		$this->assertEquals( $new_comment->comment_content, $content );

		$query = '
		mutation deleteCommentTest( $id: ID! ) {
			deleteComment(
				input: {
					id: $id
				}
			)
			{
				deletedId
				comment {
					id
					databaseId
					content
				}
			}
		}
		';

		// Test with database ID.
		$variables = [
			'id' => $comment_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'deleteComment' => [
				'deletedId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'   => [
					'id'         => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'databaseId' => $comment_id,
					'content'    => apply_filters( 'comment_text', $content ),
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
		// Test with global Id
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
		$variables['id'] = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		$expected = [
			'deleteComment' => [
				'deletedId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'   => [
					'id'         => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'databaseId' => $comment_id,
					'content'    => apply_filters( 'comment_text', $content ),
				],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		codecept_debug( $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testRestoreComment() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '1' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post for CommentMutationsTest' );
		$this->assertEquals( $new_post->post_content, 'Post Content' );

		$new_comment = $this->factory()->comment->get_object_by_id( $comment_id );
		$content     = 'Comment Content';
		$this->assertEquals( $new_comment->user_id, get_current_user_id() );
		$this->assertEquals( $new_comment->comment_post_ID, $post_id );
		$this->assertEquals( $new_comment->comment_content, $content );

		$this->trashComment( $comment_id );

		$query = '
		mutation restoreCommentTest( $id: ID! ) {
			restoreComment(
				input: {
					id: $id
				}
			)
			{
				restoredId
				comment {
					id
					databaseId
					content
				}
			}
		}
		';

		// Test database ID
		$variables = [
			'id' => $comment_id,
		];

		$expected = [
			'restoreComment' => [
				'restoredId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'    => [
					'id'         => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'databaseId' => $comment_id,
					'content'    => apply_filters( 'comment_text', $content ),
				],
			],
		];

		// Test without permissions
		wp_set_current_user( 0 );
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with permissions
		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $expected, $actual['data'] );

		// Test global Id
		$this->trashComment( $comment_id );

		$variables['id'] = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $expected, $actual['data'] );

		// Test bad ID
		$variables['id'] = '3ab21';
		$actual          = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Make sure that we can't leave a comment if we are not logged in and the comment registration
	 * flag is set
	 */
	public function testCantCreateCommentNotLoggedIn() {

		$args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for CommentMutationsTest',
			'post_content' => 'Original Content',
		];

		/**
		 * Set the flag so that only registered users can create comments
		 */
		update_option( 'comment_registration', '1' );

		/**
		 * Create a page to test against
		 */
		$post_id = $this->factory()->post->create( $args );

		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '0' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Original Title for CommentMutationsTest' );
		$this->assertEquals( $new_post->post_content, 'Original Content' );

		$query = '
		mutation createCommentTest( $commentOn:Int!, $author:String!, $email: String!, $content:String! ){
			createComment(
				input: {
					commentOn: $commentOn
					content: $content
					author: $author
					authorEmail: $email
				}
			)
			{
				comment {
					content
				}
			}
		}
		';

		$variables = [
			'commentOn' => $post_id,
			'content'   => $this->content,
			'author'    => 'Comment Author',
			'email'     => 'subscriber@example.com',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertNotEmpty( $actual['errors'] );
		$this->assertEmpty( $actual['data']['createComment'] );
	}

	/**
	 * Make sure that we can leave a comment if we are not logged in BUT the comment registration
	 * flag is allowed
	 */
	public function testCanCreateCommentNotLoggedIn() {

		$args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for CommentMutationsTest',
			'post_content' => 'Original Content',
		];

		/**
		 * Set the flag so that only registered users can create comments
		 */
		update_option( 'comment_registration', '0' );

		/**
		 * Create a page to test against
		 */
		$post_id = $this->factory()->post->create( $args );

		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '0' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Original Title for CommentMutationsTest' );
		$this->assertEquals( $new_post->post_content, 'Original Content' );

		$query = '
		mutation createCommentTest( $commentOn:Int!, $author:String!, $email: String!, $content:String! ){
			createComment(
				input: {
					commentOn: $commentOn
					content: $content
					author: $author
					authorEmail: $email
				}
			)
			{
				success
			}
		}
		';

		$variables = [
			'commentOn' => $post_id,
			'content'   => $this->content,
			'author'    => 'Comment Author',
			'email'     => 'subscriber@example.com',
		];

		wp_set_current_user( 0 );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
	}

	public function testUpdateCommentWithoutChangesDoesNotErrorButReturnsFalseSuccess() {

		update_option( 'comment_registration', '1' );
		update_option( 'moderation_notify', '1' );

		// create a comment
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );

		$comment = get_comment( $comment_id, 'ARRAY_A' );

		$mutation = '
		mutation UpdateComment( $input: UpdateCommentInput! ) {
			updateComment(input: $input) {
				success
				comment {
					id
					databaseId
					content
					status
					author {
						__typename
						node {
							databaseId
							name
							email
						}
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [
					'input' => [
						'id'      => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
						'content' => 'Comment Content',
					],
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'updateComment.success', self::IS_FALSY ),
				$this->expectedField( 'updateComment.comment.databaseId', (int) $comment['comment_ID'] ),
				$this->expectedField( 'updateComment.comment.content', apply_filters( 'comment_text', $comment['comment_content'] ) ),
			]
		);
	}

	public function testCommentStatusTransitions() {
		// Create a post first
		$post_id = $this->factory()->post->create();
		
		// Create the comment
		$comment_id = $this->factory()->comment->create([
			'comment_post_ID' => $post_id,
			'comment_approved' => '1',
		]);
	
		$query = '
		mutation UpdateCommentStatus( $id: ID!, $status: CommentStatusEnum! ) {
			updateComment(
				input: {
					id: $id
					status: $status
				}
			) {
				comment {
					id
					databaseId
					status
				}
			}
		}
		';
	
		// Test as subscriber (should fail)
		wp_set_current_user($this->subscriber);
		
		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId('comment', $comment_id),
			'status' => 'SPAM'
		];
		
		$actual = $this->graphql(compact('query', 'variables'));
		
		// Subscriber should get an error about capabilities
		$this->assertArrayHasKey('errors', $actual);
	
		// Test as admin (should succeed)
		wp_set_current_user($this->admin);
		$actual = $this->graphql(compact('query', 'variables'));
		
		$this->assertArrayNotHasKey('errors', $actual);
		$this->assertEquals('SPAM', $actual['data']['updateComment']['comment']['status']);
	}

	/**
	 * Creates a published post owned by $this->author and an approved comment on it owned by
	 * $this->subscriber, for the updateComment authorization tests.
	 *
	 * @param array<string,mixed> $post_args Overrides for the post.
	 *
	 * @return array{0:int,1:int} The post ID and the comment ID.
	 */
	public function createApprovedSubscriberComment( $post_args = [] ) {
		$post_id = $this->factory()->post->create(
			array_merge(
				[
					'post_author'    => $this->author,
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'post_title'     => 'Post for updateComment authorization tests',
					'post_content'   => 'Content',
					'comment_status' => 'open',
				],
				$post_args
			)
		);

		$subscriber_user = get_user_by( 'id', $this->subscriber );

		$comment_id = $this->factory()->comment->create(
			[
				'user_id'              => $this->subscriber,
				'comment_author'       => $subscriber_user->display_name,
				'comment_author_email' => $subscriber_user->user_email,
				'comment_author_url'   => $subscriber_user->user_url,
				'comment_post_ID'      => $post_id,
				'comment_content'      => 'Content a moderator approved',
				'comment_approved'     => '1',
			]
		);

		return [ $post_id, $comment_id ];
	}

	/**
	 * A non-moderator must not be able to carry an approved comment onto a post they were never
	 * allowed to comment on. @see GHSA-cf67-w9ww-p68v, GHSA-2w4p-8q7g-h7xv
	 */
	public function testUpdateCommentOwnerCannotMoveCommentToAnotherPost() {
		list( $post_id, $comment_id ) = $this->createApprovedSubscriberComment();

		$target_post_id = $this->factory()->post->create(
			[
				'post_author'    => $this->admin,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_title'     => 'Post with comments closed',
				'comment_status' => 'closed',
			]
		);

		wp_set_current_user( $this->subscriber );
		$this->assertFalse( current_user_can( 'moderate_comments' ), 'The subscriber must not be able to moderate comments.' );

		$query = '
		mutation moveComment( $id: ID!, $commentOn: Int!, $content: String! ) {
			updateComment( input: { id: $id, commentOn: $commentOn, content: $content } ) {
				success
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'        => $comment_id,
					'commentOn' => $target_post_id,
					'content'   => 'Content nobody approved',
				],
			]
		);

		$this->assertArrayHasKey( 'errors', $actual, 'A non-moderator must not be allowed to move their comment to another post.' );

		$comment = get_comment( $comment_id );
		$this->assertEquals( $post_id, (int) $comment->comment_post_ID, 'The comment must still be on the post it was left on.' );
		$this->assertEquals( '1', $comment->comment_approved, 'The comment must still be approved.' );
		$this->assertEquals( 'Content a moderator approved', $comment->comment_content, 'The content must be unchanged.' );
	}

	/**
	 * @dataProvider dataProviderRestrictedUpdateCommentFields
	 *
	 * @param string $field  The input field a non-moderator must not be allowed to change.
	 * @param string $column The comment column that field writes to.
	 */
	public function testUpdateCommentOwnerCannotChangeIdentityFields( $field, $column ) {
		list( $post_id, $comment_id ) = $this->createApprovedSubscriberComment();

		// A sibling comment to point `parent` at.
		$parent_comment_id = $this->factory()->comment->create(
			[
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Sibling comment',
				'comment_approved' => '1',
			]
		);

		$values = [
			'author'      => 'Site Administrator',
			'authorEmail' => 'admin@example.test',
			'authorUrl'   => 'https://example.test/not-mine',
			'date'        => '2001-01-01 00:00:00',
			'parent'      => (string) $parent_comment_id,
			'type'        => 'pingback',
		];

		$before = get_comment( $comment_id );

		wp_set_current_user( $this->subscriber );

		$query = '
		mutation updateComment( $input: UpdateCommentInput! ) {
			updateComment( input: $input ) {
				success
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'input' => [
						'id'   => $comment_id,
						$field => $values[ $field ],
					],
				],
			]
		);

		$this->assertArrayHasKey( 'errors', $actual, sprintf( 'A non-moderator must not be allowed to change `%s`.', $field ) );

		$after = get_comment( $comment_id );
		$this->assertEquals( $before->{$column}, $after->{$column}, sprintf( '`%s` must be unchanged.', $column ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function dataProviderRestrictedUpdateCommentFields() {
		return [
			'author'      => [ 'author', 'comment_author' ],
			'authorEmail' => [ 'authorEmail', 'comment_author_email' ],
			'authorUrl'   => [ 'authorUrl', 'comment_author_url' ],
			'date'        => [ 'date', 'comment_date' ],
			'parent'      => [ 'parent', 'comment_parent' ],
			'type'        => [ 'type', 'comment_type' ],
		];
	}

	/**
	 * A client that resubmits a whole form sends fields it did not change, which must not be read
	 * as an attempt to change them.
	 */
	public function testUpdateCommentOwnerCanResendUnchangedValues() {
		list( $post_id, $comment_id ) = $this->createApprovedSubscriberComment();

		$before          = get_comment( $comment_id );
		$subscriber_user = get_user_by( 'id', $this->subscriber );

		wp_set_current_user( $this->subscriber );

		$query = '
		mutation updateComment( $input: UpdateCommentInput! ) {
			updateComment( input: $input ) {
				success
				comment { databaseId content status }
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'input' => [
						'id'          => $comment_id,
						'commentOn'   => $post_id,
						'author'      => $subscriber_user->display_name,
						'authorEmail' => $subscriber_user->user_email,
						'date'        => $before->comment_date,
						'type'        => $before->comment_type,
						'content'     => 'A typo fixed in my own comment',
					],
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual, 'Resending the stored values alongside new content must be allowed.' );

		$after = get_comment( $comment_id );
		$this->assertEquals( 'A typo fixed in my own comment', $after->comment_content, 'The content must be updated.' );
		$this->assertEquals( $post_id, (int) $after->comment_post_ID, 'The comment must still be on the same post.' );
		$this->assertEquals( $before->comment_date, $after->comment_date, 'The date must be unchanged.' );
	}

	/**
	 * Approval belongs to the content a moderator read, so content edited into something that
	 * fails the site's moderation checks goes back for review.
	 */
	public function testUpdateCommentOwnerContentEditIsHeldWhenItFailsModeration() {
		$previous = [
			'comment_moderation'          => get_option( 'comment_moderation' ),
			'comment_previously_approved' => get_option( 'comment_previously_approved' ),
			'moderation_keys'             => get_option( 'moderation_keys' ),
		];

		update_option( 'comment_moderation', '0' );
		update_option( 'comment_previously_approved', '1' );
		update_option( 'moderation_keys', 'forbiddenword' );

		try {
			list( , $clean_comment_id )   = $this->createApprovedSubscriberComment();
			list( , $flagged_comment_id ) = $this->createApprovedSubscriberComment();

			wp_set_current_user( $this->subscriber );

			$query = '
			mutation updateComment( $id: ID!, $content: String! ) {
				updateComment( input: { id: $id, content: $content } ) {
					success
				}
			}
			';

			$clean = $this->graphql(
				[
					'query'     => $query,
					'variables' => [
						'id'      => $clean_comment_id,
						'content' => 'An ordinary edit to my own comment',
					],
				]
			);

			$this->assertArrayNotHasKey( 'errors', $clean, 'An ordinary content edit must be allowed.' );
			$this->assertEquals( '1', get_comment( $clean_comment_id )->comment_approved, 'An ordinary content edit must keep its approved status.' );

			$flagged = $this->graphql(
				[
					'query'     => $query,
					'variables' => [
						'id'      => $flagged_comment_id,
						'content' => 'An edit containing a forbiddenword',
					],
				]
			);

			$this->assertArrayNotHasKey( 'errors', $flagged, 'The edit itself is allowed, it is the approval that is withdrawn.' );
			$this->assertEquals( '0', get_comment( $flagged_comment_id )->comment_approved, 'Content that fails moderation must be held for review.' );
			$this->assertEquals( 'An edit containing a forbiddenword', get_comment( $flagged_comment_id )->comment_content, 'The edited content must be saved.' );
		} finally {
			foreach ( $previous as $option => $value ) {
				update_option( $option, $value );
			}
		}
	}

	/**
	 * Sites that treat their comment owners as trusted can keep the old behavior.
	 */
	public function testUpdateCommentOwnerContentEditKeepsApprovalWhenFilterSaysSo() {
		$previous_keys = get_option( 'moderation_keys' );
		update_option( 'moderation_keys', 'forbiddenword' );

		$filter_ran    = false;
		$keep_approval = static function ( $requires_moderation ) use ( &$filter_ran ) {
			$filter_ran = true;

			// The comment would have been held without the filter.
			\PHPUnit\Framework\Assert::assertTrue( $requires_moderation );

			return false;
		};
		add_filter( 'graphql_comment_update_requires_moderation', $keep_approval );

		try {
			list( , $comment_id ) = $this->createApprovedSubscriberComment();

			wp_set_current_user( $this->subscriber );

			$actual = $this->graphql(
				[
					'query'     => '
					mutation updateComment( $id: ID!, $content: String! ) {
						updateComment( input: { id: $id, content: $content } ) {
							success
						}
					}
					',
					'variables' => [
						'id'      => $comment_id,
						'content' => 'An edit containing a forbiddenword',
					],
				]
			);

			$this->assertArrayNotHasKey( 'errors', $actual );
			$this->assertTrue( $filter_ran, 'The filter must be applied before the comment is held.' );
			$this->assertEquals( '1', get_comment( $comment_id )->comment_approved, 'The filter must be able to keep the comment approved.' );
		} finally {
			remove_filter( 'graphql_comment_update_requires_moderation', $keep_approval );
			update_option( 'moderation_keys', $previous_keys );
		}
	}

	/**
	 * Regression guard: the restrictions apply to callers without `moderate_comments` only.
	 */
	public function testUpdateCommentModeratorCanChangeTargetAndAuthor() {
		list( , $comment_id ) = $this->createApprovedSubscriberComment();

		$target_post_id = $this->factory()->post->create(
			[
				'post_author'    => $this->admin,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_title'     => 'Post a moderator is moving the comment to',
				'comment_status' => 'closed',
			]
		);

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => '
				mutation moveComment( $id: ID!, $commentOn: Int!, $author: String! ) {
					updateComment( input: { id: $id, commentOn: $commentOn, author: $author } ) {
						success
					}
				}
				',
				'variables' => [
					'id'        => $comment_id,
					'commentOn' => $target_post_id,
					'author'    => 'Renamed By A Moderator',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual, 'A moderator must still be able to move a comment and rename its author.' );

		$comment = get_comment( $comment_id );
		$this->assertEquals( $target_post_id, (int) $comment->comment_post_ID, 'The moderator move must be saved.' );
		$this->assertEquals( 'Renamed By A Moderator', $comment->comment_author, 'The moderator rename must be saved.' );
	}

	/**
	 * `wp_update_comment()` rejects a `comment_post_ID` that holds nothing, so this is a guard on
	 * WordPress's behavior rather than on anything WPGraphQL checks itself.
	 */
	public function testUpdateCommentCannotMoveCommentToANodeThatDoesNotExist() {
		list( $post_id, $comment_id ) = $this->createApprovedSubscriberComment();

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => '
				mutation moveComment( $id: ID!, $commentOn: Int! ) {
					updateComment( input: { id: $id, commentOn: $commentOn } ) {
						success
					}
				}
				',
				'variables' => [
					'id'        => $comment_id,
					'commentOn' => 99999999,
				],
			]
		);

		$this->assertArrayHasKey( 'errors', $actual, 'A comment must not be movable to a node that does not exist.' );
		$this->assertEquals( $post_id, (int) get_comment( $comment_id )->comment_post_ID, 'The comment must still be on the post it was left on.' );
	}

	/**
	 * WPGraphQL lets a comment's author edit it, which WordPress does not. A site that wants
	 * WordPress's rule can turn the ownership path off.
	 */
	public function testUpdateCommentOwnerEditsCanBeTurnedOffByFilter() {
		list( , $comment_id ) = $this->createApprovedSubscriberComment();

		$deny_owner_edits = static function () {
			return false;
		};
		add_filter( 'graphql_comment_author_can_update_own_comment', $deny_owner_edits );

		$query = '
		mutation updateComment( $id: ID!, $content: String! ) {
			updateComment( input: { id: $id, content: $content } ) {
				success
			}
		}
		';

		try {
			wp_set_current_user( $this->subscriber );

			$actual = $this->graphql(
				[
					'query'     => $query,
					'variables' => [
						'id'      => $comment_id,
						'content' => 'An edit the site does not want to allow',
					],
				]
			);

			$this->assertArrayHasKey( 'errors', $actual, 'The filter must be able to deny an owner edit.' );
			$this->assertEquals( 'Content a moderator approved', get_comment( $comment_id )->comment_content, 'The content must be unchanged.' );

			// A moderator is not affected by the filter.
			wp_set_current_user( $this->admin );

			$actual = $this->graphql(
				[
					'query'     => $query,
					'variables' => [
						'id'      => $comment_id,
						'content' => 'An edit by a moderator',
					],
				]
			);

			$this->assertArrayNotHasKey( 'errors', $actual, 'A moderator must still be able to update the comment.' );
		} finally {
			remove_filter( 'graphql_comment_author_can_update_own_comment', $deny_owner_edits );
		}
	}
}
