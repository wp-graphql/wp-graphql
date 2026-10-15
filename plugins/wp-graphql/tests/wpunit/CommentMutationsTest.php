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
	 * Runs createComment anonymously against a post and returns the error message,
	 * or null when the comment was accepted.
	 *
	 * @param int $post_id The post to comment on.
	 */
	protected function anonymousCommentError( int $post_id ): ?string {
		update_option( 'comment_registration', '0' );
		wp_set_current_user( 0 );

		$actual = $this->graphql(
			[
				'query'     => '
				mutation ( $commentOn: Int! ) {
					createComment( input: {
						commentOn: $commentOn
						content: "probe"
						author: "Probe"
						authorEmail: "probe@example.com"
					} ) { success }
				}
				',
				'variables' => [ 'commentOn' => $post_id ],
			]
		);

		return $actual['errors'][0]['message'] ?? null;
	}

	/**
	 * Hardening for GHSA-2f5m-v898-fmjx and GHSA-4vgv-vvxr-9fh9. createComment
	 * checked only `comment_status`, so an anonymous caller could comment on a
	 * draft, a private post, a trashed post or a password-protected one. Each
	 * comment also triggers wp_notify_moderator(), whose subject carries the post
	 * title, so a comment on a private post mailed that title to the moderator.
	 *
	 * The conditions mirror wp_handle_comment_submission().
	 *
	 * @dataProvider dataPostsThatCannotBeCommentedOn
	 *
	 * @param string              $status      The post status to create.
	 * @param array<string,mixed> $extra       Extra arguments for the post.
	 * @param bool                $trash_first Whether to trash the post before commenting.
	 */
	public function testAnonymousCannotCommentOnPostsItCannotRead( string $status, array $extra, bool $trash_first ) {
		$post_id = $this->factory()->post->create(
			array_merge(
				[
					'post_type'      => 'post',
					'post_status'    => $status,
					'post_title'     => 'Title that must not leak',
					'post_author'    => $this->admin,
					'comment_status' => 'open',
				],
				$extra
			)
		);

		if ( $trash_first ) {
			wp_trash_post( $post_id );
		}

		$error = $this->anonymousCommentError( $post_id );

		$this->assertSame(
			'The ID of the node to comment on is invalid',
			$error,
			'A post the caller cannot read must give the same answer as one that does not exist'
		);
		$comment_count = (int) get_comments(
			[
				'post_id' => $post_id,
				'count'   => true,
			]
		);

		$this->assertSame( 0, $comment_count, 'No comment may be created' );
	}

	/**
	 * @return array<string,array{0:string,1:array<string,mixed>,2:bool}>
	 */
	public function dataPostsThatCannotBeCommentedOn(): array {
		return [
			'draft'              => [ 'draft', [], false ],
			'pending'            => [ 'pending', [], false ],
			'private'            => [ 'private', [], false ],
			'trashed'            => [ 'publish', [], true ],
			'password protected' => [ 'publish', [ 'post_password' => 'hunter2' ], false ],
		];
	}

	/**
	 * The refusal must not say which kind of post it was, or the mutation becomes a
	 * way to map which IDs exist and what state they are in.
	 */
	public function testRefusalIsIndistinguishableFromANonexistentPost() {
		$draft = $this->factory()->post->create(
			[
				'post_status'    => 'draft',
				'post_author'    => $this->admin,
				'comment_status' => 'open',
			]
		);

		$this->assertSame(
			$this->anonymousCommentError( 999999999 ),
			$this->anonymousCommentError( $draft ),
			'A draft and an ID that does not exist must be indistinguishable'
		);
	}

	/**
	 * The guard must not over-block. Someone who can read the post is told why
	 * comments are refused, and can still comment where WordPress allows it.
	 */
	public function testCapableUserIsToldWhyAndCanStillComment() {
		wp_set_current_user( $this->admin );
		update_option( 'comment_registration', '0' );

		$draft = $this->factory()->post->create(
			[
				'post_status'    => 'draft',
				'post_author'    => $this->admin,
				'comment_status' => 'open',
			]
		);

		$private = $this->factory()->post->create(
			[
				'post_status'    => 'private',
				'post_author'    => $this->admin,
				'comment_status' => 'open',
			]
		);

		$mutation = '
		mutation ( $commentOn: Int! ) {
			createComment( input: { commentOn: $commentOn, content: "probe" } ) { success }
		}
		';

		$on_draft = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [ 'commentOn' => $draft ],
			]
		);
		$this->assertSame(
			'Sorry, comments are not allowed for this item',
			$on_draft['errors'][0]['message'] ?? null,
			'A user who can read the draft is told why, as WordPress does'
		);

		$on_private = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [ 'commentOn' => $private ],
			]
		);
		$this->assertArrayNotHasKey( 'errors', $on_private, 'A user who can read a private post may comment on it' );
	}

	/**
	 * Hardening for GHSA-4vgv-vvxr-9fh9. `date` and `type` were taken from input
	 * with no capability check, so any caller could backdate a comment or file it
	 * as another type. Core draws the same line with `moderate_comments`.
	 */
	public function testDateAndTypeRequireModerateComments() {
		$post_id = $this->factory()->post->create(
			[
				'post_status'    => 'publish',
				'post_author'    => $this->admin,
				'comment_status' => 'open',
			]
		);

		$mutation = '
		mutation ( $commentOn: Int! ) {
			createComment( input: {
				commentOn: $commentOn
				content: "probe"
				date: "2001-01-01 00:00:00"
				type: "pingback"
			} ) { comment { databaseId } }
		}
		';

		update_option( 'comment_registration', '0' );

		wp_set_current_user( $this->subscriber );
		$actual  = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [ 'commentOn' => $post_id ],
			]
		);
		$comment = get_comment( $actual['data']['createComment']['comment']['databaseId'] );

		$this->assertNotSame( '2001-01-01 00:00:00', $comment->comment_date, 'A subscriber must not be able to backdate a comment' );
		$this->assertNotSame( 'pingback', $comment->comment_type, 'A subscriber must not be able to choose the comment type' );

		wp_set_current_user( $this->admin );
		$actual  = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [ 'commentOn' => $post_id ],
			]
		);
		$comment = get_comment( $actual['data']['createComment']['comment']['databaseId'] );

		$this->assertSame( '2001-01-01 00:00:00', $comment->comment_date, 'A moderator may still set the date' );
		$this->assertSame( 'pingback', $comment->comment_type, 'A moderator may still set the type' );
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

}
