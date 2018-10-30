<?php

class CommentMutationsTest extends \Codeception\TestCase\WPTestCase
{
    public $title;
	public $content;
	public $client_mutation_id;
	public $admin;
	public $subscriber;
	public $author;

	public function setUp() {
		// before
		parent::setUp();

		$this->title             = 'some title';
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


	public function tearDown() {
		// your tear down methods here

		// then
		parent::tearDown();
    }

    public function createComment( &$post_id, &$comment_id, $postCreator, $commentCreator )
    {
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
        $user = wp_get_current_user();
        $comment_args = [
            'user_id' => $user->ID,
            'comment_author' => $user->display_name,
			'comment_author_url' => $user->user_url,
            'comment_post_ID' => $post_id,
			'comment_content' => 'Comment Content',
        ];

        /**
		 * Create a comment to test against
		 */
        $comment_id = $this->factory()->comment->create( $comment_args );
    }

    public function trashComment( &$comment_id )
    {
        wp_trash_comment( $comment_id );
    }

    // tests
    public function testCreateComment()
    {
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
        
        $mutation = '
		mutation createCommentTest( $clientMutationId:String!, $postId:Int!, $author:String!, $email: String!, $content:String!, $ip:String ){
		  createComment( 
		    input: {
		      clientMutationId: $clientMutationId
		      postId: $postId
              content: $content
              author: $author
              authorEmail: $email
              authorIp: $ip
		    }
          )
          {
		    clientMutationId
		    comment {
              content
              authorIp
		    }
          }
        }
		';
		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'postId'           => $post_id,
            'content'          => $this->content,
            'author'           => 'Comment Author',
            'email'            => 'subscriber@example.com',
            'ip'               => ':1',
        ] );
        
        $actual = do_graphql_request( $mutation, 'createCommentTest', $variables );


        
        $expected = [
			'data' => [
				'createComment' => [
					'clientMutationId' => $this->client_mutation_id,
					'comment'             => [
						'content' => apply_filters( 'comment_text', $this->content ),
                        'authorIp'=> ':1',
					],
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
        $this->assertEquals( $expected, $actual );
        $count = wp_count_comments( $post_id );
        $this->assertEquals( '1', $count->total_comments );
    }

    public function testUpdateCommentWithAuthorConnection()
    {
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
        $mutation = '
		mutation updateCommentTest( $clientMutationId: String!, $id: ID!, $content: String!, $ip: String ) {
		  updateComment( 
		    input: {
		      clientMutationId: $clientMutationId
              id: $id
              content: $content
              authorIp: $ip
		    }
          )
          {
		    clientMutationId
		    comment {
              id
              commentId
              content
              authorIp
		    }
          }
        }
		';
		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
            'id'        => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
            'content'          => $content,
            'ip'               => ':2',
        ] );
        
        $actual = do_graphql_request( $mutation, 'updateCommentTest', $variables );
        
        $expected = [
			'data' => [
				'updateComment' => [
					'clientMutationId' => $this->client_mutation_id,
					'comment' => [
                        'id'          => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
                        'commentId'   => $comment_id,
                        'content'     => apply_filters( 'comment_text', $content ),
                        'authorIp'    => ':2',
                    ],
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
        $this->assertEquals( $expected, $actual );
    }

    public function testDeleteCommentWithPostConnection()
    {
        $this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
        $new_post = $this->factory()->post->get_object_by_id( $post_id );
        
        $this->assertEquals( $new_post->comment_count, '1' );
        $this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post Title' );
        $this->assertEquals( $new_post->post_content, 'Post Content' );
        
        $new_comment = $this->factory()->comment->get_object_by_id( $comment_id );
        $content = 'Comment Content';
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
            'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
		];

        $actual = do_graphql_request( $mutation, 'deleteCommentTest', $variables );
        
        $expected = [
			'data' => [
				'deleteComment' => [
                    'clientMutationId' => $this->client_mutation_id,
                    'deletedId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'comment' => [
                        'id'          => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
                        'commentId'   => $comment_id,
                        'content'     => apply_filters( 'comment_text', $content ),
                    ],
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
        $this->assertEquals( $expected, $actual );
    }

    public function testUntrashComment()
    {
        $this->createComment( $post_id, $comment_id, $this->author, $this->subscriber );
        $new_post = $this->factory()->post->get_object_by_id( $post_id );
        
        $this->assertEquals( $new_post->comment_count, '1' );
        $this->assertEquals( $new_post->post_type, 'post' );
		$this->assertEquals( $new_post->post_title, 'Post Title' );
        $this->assertEquals( $new_post->post_content, 'Post Content' );
        
        $new_comment = $this->factory()->comment->get_object_by_id( $comment_id );
        $content = 'Comment Content';
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
            'id' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
        ];
        
        wp_set_current_user( $this->admin );

        $actual = do_graphql_request( $mutation, 'restoreCommentTest', $variables );
        
        $expected = [
			'data' => [
				'restoreComment' => [
                    'clientMutationId' => $this->client_mutation_id,
                    'restoredId' => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
					'comment' => [
                        'id'          => \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id ),
                        'commentId'   => $comment_id,
                        'content'     => apply_filters( 'comment_text', $content ),
                    ],
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
        $this->assertEquals( $expected, $actual );
    }
}