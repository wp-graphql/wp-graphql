<?php

class CommentMutationsTest extends \Codeception\TestCase\WPTestCase {
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
	}


	public function tearDown(): void {
		// your tear down methods here

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

		wp_set_current_user( $this->admin );

		$mutation  = '
		mutation createCommentTest( $clientMutationId:String!, $commentOn:Int!, $author:String!, $email: String!, $content:String! ){
		  createComment( 
		    input: {
		      clientMutationId: $clientMutationId
		      commentOn: $commentOn
              content: $content
              author: $author
              authorEmail: $email
		    }
          )
          {
		    clientMutationId
		    comment {
              content
		    }
          }
        }
		';
		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'commentOn'        => $post_id,
			'content'          => $this->content,
			'author'           => 'Comment Author',
			'email'            => 'subscriber@example.com',
		] );

		$actual = do_graphql_request( $mutation, 'createCommentTest', $variables );

		$expected = [
			'createComment' => [
				'clientMutationId' => $this->client_mutation_id,
				'comment'          => [
					'content'  => apply_filters( 'comment_text', $this->content ),
				],
			],
		];

		/**
		 * use --debug flag to view
		 */
		codecept_debug( $actual );

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );
		$count = wp_count_comments( $post_id );
		$this->assertEquals( '1', $count->total_comments );
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

		$content   = 'Updated Content';
		$mutation  = '
		mutation updateCommentTest( $clientMutationId: String!, $id: ID!, $content: String! ) {
		  updateComment( 
		    input: {
		      clientMutationId: $clientMutationId
              id: $id
              content: $content
		    }
          )
          {
		    clientMutationId
		    comment {
              id
              commentId
              content
		    }
          }
        }
		';
		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
			'content'          => $content,
		] );

		$actual = do_graphql_request( $mutation, 'updateCommentTest', $variables );

		$expected = [
			'updateComment' => [
				'clientMutationId' => $this->client_mutation_id,
				'comment'          => [
					'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'commentId' => $comment_id,
					'content'   => apply_filters( 'comment_text', $content ),
				],
			],
		];

		/**
		 * use --debug flag to view
		 */
		codecept_debug( $actual );

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

		$mutation = '
		mutation deleteCommentTest( $clientMutationId: String!, $id: ID! ) {
		  deleteComment( 
		    input: {
		      clientMutationId: $clientMutationId
              id: $id
		    }
          )
          {
            clientMutationId
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
			'clientMutationId' => $this->client_mutation_id,
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
		];

		$actual = do_graphql_request( $mutation, 'deleteCommentTest', $variables );

		$expected = [
			'deleteComment' => [
				'clientMutationId' => $this->client_mutation_id,
				'deletedId'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'          => [
					'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'commentId' => $comment_id,
					'content'   => apply_filters( 'comment_text', $content ),
				],
			],
		];

		/**
		 * use --debug flag to view
		 */
		\Codeception\Util\Debug::debug( $actual );

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

		$mutation = '
		mutation restoreCommentTest( $clientMutationId: String!, $id: ID! ) {
		  restoreComment( 
		    input: {
		      clientMutationId: $clientMutationId
              id: $id
		    }
          )
          {
            clientMutationId
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
			'clientMutationId' => $this->client_mutation_id,
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
		];

		wp_set_current_user( $this->admin );

		$actual = do_graphql_request( $mutation, 'restoreCommentTest', $variables );

		$expected = [
			'restoreComment' => [
				'clientMutationId' => $this->client_mutation_id,
				'restoredId'       => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
				'comment'          => [
					'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'commentId' => $comment_id,
					'content'   => apply_filters( 'comment_text', $content ),
				],
			],
		];

		/**
		 * use --debug flag to view
		 */
		\Codeception\Util\Debug::debug( $actual );

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

		$mutation  = '
		mutation createCommentTest( $clientMutationId:String!, $commentOn:Int!, $author:String!, $email: String!, $content:String! ){
		  createComment(
		    input: {
		      clientMutationId: $clientMutationId
		      commentOn: $commentOn
		      content: $content
		      author: $author
		      authorEmail: $email
		    }
		  )
		  {
		    clientMutationId
		    comment {
		      content
		    }
		  }
		}
		';

		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'commentOn'        => $post_id,
			'content'          => $this->content,
			'author'           => 'Comment Author',
			'email'            => 'subscriber@example.com',
		] );

		$actual = do_graphql_request( $mutation, 'createCommentTest', $variables );

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

		$mutation  = '
		mutation createCommentTest( $clientMutationId:String!, $commentOn:Int!, $author:String!, $email: String!, $content:String! ){
		  createComment(
		    input: {
		      clientMutationId: $clientMutationId
		      commentOn: $commentOn
		      content: $content
		      author: $author
		      authorEmail: $email
		    }
		  )
		  {
		    clientMutationId
		    success
		  }
		}
		';

		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'commentOn'        => $post_id,
			'content'          => $this->content,
			'author'           => 'Comment Author',
			'email'            => 'subscriber@example.com',
		] );

		wp_set_current_user( 0 );

		$actual = do_graphql_request( $mutation, 'createCommentTest', $variables );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['createComment']['success'] );

	}
}
