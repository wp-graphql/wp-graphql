<?php

/**
 * WPGraphQL Test Post Object Mutations
 * This tests post mutations checking to see if the available fields return the expected response
 *
 * @package WPGraphQL
 */
class WP_GraphQL_Test_Post_Object_Mutations extends WP_UnitTestCase {

	public $title;
	public $content;
	public $client_mutation_id;
	public $admin;
	public $subscriber;
	public $author;
	public $file_path;
	public $file_type;
	public $alt_text;

	/**
	 * This function is run before each method
	 */
	public function setUp() {

		$this->title              = 'some title';
		$this->content            = 'some content';
		$this->client_mutation_id = 'someUniqueId';
		$this->file_path          = 'http://www.reactiongifs.com/r/mgc.gif';
		$this->file_type          = 'IMAGE_GIF';
		$this->alt_text           = 'alternative text';

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

		parent::setUp();

	}

	/**
	 * Runs after each method.
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * This processes a mutation to create a post
	 *
	 * @return array
	 */
	public function createPostMutation() {

		$mutation = '
		mutation createPost( $clientMutationId:String!, $title:String!, $content:String! ){
		  createPost(
		    input:{
		      clientMutationId:$clientMutationId,
		      title:$title
		      content:$content
		    }
		  ){
		    clientMutationId
		    post{
		      title
		      content
		    }
		  }
		}
		';

		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'title'            => $this->title,
			'content'          => $this->content,
		] );

		$actual = do_graphql_request( $mutation, 'createPost', $variables );

		return $actual;

	}

	public function createPageMutation() {

		$mutation = '
		mutation createPage( $clientMutationId:String!, $title:String!, $content:String! ){
		  createPage(
		    input:{
		      clientMutationId:$clientMutationId,
		      title:$title
		      content:$content
		    }
		  ){
		    clientMutationId
		    page{
		      title
		      content
		    }
		  }
		}
		';

		$variables = wp_json_encode( [
			'clientMutationId' => $this->client_mutation_id,
			'title'            => $this->title,
			'content'          => $this->content,
		] );

		$actual = do_graphql_request( $mutation, 'createPage', $variables );

		return $actual;

	}

	public function testUpdatePageMutation() {

		$args = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against
		 */
		$page_id = $this->factory->post->create( $args );

		/**
		 * Get the new page object
		 */
		$new_page = get_post( $page_id );

		/**
		 * Verify the page was created with the original content as expected
		 */
		$this->assertEquals( $new_page->post_type, 'page' );
		$this->assertEquals( $new_page->post_title, 'Original Title' );
		$this->assertEquals( $new_page->post_content, 'Original Content' );

		/**
		 * Prepare the mutation
		 */
		$mutation = '
		mutation updatePageTest( $clientMutationId:String! $id:ID! $title:String $content:String ){
		  updatePage(
		    input: {
		        clientMutationId:$clientMutationId
		        id:$id,
		        title:$title,
		        content:$content,
		    }
		  ) {
		    clientMutationId
		    page{
		      id
		      title
		      content
		      pageId
		    }
		  }
		}';

		/**
		 * Set the variables to use with the mutation
		 */
		$variables = wp_json_encode( [
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
			'title'            => 'Some updated title',
			'content'          => 'Some updated content',
			'clientMutationId' => 'someId',
		] );

		/**
		 * Set the current user as the subscriber so we can test, and expect to fail
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'updatePageTest', $variables );

		/**
		 * We should get an error because the user is a subscriber and can't edit posts
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the current user to a user with permission to edit posts, but NOT permission to edit OTHERS posts
		 */
		wp_set_current_user( $this->author );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'updatePageTest', $variables );

		/**
		 * We should get an error because the user is an and can't edit others posts
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the current user as the admin role so we
		 * successfully run the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'updatePageTest', $variables );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'data' => [
				'updatePage' => [
					'clientMutationId' => 'someId',
					'page'             => [
						'id'      => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
						'title'   => 'Some updated title',
						'content' => apply_filters( 'the_content', 'Some updated content' ),
						'pageId'  => $page_id,
					],
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );

	}

	public function testDeletePageMutation() {

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->subscriber );

		$args = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against
		 */
		$page_id = $this->factory->post->create( $args );

		/**
		 * Get the new page object
		 */
		$new_page = get_post( $page_id );

		/**
		 * Verify the page was created with the original content as expected
		 */
		$this->assertEquals( $new_page->post_type, 'page' );
		$this->assertEquals( $new_page->post_title, 'Original Title' );
		$this->assertEquals( $new_page->post_content, 'Original Content' );

		/**
		 * Prepare the mutation
		 */
		$mutation = '
		mutation deletePageTest($input:deletePageInput!){
		  deletePage(input:$input){
		    clientMutationId
		    deletedId
		    page{
		      id
		      title
		      content
		      pageId
		    }
		  }
		}';

		/**
		 * Set the variables to use with the mutation
		 */
		$variables = [
			'input' => [
				'id'               => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
				'clientMutationId' => 'someId',
			],
		];


		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );

		/**
		 * The deletion should fail because we're a subscriber
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the user to an admin and try again
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'data' => [
				'deletePage' => [
					'clientMutationId' => 'someId',
					'deletedId'        => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
					'page'             => [
						'id'      => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
						'title'   => 'Original Title',
						'content' => apply_filters( 'the_content', 'Original Content' ),
						'pageId'  => $page_id,
					],
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );

		/**
		 * Try to delete again
		 */
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );

		/**
		 * We should get an error because we're not using forceDelete
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Try to delete again, this time with forceDelete
		 */
		$variables = [
			'input' => [
				'id'               => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
				'clientMutationId' => 'someId',
				'forceDelete' => true,
			],
		];
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );


		/**
		 * This time, we used forceDelete so the mutation should have succeeded
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'someId', $actual['data']['deletePage']['clientMutationId'] );
		$this->assertEquals( \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ), $actual['data']['deletePage']['deletedId'] );

		/**
		 * Try to delete the page one more time, and now there's nothing to delete, not even from the trash
		 */
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );

		/**
		 * Now we should have errors again, because there's nothing to be deleted
		 */
		$this->assertArrayHasKey( 'errors', $actual );


	}

	/**
	 * This processes a mutation to create a mediaItem (attachment)
	 *
	 * @return array
	 */
	public function createMediaItemMutation() {

		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
		      title
		      description
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'filePath'         => $this->file_path,
				'fileType'         => $this->file_type,
				'clientMutationId' => $this->client_mutation_id,
				'title'            => $this->title,
				'description'      => $this->content,
				'altText'          => $this->alt_text,
			],
		];

		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );

		return $actual;

	}

	/**
	 * This processes a mutation to create a mediaItem (attachment)
	 *
	 * @return array
	 */
	public function createMediaItemMutationForUpdates() {

		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
		      id
		      title
		      description
		      mediaItemId
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'filePath'         => $this->file_path,
				'fileType'         => $this->file_type,
				'clientMutationId' => $this->client_mutation_id,
				'title'            => $this->title,
				'description'      => $this->content,
				'altText'          => $this->alt_text,
			],
		];

		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );

		return $actual;

	}

	public function testUpdateMediaItemMutation() {

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create a mediaItem to test against
		 */
		$media_item = $this->createMediaItemMutationForUpdates();

		$media_item_id = $media_item["data"]["createMediaItem"]["mediaItem"]["id"];

		$attachment_id = $media_item["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];

		$new_attachment = get_post( $attachment_id );

		/**
		 * Verify the page was created with the original content as expected
		 */
		$this->assertEquals( $new_attachment->post_type, 'attachment' );
		$this->assertEquals( $new_attachment->post_title, 'some title' );
		$this->assertEquals( $new_attachment->post_content, 'some content' );

		/**
		 * Prepare the mutation
		 */
		$mutation = '
		mutation updateMediaItem( $input: updateMediaItemInput! ){
		  updateMediaItem (input: $input){
		    clientMutationId
		    mediaItem {
		      id
		      title
		      description
		      mediaItemId
		      altText
		    }
		  }
		}
		';

		/**
		 * Set the variables to use with the mutation
		 */
		$variables = [
			'input' => [
				'id'               => $media_item_id,
				'title'            => 'Some updated title',
				'description'      => 'Some updated content',
				'clientMutationId' => 'someId',
				'altText'          => 'Some updated alt text'
			]
		];

		/**
		 * Set the current user as the subscriber so we can test, and expect to fail
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );

		/**
		 * We should get an error because the user is a subscriber and can't edit posts
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the current user to a user with permission to edit posts, but NOT permission to edit OTHERS posts
		 */
		wp_set_current_user( $this->author );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );

		/**
		 * We should get an error because the user is an and can't edit others posts
		 */
		$this->assertArrayHasKey( 'errors', $actual );


		/**
		 * Set the current user as the admin role so we
		 * successfully run the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'data' => [
				'updateMediaItem' => [
					'clientMutationId' => 'someId',
					'mediaItem'             => [
						'id'               => $media_item_id,
						'title'            => 'Some updated title',
						'description'      => apply_filters( 'the_content', 'Some updated content' ),
						'mediaItemId'      => $attachment_id,
						'altText'          => 'Some updated alt text',
					],
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );

	}

	public function testDeleteMediaItemMutation() {

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create a mediaItem to test against
		 */
		$media_item = $this->createMediaItemMutationForUpdates();

		$media_item_id = $media_item["data"]["createMediaItem"]["mediaItem"]["id"];

		$attachment_id = $media_item["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];

		$new_attachment = get_post( $attachment_id );

		/**
		 * Verify the mediaItem was created with the original content as expected
		 */
		$this->assertEquals( $new_attachment->post_type, 'attachment' );
		$this->assertEquals( $new_attachment->post_title, 'some title' );
		$this->assertEquals( $new_attachment->post_content, 'some content' );


		/**
		 * Prepare the mutation
		 */
		$mutation = '
		mutation deleteMediaItem( $input: deleteMediaItemInput! ){
		  deleteMediaItem(input: $input) {
		    clientMutationId
		    mediaItem{
		      id
		      title
		      mediaItemId
		    }
		  }
		}
		';

		/**
		 * Set the variables to use with the mutation
		 */
		$variables = [
			'input' => [
				'id'               => $media_item_id,
				'clientMutationId' => 'someId',
				'forceDelete'      => true,
			]
		];


		/**
		 * Set the current user as the subscriber role
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );

		/**
		 * The deletion should fail because we're a subscriber
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the user to an admin and try again
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'data' => [
				'deleteMediaItem' => [
					'clientMutationId' => 'someId',
					'mediaItem' => [
						'id'               => $media_item_id,
						'title'            => 'some title',
						'mediaItemId'      => $attachment_id,
					],
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );

		/**
		 * Try to delete again
		 */
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );

		/**
		 * We should have errors, because there's nothing to be deleted
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	public function testUpdatePostWithInvalidId() {

		$mutation = '
		mutation updatePostWithInvalidId($input:updatePostInput!) {
			updatePost(input:$input) {
				clientMutationId
			}
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'someId',
				'id' => 'invalidIdThatShouldThrowAnError',
			],
		];

		$actual = do_graphql_request( $mutation, 'updatePostWithInvalidId', $variables );

		/**
		 * We should get an error thrown if we try and update a post with an invalid id
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		$page_id = $this->factory->post->create([
			'post_type' => 'page',
		]);
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $page_id );

		$variables = [
			'input' => [
				'clientMutationId' => 'someId',
				'id' => $global_id,
			],
		];

		/**
		 * Try to update a post, with a valid ID of a page
		 */
		$actual = do_graphql_request( $mutation, 'updatePostWithInvalidId', $variables );

		/**
		 * We should get an error here because the updatePost mutation should only be able to update "post" objects
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	public function testDeletePostOfAnotherType() {

		$args = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against
		 */
		$page_id = $this->factory->post->create( $args );

		$mutation = '
		mutation deletePostWithPageIdShouldFail{
		  deletePost( $clientMutationId:String! $id:ID! ){
		    post{
		      id
		    }
		  }
		}
		';

		$variables = wp_json_encode( [
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
			'clientMutationId' => 'someId',
		] );

		/**
		 * Run the mutation
		 */
		$actual = do_graphql_request( $mutation, 'deletePostWithPageIdShouldFail', $variables );

		/**
		 * The mutation should fail because the ID is for a page, but we're trying to delete a post
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	/**
	 * This tests to make sure a user without proper capabilities cannot create a post
	 */
	public function testCreatePostObjectWithoutProperCapabilities() {

		/**
		 * Set the current user as the subscriber role so we
		 * can test the mutation and make sure they cannot create a post
		 * since they don't have proper permissions
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Run the mutation.
		 */
		$actual = $this->createPostMutation();

		/**
		 * We're asserting that this will properly return an error
		 * because this user doesn't have permissions to create a post as a
		 * subscriber
		 */
		$this->assertNotEmpty( $actual['errors'] );

	}

	/**
	 * This tests a createPage mutation by an admin, to verify that a user WITH proper
	 * capabilities can create a page
	 */
	public function testCreatePageObjectByAdmin() {

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createPageMutation();

		/**
		 * We're expecting to have createPage returned with a nested clientMutationId matching the
		 * clientMutationId we sent through, as well as the title and content we passed through in the mutation
		 */
		$expected = [
			'data' => [
				'createPage' => [
					'clientMutationId' => $this->client_mutation_id,
					'page'             => [
						'title'   => $this->title,
						'content' => apply_filters( 'the_content', $this->content ),
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * This tests a createMediaItem mutation by an admin, to verify that a user WITH proper
	 * capabilities can create a page
	 */
	public function testCreateMediaItemByAdmin() {

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createMediaItemMutation();

		/**
		 * We're expecting to have createMediaItem returned with a nested clientMutationId matching the
		 * clientMutationId we sent through, as well as the title and description we passed through in the mutation
		 */
		$expected = [
			'data' => [
				'createMediaItem' => [
					'clientMutationId' => $this->client_mutation_id,
					'mediaItem'             => [
						'title'   => $this->title,
						'description' => apply_filters( 'the_content', $this->content ),
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testCreatePostWithNoInput() {

		$mutation = '
		mutation {
		  createPost{
		    post{
		      id
		    }
		  }
		}
		';

		$actual = do_graphql_request( $mutation );

		/**
		 * Make sure we're throwing an error if there's no $input with the mutation
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}
}
