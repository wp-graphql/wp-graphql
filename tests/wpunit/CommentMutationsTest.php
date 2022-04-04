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

		$this->author = $this->factory()->user->create( [
			'role' => 'author',
		] );

		$this->admin = $this->factory()->user->create( [
			'role' => 'administrator',
		] );

		$this->subscriber = $this->factory()->user->create( [
			'role' => 'subscriber',
		] );

		WPGraphQL::clear_schema();
	}


	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	public function testCreateCommentByLoggedInUserShouldSetUserProperly() {

		$post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test for comments...'
		]);

		$query = '
		mutation createComment($input: CreateCommentInput!) {
		  createComment(input: $input) {
		    clientMutationId
		    success
		    comment {
		      id
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
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'Create...',
				'content' => 'Test comment ' . uniqid(),
				'commentOn' => $post_id
			]
		];

		wp_set_current_user( $this->admin );

		$actual = graphql([
			'query' => $query,
			'variables' => $variables,
		]);


		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertSame( $this->admin, $actual['data']['createComment']['comment']['author']['node']['databaseId'] );

		add_filter( 'comment_flood_filter', '__return_false' );

		wp_set_current_user( 0 );

		$variables['input']['author'] = 'joe';
		$variables['input']['authorEmail'] = 'joe@example.com';

		sleep(1);
		$actual = graphql([
			'query' => $query,
			'variables' => $variables,
		]);


		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );

	}

	public function createComment( &$post_id, &$comment_id, $postCreator, $commentCreator ) {
		wp_set_current_user( $postCreator );
		$post_args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Post Title',
			'post_content' => 'Post Content',
		];

		/**
		 * Create a page to test against
		 */
		$post_id = $this->factory()->post->create( $post_args );

		wp_set_current_user( $commentCreator );
		$user         = wp_get_current_user();
		$comment_args = [
			'user_id'            => $user->ID,
			'comment_author'     => $user->display_name,
			'comment_author_url' => $user->user_url,
			'comment_post_ID'    => $post_id,
			'comment_content'    => 'Comment Content',
		];

		/**
		 * Create a comment to test against
		 */
		$comment_id = $this->factory()->comment->create( $comment_args );
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
			'post_title'   => 'Original Title',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against
		 */
		$post_id = $this->factory()->post->create( $args );

		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '0' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Original Title' );
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

		$count = wp_count_comments( $post_id );
		$this->assertEquals( '1', $count->total_comments );

		// Test logged in user without `moderate_comments`.
		wp_set_current_user( $this->subscriber );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );
		$this->assertEquals( $this->subscriber, $actual['data']['createComment']['comment']['author']['node']['databaseId'] );

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

	public function testUpdateCommentWithAuthorConnection() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );

		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '1' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post Title' );
		$this->assertEquals( $new_post->post_content, 'Post Content' );

		$new_comment = $this->factory()->comment->get_object_by_id( $comment_id );

		$this->assertEquals( $new_comment->user_id, get_current_user_id() );
		$this->assertEquals( $new_comment->comment_post_ID, $post_id );
		$this->assertEquals( $new_comment->comment_content, 'Comment Content' );

		$content = 'Updated Content';

		$query     = '
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
					commentId
					content
				}
			}
		}
		';
		$variables = [
			'id'      => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
			'content' => $content,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'updateComment' => [
				'comment' => [
					'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'commentId' => $comment_id,
					'content'   => apply_filters( 'comment_text', $content ),
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testDeleteCommentWithPostConnection() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '1' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post Title' );
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
					commentId
					content
				}
			}
		}
		';

		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'deleteComment' => [
				'deletedId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'   => [
					'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'commentId' => $comment_id,
					'content'   => apply_filters( 'comment_text', $content ),
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testRestoreComment() {
		$this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
		$new_post = $this->factory()->post->get_object_by_id( $post_id );

		$this->assertEquals( $new_post->comment_count, '1' );
		$this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post Title' );
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
					commentId
					content
				}
			}
		}
		';

		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
		];

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'restoreComment' => [
				'restoredId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'    => [
					'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'commentId' => $comment_id,
					'content'   => apply_filters( 'comment_text', $content ),
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Make sure that we can't leave a comment if we are not logged in and the comment registration
	 * flag is set
	 */
	public function testCantCreateCommentNotLoggedIn() {

		$args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title',
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
		$this->assertEquals( $new_post->post_title, 'Original Title' );
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
			'post_title'   => 'Original Title',
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
		$this->assertEquals( $new_post->post_title, 'Original Title' );
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
}
