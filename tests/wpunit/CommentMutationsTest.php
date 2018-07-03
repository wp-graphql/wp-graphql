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

    // tests
    public function testMe()
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
        $post_id = $this->factory()->post->create($args);

        $new_post = $this->factory()->post->get_object_by_id($post_id);
        
        $this->assertEquals($new_post->comment_count, 0);
        $this->assertEquals($new_post->post_type, 'post');
		$this->assertEquals($new_post->post_title, 'Original Title');
		$this->assertEquals($new_post->post_content, 'Original Content');
        
        $mutation = '
		mutation createComment( $clientMutationId:String!, $postId:ID!, $author:String!, $content:String!, $ip:String){
		  createComment(
		    input:{
		      clientMutationId:$clientMutationId,
		      postId:$postId
              content:$content
              author:$author
              authorIp:$ip
		    }
		  ){
		    clientMutationId
		    comment{
                content
                authorIp
		    }
		  }
		}
		';
		$variables = wp_json_encode([
			'clientMutationId' => $this->client_mutation_id,
			'postId'           => $post_id,
            'content'          => $this->content,
            'author'           => 'Comment Author',
            'ip'         => ':1',
        ]);
        
        wp_set_current_user($this->subscriber);
        
        $actual = do_graphql_request($mutation, 'createComment', $variables);
        
        $expected = [
			'data' => [
				'createComment' => [
					'clientMutationId' => $this->client_mutation_id,
					'comment'             => [
						'content' => apply_filters('the_content', $this->content),
                        'authorIp'=> ':1',
					],
				],
			],
		];

        \Codeception\Util\Debug::debug($actual);

		/**
		 * Compare the actual output vs the expected output
		 */
        $this->assertEquals($actual, $expected);
        $updated_post = $this->factory()->post->get_object_by_id($post_id);
        $this->assertEquals($updated_post->comment_count, 1);

    }

}