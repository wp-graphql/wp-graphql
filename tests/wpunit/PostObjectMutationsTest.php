<?php

class PostObjectMutationsTest extends \Codeception\TestCase\WPTestCase {

	public $title;
	public $content;
	public $client_mutation_id;
	public $admin;
	public $subscriber;
	public $author;
	public $attachment_id;
	public $media_item_id;
	public $post;
	public $post_uid;
	public $create_media_item_variables;
	public $altText;
	public $authorId;
	public $caption;
	public $commentStatus;
	public $current_date_gmt;
	public $date;
	public $dateGmt;
	public $description;
	public $filePath;
	public $fileType;
	public $slug;
	public $status;
	public $pingStatus;
	public $parentId;

	public function setUp() {

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

		/**
		 * Create a mediaItem to update and store it's WordPress post ID
		 * and it's WPGraphQL ID for using in our updateMediaItem mutation
		 */
		$this->attachment_id = $this->factory()->attachment->create( ['post_mime_type' => 'image/gif', 'post_author' => $this->admin] );
		$this->media_item_id = \GraphQLRelay\Relay::toGlobalId( 'attachment', $this->attachment_id );

		/**
		 * Create a post to test against
		 */
		$this->post = $this->factory()->post->create( [
			'post_author' => $this->admin,
		] );
		$this->post_uid = \GraphQLRelay\Relay::toGlobalId( 'post', $this->post );

		/**
		 * Populate the mediaItem input fields
		 */
		$this->altText          = 'A gif of Shia doing Magic.';
		$this->authorId         = \GraphQLRelay\Relay::toGlobalId( 'user', $this->admin );
		$this->caption          = 'Shia shows off some magic in this caption.';
		$this->commentStatus    = 'closed';
		$this->date             = '2017-08-01 15:00:00';
		$this->dateGmt          = '2017-08-01T21:00:00';
		$this->description      = 'This is a magic description.';
		$this->filePath         = 'http://www.reactiongifs.com/r/mgc.gif';
		$this->fileType         = 'IMAGE_GIF';
		$this->slug             = 'magic-shia';
		$this->status           = 'INHERIT';
		$this->title            = 'Magic Shia Gif';
		$this->pingStatus       = 'closed';
		$this->parentId         = null;

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$this->create_media_item_variables = [
			'input' => [
				'filePath'         => $this->filePath,
				'fileType'         => $this->fileType,
				'clientMutationId' => $this->client_mutation_id,
				'title'            => $this->title,
				'description'      => $this->description,
				'altText'          => $this->altText,
				'parentId'         => $this->parentId,
				'caption'          => $this->caption,
				'commentStatus'    => $this->commentStatus,
				'date'             => $this->date,
				'dateGmt'          => $this->dateGmt,
				'slug'             => $this->slug,
				'status'           => $this->status,
				'pingStatus'       => $this->pingStatus,
				'authorId'         => $this->authorId,
			],
		];
	}


	public function tearDown() {
		// your tear down methods here

		// then
		parent::tearDown();
	}

	/**
	 * This function tests the createMediaItem mutation
	 * and is reused throughout the createMediaItem tests
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php
	 * @access public
	 * @return array $actual
	 */
	public function createMediaItemMutation() {

		/**
		 * Set up the createMediaItem mutation
		 */
		$mutation = '
			mutation createMediaItem( $input: CreateMediaItemInput! ){
			  createMediaItem(input: $input){
			    clientMutationId
			    mediaItem{
			      id
			      mediaItemId
			      mediaType
			      date
			      dateGmt
			      slug
			      status
			      title
			      commentStatus
			      pingStatus
			      altText
			      caption
			      description
			      mimeType
			      parent {
			        ... on Post {
			          id
			        }
			      }
			      sourceUrl
			      mediaDetails {
			          file
			          height
			          meta {
			            aperture
			            credit
			            camera
			            caption
			            createdTimestamp
			            copyright
			            focalLength
			            iso
			            shutterSpeed
			            title
			            orientation
			          }
			          width
			          sizes {
			            name
			            file
			            width
			            height
			            mimeType
			            sourceUrl
			          }
			        }
			    }
			  }
			}
		';

		$actual = do_graphql_request( $mutation, 'createMediaItem', $this->create_media_item_variables );

		return $actual;
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
						'title'   => apply_filters( 'the_title', 'Some updated title' ),
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

		/**
		 * Make sure the edit lock is removed after the mutation has finished
		 */
		$this->assertFalse( get_post_meta( '_edit_lock', $page_id, true ) );

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
						'title'   => apply_filters( 'the_title', 'Original Title' ),
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
				'forceDelete'      => true,
			],
		];
		$actual    = do_graphql_request( $mutation, 'deletePageTest', $variables );


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
				'id'               => 'invalidIdThatShouldThrowAnError',
			],
		];

		$actual = do_graphql_request( $mutation, 'updatePostWithInvalidId', $variables );

		/**
		 * We should get an error thrown if we try and update a post with an invalid id
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		$page_id   = $this->factory()->post->create( [
			'post_type' => 'page',
		] );
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $page_id );

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
						'title'   => apply_filters( 'the_title', $this->title ),
						'content' => apply_filters( 'the_content', $this->content ),
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

		$dateExpected = '2017-01-03 00:00:00';
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

		$dateExpected = '2017-01-03 00:00:00';
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

		$dateExpected = '2017-01-03 00:00:00';
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
	 * This processes a mutation to create a post with an existing mediaItem and
	 * a new mediaItem for the featured image
	 *
	 * @return void
	 */
	public function testCreatePostMutationWithFeaturedImage() {

		/**
		 * Set the current user to admin
		 */
		wp_set_current_user( $this->admin );

		/**
		 * ADD NEW IMAGE on create
		 *
		 * Create a test post with an new mediaItem as the featured image
		 */
		$new_image_mutation = '
		mutation createPostWithNewImage( $clientMutationId:String!, $title:String!, $content:String!, $featuredImage:PostFeaturedImageNodeInput! ){
		  createPost(
		    input:{
		      clientMutationId:$clientMutationId,
		      title:$title
		      content:$content
		      featuredImage: $featuredImage
		    }
		  ){
		    clientMutationId
		    post{
		      title
		      content
		      featuredImage {
		        title
		        sourceUrl
		      }
		    }
		  }
		}
		';

		$new_image_variables = [
			'clientMutationId' => $this->client_mutation_id,
			'title'            => 'Post with an Awesome Photo',
			'content'          => $this->content,
			'featuredImage'    => [
				'title' => 'Awesome Photo',
				'sourceUrl' => 'https://media.giphy.com/media/Z6f7vzq3iP6Mw/giphy.gif',
			],
		];

		$actual = do_graphql_request( $new_image_mutation, 'createPostWithNewImage', $new_image_variables );

		$expected = [
			'data' => [
				'createPost' => [
					'clientMutationId' => $new_image_variables['clientMutationId'],
					'post' => [
						'title'         => apply_filters( 'the_title', $new_image_variables['title'] ),
						'content'       => apply_filters( 'the_content', $new_image_variables['content'] ),
						'featuredImage' => [
							'title'     => $new_image_variables['featuredImage']['title'],
							'sourceUrl' => $actual['data']['createPost']['post']['featuredImage']['sourceUrl'],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
		$this->assertArrayHasKey( 'sourceUrl', $actual['data']['createPost']['post']['featuredImage'] );
		$this->assertInternalType( 'string', $actual['data']['createPost']['post']['featuredImage']['sourceUrl'] );

		/**
		 * Create a media item to add to the post
		 */
		$media_item = $this->createMediaItemMutation();
		$media_item_uid = $media_item['data']['createMediaItem']['mediaItem']['id'];

		/**
		 * ADD EXISTING IMAGE on create
		 *
		 * Create a test post with an existing featured image
		 */
		$existing_image_mutation = '
		mutation createPostWithExistingImage( $clientMutationId:String!, $title:String!, $content:String!, $featuredImage:PostFeaturedImageNodeInput! ){
		  createPost(
		    input:{
		      clientMutationId:$clientMutationId,
		      title:$title
		      content:$content
		      featuredImage: $featuredImage
		    }
		  ){
		    clientMutationId
		    post{
		      title
		      content
		      featuredImage {
		        id
		      }
		    }
		  }
		}
		';

		$existing_image_variables = [
			'clientMutationId' => $this->client_mutation_id,
			'title'            => 'Post with an Awesome Photo',
			'content'          => $this->content,
			'featuredImage'    => [
				'id' => $media_item_uid,
			],
		];

		$actual = do_graphql_request( $existing_image_mutation, 'createPostWithExistingImage', $existing_image_variables );

		$expected = [
			'data' => [
				'createPost' => [
					'clientMutationId' => $existing_image_variables['clientMutationId'],
					'post' => [
						'title'         => apply_filters( 'the_title', $existing_image_variables['title'] ),
						'content'       => apply_filters( 'the_content', $existing_image_variables['content'] ),
						'featuredImage' => [
							'id'     => $media_item_uid,
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * This processes a mutation to create a post with a featured image
	 *
	 * @return void
	 */
	public function testUpdatePostMutationWithFeaturedImage() {

		/**
		 * Set the current user to admin
		 */
		wp_set_current_user( $this->admin );

		/**
		 * CREATE FEATURED IMAGE on update
		 *
		 * Update the factory created test post with an existing featured image
		 */
		$new_image_mutation = '
		mutation updatePostWithNewImage( $id:ID!, $clientMutationId:String!, $featuredImage:PostFeaturedImageNodeInput! ){
		  updatePost(
		    input:{
		      clientMutationId:$clientMutationId,
		      id:$id
		      featuredImage:$featuredImage
		    }
		  ){
		    clientMutationId
		    post{
		      featuredImage {
		        title
		        sourceUrl
		      }
		    }
		  }
		}
		';

		/**
		 * Variables for the already created featured image
		 */
		$new_image_variables = [
			'id'               => $this->post_uid,
			'clientMutationId' => $this->client_mutation_id,
			'featuredImage'    => [
				'title' => 'Awesome Photo',
				'sourceUrl' => 'https://media.giphy.com/media/Z6f7vzq3iP6Mw/giphy.gif',
			],
		];

		$actual = do_graphql_request( $new_image_mutation, 'updatePostWithNewImage', $new_image_variables );

		$expected = [
			'data' => [
				'updatePost' => [
					'clientMutationId' => $new_image_variables['clientMutationId'],
					'post' => [
						'featuredImage' => [
							'title'     => $new_image_variables['featuredImage']['title'],
							'sourceUrl' => 'http://wpgraphql.test/wp-content/uploads/'. date("Y") . '/' . date('m') . '/giphy.gif',
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

		/**
		 * Create a media item to add to the post
		 */
		$media_item = $this->createMediaItemMutation();
		$media_item_uid = $media_item['data']['createMediaItem']['mediaItem']['id'];

		/**
		 * ADD EXISTING IMAGE on update
		 *
		 * Update the factory created test post with an existing featured image
		 */
		$existing_image_mutation = '
		mutation updatePostWithExistingImage( $id:ID!, $clientMutationId:String!, $featuredImage:PostFeaturedImageNodeInput! ){
		  updatePost(
		    input:{
		      clientMutationId:$clientMutationId,
		      id:$id
		      featuredImage:$featuredImage
		    }
		  ){
		    clientMutationId
		    post{
		      featuredImage {
		        id
		      }
		    }
		  }
		}
		';

		/**
		 * Variables for the already created featured image
		 */
		$existing_image_variables = [
			'id'               => $this->post_uid,
			'clientMutationId' => $this->client_mutation_id,
			'featuredImage'    => [
				'id' => $media_item_uid,
			],
		];

		$actual = do_graphql_request( $existing_image_mutation, 'updatePostWithExistingImage', $existing_image_variables );

		$expected = [
			'data' => [
				'updatePost' => [
					'clientMutationId' => $existing_image_variables['clientMutationId'],
					'post' => [
						'featuredImage' => [
							'id'     => $media_item_uid,
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

}
