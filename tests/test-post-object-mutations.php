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

	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->title = 'some title';
		$this->content = 'some content';
		$this->client_mutation_id = 'someUniqueId';
		$this->admin            = $this->factory->user->create( [
			'role' => 'administrator',
		] );
		$this->subscriber       = $this->factory->user->create( [
			'role' => 'subscriber',
		] );
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

		$mutation  = '
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

		$mutation  = '
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

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		$args = [
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Original Title',
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
		$variables = wp_json_encode([
			'id' => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
			'title' => 'Some updated title',
			'content' => 'Some updated content',
			'clientMutationId' => 'someId',
		]);

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
					'page' => [
						'id' => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
						'title' => 'Some updated title',
						'content' => apply_filters( 'the_content', 'Some updated content' ),
						'pageId' => $page_id,
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
		wp_set_current_user( $this->admin );

		$args = [
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Original Title',
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
		mutation deletePageTest( $clientMutationId:String! $id:ID! ){
		  deletePage(
		    input: {
		        clientMutationId:$clientMutationId
		        id:$id
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
		$variables = wp_json_encode([
			'id' => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
			'clientMutationId' => 'someId',
		]);

		/**
		 * Execute the request
		 */
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );

		var_dump( $actual );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'data' => [
				'deletePage' => [
					'clientMutationId' => 'someId',
					'page' => [
						'id' => \GraphQLRelay\Relay::toGlobalId( 'page', $page_id ),
						'title' => 'Original Title',
						'content' => apply_filters( 'the_content', 'Original Content' ),
						'pageId' => $page_id,
					],
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );

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
}
