<?php

class PostObjectMutationsTest extends \Codeception\TestCase\WPTestCase {

	public $title;
	public $content;
	public $client_mutation_id;
	public $admin;
	public $subscriber;
	public $author;
	public $contributor;

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

		$this->contributor = $this->factory()->user->create( [
			'role' => 'contributor',
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
		$page_id = $this->factory()->post->create( $args );

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
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
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
			'updatePage' => [
				'clientMutationId' => 'someId',
				'page'             => [
					'id'      => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
					'title'   => apply_filters( 'the_title', 'Some updated title' ),
					'content' => apply_filters( 'the_content', 'Some updated content' ),
					'pageId'  => $page_id,
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );

		/**
		 * Make sure the edit lock is removed after the mutation has finished
		 */
		$this->assertFalse( get_post_meta( '_edit_lock', $page_id, true ) );

	}

	public function testDeletePageMutation() {

		/**
		 * Set the current user as the subscriber role so we
		 * can test the mutation and assert that it failed
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
		$page_id = $this->factory()->post->create( $args );

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
		mutation deletePageTest($input:DeletePageInput!){
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
				'id'               => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
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
			'deletePage' => [
				'clientMutationId' => 'someId',
				'deletedId'        => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
				'page'             => [
					'id'      => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
					'title'   => apply_filters( 'the_title', 'Original Title' ),
					'content' => apply_filters( 'the_content', 'Original Content' ),
					'pageId'  => $page_id,
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );

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
				'id'               => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
				'clientMutationId' => 'someId',
				'forceDelete'      => true,
			],
		];
		$actual    = do_graphql_request( $mutation, 'deletePageTest', $variables );


		/**
		 * This time, we used forceDelete so the mutation should have succeeded
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'someId', $actual['data']['deletePage']['clientMutationId'] );
		$this->assertEquals( \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ), $actual['data']['deletePage']['deletedId'] );

		/**
		 * Try to delete the page one more time, and now there's nothing to delete, not even from the trash
		 */
		$actual = do_graphql_request( $mutation, 'deletePageTest', $variables );

		/**
		 * Now we should have errors again, because there's nothing to be deleted
		 */
		$this->assertArrayHasKey( 'errors', $actual );


	}

	public function testUpdatePostWithInvalidId() {

		$mutation = '
		mutation updatePostWithInvalidId($input:UpdatePostInput!) {
			updatePost(input:$input) {
				clientMutationId
			}
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'someId',
				'id'               => 'invalidIdThatShouldThrowAnError',
			],
		];

		$actual = do_graphql_request( $mutation, 'updatePostWithInvalidId', $variables );

		codecept_debug( $actual );

		/**
		 * We should get an error thrown if we try and update a post with an invalid id
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		$page_id   = $this->factory()->post->create( [
			'post_type' => 'page',
		] );
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $page_id );

		$variables = [
			'input' => [
				'clientMutationId' => 'someId',
				'id'               => $global_id,
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
		$page_id = $this->factory()->post->create( $args );

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
			'id'               => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
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
			'createPage' => [
				'clientMutationId' => $this->client_mutation_id,
				'page'             => [
					'title'   => apply_filters( 'the_title', $this->title ),
					'content' => apply_filters( 'the_content', $this->content ),
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

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

	public function testCreatePostByAuthorCanHavePublishStatus() {

		$mutation = '
		mutation createPost($input:CreatePostInput!){
		  createPost(input:$input){
		    clientMutationId
		    post{
		      id
		      title
		      status
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'CreatePost',
				'title' => 'Test Post as Contributor',
				'status' => 'PUBLISH',
			],
		];

		wp_set_current_user( $this->author );

		$actual = graphql([
			'query' => $mutation,
			'variables' => $variables
		]);

		codecept_debug( $actual );

		/**
		 * Make sure we're throwing an error if there's no $input with the mutation
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'publish', $actual['data']['createPost']['post']['status'] );
		$this->assertSame( $variables['input']['title'], $actual['data']['createPost']['post']['title'] );
		$this->assertSame( $variables['input']['clientMutationId'], $actual['data']['createPost']['clientMutationId'] );

	}

	public function testCreatePostByContributorCannotHavePublishStatus() {

		$mutation = '
		mutation createPost($input:CreatePostInput!){
		  createPost(input:$input){
		    clientMutationId
		    post{
		      id
		      title
		      status
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'CreatePost',
				'title' => 'Test Post as Contributor',
				'status' => 'PUBLISH',
			],
		];

		wp_set_current_user( $this->contributor );

		$actual = graphql([
			'query' => $mutation,
			'variables' => $variables
		]);

		codecept_debug( $actual );

		/**
		 * Make sure we're throwing an error if there's no $input with the mutation
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'pending', $actual['data']['createPost']['post']['status'] );
		$this->assertSame( $variables['input']['title'], $actual['data']['createPost']['post']['title'] );
		$this->assertSame( $variables['input']['clientMutationId'], $actual['data']['createPost']['clientMutationId'] );

	}

	public function createPostWithDatesMutation( $input ) {

        wp_set_current_user( $this->admin );

        $mutation = 'mutation createPost( $input:CreatePostInput! ) {
          createPost(input: $input) {
            post {
              id
              postId
              title
              date
              dateGmt
              modified
              modifiedGmt
            }
          }
		}
        ';

        $defaults = [
            'clientMutationId' => uniqid(),
            'title' => 'New Post',
            'status' => 'PUBLISH',
        ];

        $input = array_merge( $defaults, $input );

        $variables = [
            'input' => $input,
        ];

        /**
         * Run the mutation.
         */

        $results = do_graphql_request( $mutation, 'createPost', $variables );

	    return $results;
	}

    public function testDateInputsForCreatePost() {

        /**
         * Set the current user as the admin role so we
         * can test the mutation
         */

        wp_set_current_user( $this->admin );

        /**
         * Set the expected date outcome
         */

        $dateExpected = '2017-01-03T00:00:00';
        $dateGmtExpected = '2017-01-03T00:00:00';

        $results = $this->createPostWithDatesMutation([
            'date' => '1/3/2017',
            'status' => 'PUBLISH'
        ]);

        /**
         * Make sure there are no errors
         */
        $this->assertArrayNotHasKey( 'errors', $results );

        /**
         * We're expecting the date variable to match the date entry regardless of the way user enters it
         */

        $this->assertEquals( $dateExpected, $results['data']['createPost']['post']['date'] );
        $this->assertEquals( $dateGmtExpected, $results['data']['createPost']['post']['dateGmt'] );
        $this->assertNotEquals( '0000-00-00 00:00:00', $results['data']['createPost']['post']['modified'] );
        $this->assertNotEquals( '0000-00-00 00:00:00', $results['data']['createPost']['post']['modifiedGmt'] );

    }

    public function testDateInputsWithSlashFormattingForCreatePost() {

        /**
         * Set the current user as the admin role so we
         * can test the mutation
         */

        wp_set_current_user( $this->admin );

        /**
         * Set the input and expected date outcome
         */

        $dateExpected = '2017-01-03T00:00:00';
        $dateGmtExpected = '2017-01-03T00:00:00';

        $results = $this->createPostWithDatesMutation([
            'date' => '2017/01/03',
        ]);

        /**
         * Make sure there are no errors
         */

        $this->assertArrayNotHasKey( 'errors', $results );

        /**
         * We're expecting the date variable to match the date entry regardless of the way user enters it
         */

        $this->assertEquals( $dateExpected, $results['data']['createPost']['post']['date'] );
        $this->assertEquals( $dateGmtExpected, $results['data']['createPost']['post']['dateGmt'] );
	    $this->assertNotEquals( '0000-00-00 00:00:00', $results['data']['createPost']['post']['modified'] );
	    $this->assertNotEquals( '0000-00-00 00:00:00', $results['data']['createPost']['post']['modifiedGmt'] );

    }

    public function testDateInputsWithStatusPendingAndDashesCreatePost() {

        /**
         * Set the current user as the admin role so we
         * can test the mutation
         */

        wp_set_current_user( $this->admin );

        /**
         * Set the input and expected date outcome
         */

        $dateExpected = '2017-01-03T00:00:00';
        $dateGmtExpected = null;

        $results = $this->createPostWithDatesMutation([
            'date' => '3-1-2017',
            'status' => 'PENDING'
        ]);

        /**
         * Make sure there are no errors
         */

        $this->assertArrayNotHasKey( 'errors', $results );

        /**
         * We're expecting the date variable to match the date entry regardless of the way user enters it
         */

        $this->assertEquals( $dateExpected, $results['data']['createPost']['post']['date'] );
        $this->assertEquals( $dateGmtExpected, $results['data']['createPost']['post']['dateGmt'] );
	    $this->assertNotEquals( '0000-00-00 00:00:00', $results['data']['createPost']['post']['modified'] );
	    $this->assertNotEquals( '0000-00-00 00:00:00', $results['data']['createPost']['post']['modifiedGmt'] );

    }

    public function testDateInputsWithDraftAndPublishUpdatePost() {

        /**
         * Set the current user as the admin role so we
         * can test the mutation
         */
        wp_set_current_user( $this->admin );

        /**
         * Create a post to test against and set global ID
         */
        $test_post = $this->factory()->post->create( [
            'post_title' => 'My Test Post',
            'post_status' => 'draft',
        ] );

        $global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $test_post );

        /**
         * Prepare mutation for GQL request
         */
        $request = '
        {
            post( id: "'. $global_id . '" ) {
              id
              postId
              title
              date
              dateGmt
              modified
              modifiedGmt
            }
        }
        ';

        /**
         * Run GQl request
         */
        $results = do_graphql_request( $request );

        /**
         * Set the expected dateGmt outcome
         */
        $dateGmtExpected = null;

        /**
         * Assert results dateGmt equals the expected outcome
         */
        $this->assertEquals( $dateGmtExpected, $results['data']['post']['dateGmt'] );

        /**
         * Update post to test against status: published
         */
        wp_update_post( [
            'ID'          => $test_post,
            'post_status' => 'publish',
        ] );

        /**
         * Run GQl request
         */
        $results = do_graphql_request( $request );

        /**
         * Assert timestamp is not null
         */
        $this->assertNotNull( $results['data']['post']['dateGmt'] );

        /**
         * Update post back to draft
         */
        wp_update_post( [
            'ID' => $test_post,
            'post_status' => 'draft'
        ] );

        /**
         * Run GQl request
         */
        $results = do_graphql_request( $request );

        /**
         * Assert timestamp is STILL not null
         */
        $this->assertNotNull( $results['data']['post']['dateGmt'] );
    }

	/**
	 * @throws Exception
	 */
    public function testUserWithoutProperCapabilityCannotUpdateOthersPosts() {

		$admin_created_post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Post from Admin, Edit by Contributor',
			'post_author' => $this->admin
		]);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $admin_created_post_id );

		$mutation = '
		mutation UpdatePost($input: UpdatePostInput! ) {
		  updatePost(input:$input) {
		    post {
		      id
		      title
		      content
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'UpdatePost',
				'id' => $global_id,
				'title' => 'New Title'
			]
		];

		wp_set_current_user( $this->contributor );

		$actual = graphql([
			'query' => $mutation,
			'variables' => $variables,
		]);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

    }

	/**
	 * @throws Exception
	 */
	public function testUserWithoutProperCapabilityCannotUpdateOthersPages() {

		$admin_created_page_id = $this->factory()->post->create( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Test Page from Admin, Edit by Contributor',
			'post_author' => $this->admin
		] );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $admin_created_page_id );

		$mutation = '
		mutation UpdatePage($input: UpdatePageInput! ) {
		  updatePage(input:$input) {
		    page {
		      id
		      title
		      content
		    }
		  }
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => 'UpdatePage',
				'id'               => $global_id,
				'title'            => 'New Title'
			]
		];

		wp_set_current_user( $this->contributor );

		$actual = graphql( [
			'query'     => $mutation,
			'variables' => $variables,
		] );

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

	}

	public function testUpdatingPostByOtherAuthorRequiresEditOtherPostCapability() {

		$post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $this->author
		]);

		wp_set_current_user( $this->contributor );

		$mutation = '
		mutation updatePost( $input: UpdatePostInput! ) {
		  updatePost( input: $input ) {
		    post {
		      id
		      title
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $mutation,
			'variables' => [
				'input' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
					'title' => 'Test Update',
					'clientMutationId' => 'test...'
				],
			],
		]);


		// A contributor cannot edit another authors posts
		$this->assertArrayHasKey( 'errors', $actual );

		wp_set_current_user( $this->admin );

		$updated_title = uniqid();

		$actual = graphql([
			'query' => $mutation,
			'variables' => [
				'input' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
					'title' => $updated_title,
					'clientMutationId' => 'test...'
				],
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $updated_title, $actual['data']['updatePost']['post']['title'] );

	}

}
