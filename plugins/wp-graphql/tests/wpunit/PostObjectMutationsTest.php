<?php

class PostObjectMutationsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $title;
	public $content;
	public $admin;
	public $subscriber;
	public $author;
	public $contributor;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->title   = 'some title';
		$this->content = 'some content';

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

		$this->contributor = $this->factory()->user->create(
			[
				'role'         => 'contributor',
				'display_name' => 'contributor',
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

	/**
	 * This processes a mutation to create a post
	 *
	 * @return array
	 */
	public function createPostMutation() {

		$query = '
		mutation createPost( $title:String!, $content:String! ){
			createPost(
				input:{
					title:$title
					content:$content
				}
			){
				post{
					title
					content
				}
			}
		}
		';

		$variables = [
			'title'   => $this->title,
			'content' => $this->content,
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	public function createPageMutation() {

		$query = '
		mutation createPage( $title:String!, $content:String! ){
			createPage(
				input:{
					title:$title
					content:$content
				}
			){
				page{
					title
					content
				}
			}
		}
		';

		$variables = [
			'title'   => $this->title,
			'content' => $this->content,
		];

		return graphql( compact( 'query', 'variables' ) );
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
		codecept_debug( $actual );

		$expected = [
			'createPage' => [
				'page' => [
					'title'   => apply_filters( 'the_title', $this->title ),
					'content' => apply_filters( 'the_content', $this->content ),
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testCreatePostWithNoInput() {

		$query = '
		mutation {
			createPost{
				post{
					id
				}
			}
		}
		';

		$actual = graphql( compact( 'query' ) );

		/**
		 * Make sure we're throwing an error if there's no $input with the mutation
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testCreatePostByAuthorCanHavePublishStatus() {

		$query = '
		mutation createPost($input:CreatePostInput!){
			createPost(input:$input){
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
				'title'  => 'Test Post as Contributor',
				'status' => 'PUBLISH',
			],
		];

		wp_set_current_user( $this->author );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Make sure we're throwing an error if there's no $input with the mutation
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'publish', $actual['data']['createPost']['post']['status'] );
		$this->assertSame( $variables['input']['title'], $actual['data']['createPost']['post']['title'] );
	}

	public function testCreatePostByContributorCannotHavePublishStatus() {

		$query = '
		mutation createPost($input:CreatePostInput!){
			createPost(input:$input){
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
				'title'  => 'Test Post as Contributor',
				'status' => 'PUBLISH',
			],
		];

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Make sure we're throwing an error if there's no $input with the mutation
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'pending', $actual['data']['createPost']['post']['status'] );
		$this->assertSame( $variables['input']['title'], $actual['data']['createPost']['post']['title'] );
	}

	public function testCreatePageWithParent() {
		wp_set_current_user( $this->admin );

		$parent_page_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Parent Page for PostObjectMutationsTest',
				'post_content' => 'Parent Content',
			]
		);

		$query = '
		mutation createPage( $input:CreatePageInput! ){
			createPage( input:$input ) {
				page {
					parent {
						node{
							databaseId
							id
						}
					}
				}
			}
		}
		';

		// Test with bad parent ID
		$variables = [
			'input' => [
				'title'    => $this->title,
				'content'  => $this->content,
				'parentId' => 99999,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( null, $actual['data']['createPage']['page']['parent'] );

		// Test with databaseId
		$variables['input']['parentId'] = $parent_page_id;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $parent_page_id, $actual['data']['createPage']['page']['parent']['node']['databaseId'] );

		// Test with global Id
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $parent_page_id );
		$variables = [
			'input' => [
				'title'    => $this->title . ' 2',
				'content'  => $this->content,
				'parentId' => $global_id,
			],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $global_id, $actual['data']['createPage']['page']['parent']['node']['id'] );
	}

	public function testCreatePostWithPostAuthor() {
		wp_set_current_user( $this->admin );

		$query = '
		mutation createPost( $input:CreatePostInput! ){
			createPost( input:$input ) {
				post{
					author {
						node {
							databaseId
							id
						}
					}
				}
			}
		}
		';

		// Test with bad author ID
		$variables = [
			'input' => [
				'title'    => $this->title,
				'content'  => $this->content,
				'authorId' => 99999,
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with databaseId
		$variables['input']['authorId'] = $this->contributor;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $this->contributor, $actual['data']['createPost']['post']['author']['node']['databaseId'] );

		// Test with global Id
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $this->contributor );
		$variables = [
			'input' => [
				'title'    => $this->title . ' 2',
				'content'  => $this->content,
				'authorId' => $global_id,
			],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $global_id, $actual['data']['createPost']['post']['author']['node']['id'] );
	}

	public function createPostWithDatesMutation( $input ) {

		wp_set_current_user( $this->admin );

		$query = 'mutation createPost( $input:CreatePostInput! ) {
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
			'title'  => 'New Post',
			'status' => 'PUBLISH',
		];

		$input = array_merge( $defaults, $input );

		$variables = [
			'input' => $input,
		];

		/**
		 * Run the mutation.
		 */

		return graphql( compact( 'query', 'variables' ) );
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

		$dateExpected    = '2017-01-03T00:00:00';
		$dateGmtExpected = '2017-01-03T00:00:00';

		$results = $this->createPostWithDatesMutation(
			[
				'date'   => '1/3/2017',
				'status' => 'PUBLISH',
			]
		);
		codecept_debug( $results );

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

		$dateExpected    = '2017-01-03T00:00:00';
		$dateGmtExpected = '2017-01-03T00:00:00';

		$results = $this->createPostWithDatesMutation(
			[
				'date' => '2017/01/03',
			]
		);
		codecept_debug( $results );

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

		$dateExpected    = '2017-01-03T00:00:00';
		$dateGmtExpected = null;

		$results = $this->createPostWithDatesMutation(
			[
				'date'   => '3-1-2017',
				'status' => 'PENDING',
			]
		);
		codecept_debug( $results );

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
		$test_post = $this->factory()->post->create(
			[
				'post_title'  => 'My Test Post for PostObjectMutationsTest',
				'post_status' => 'draft',
			]
		);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $test_post );

		/**
		 * Prepare mutation for GQL request
		 */
		$query = '
		{
			post( id: "' . $global_id . '" ) {
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
		$results = $this->graphql( compact( 'query' ) );

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
		wp_update_post(
			[
				'ID'          => $test_post,
				'post_status' => 'publish',
			]
		);

		/**
		 * Run GQl request
		 */
		$results = $this->graphql( compact( 'query' ) );

		/**
		 * Assert timestamp is not null
		 */
		$this->assertNotNull( $results['data']['post']['dateGmt'] );

		/**
		 * Update post back to draft
		 */
		wp_update_post(
			[
				'ID'          => $test_post,
				'post_status' => 'draft',
			]
		);

		/**
		 * Run GQl request
		 */
		$results = $this->graphql( compact( 'query' ) );

		/**
		 * Assert timestamp is STILL not null
		 */
		$this->assertNotNull( $results['data']['post']['dateGmt'] );
	}

	public function testDeletePageMutation() {
		wp_set_current_user( $this->admin );

		$args = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for DeletePageMutation',
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
		$this->assertEquals( $new_page->post_title, 'Original Title for DeletePageMutation' );
		$this->assertEquals( $new_page->post_content, 'Original Content' );

		/**
		 * Prepare the mutation
		 */
		$query = '
		mutation deletePageTest($input:DeletePageInput!){
			deletePage(input:$input){
				deletedId
				page{
					id
					title
					content
					databaseId
				}
			}
		}';

		// Test with no id.
		$variables = [
			'input' => [
				'id' => '',
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with bad Id.
		$variables['input']['id'] = 999999;

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with global Id.

		/**
		 * Set the current user as the subscriber role so we
		 * can test the mutation and assert that it failed
		 */
		wp_set_current_user( $this->subscriber );

		$variables['input']['id'] = \GraphQLRelay\Relay::toGlobalId( 'post', $page_id );

		/**
		 * Execute the request
		 */
		$actual = graphql( compact( 'query', 'variables' ) );

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
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'deletePage' => [
				'deletedId' => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
				'page'      => [
					'id'         => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
					'title'      => apply_filters( 'the_title', 'Original Title for DeletePageMutation' ),
					'content'    => apply_filters( 'the_content', 'Original Content' ),
					'databaseId' => $page_id,
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );

		// Test with database ID
		wp_update_post(
			[
				'ID'          => $page_id,
				'post_status' => 'publish',
			]
		);

		$variables['input']['id'] = $page_id;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $expected, $actual['data'] );

		/**
		 * Try to delete again
		 */
		$actual = graphql( compact( 'query', 'variables' ) );

		/**
		 * We should get an error because we're not using forceDelete
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Try to delete again, this time with forceDelete
		 */
		$variables = [
			'input' => [
				'id'          => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
				'forceDelete' => true,
			],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * This time, we used forceDelete so the mutation should have succeeded
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ), $actual['data']['deletePage']['deletedId'] );

		/**
		 * Try to delete the page one more time, and now there's nothing to delete, not even from the trash
		 */
		$actual = graphql( compact( 'query', 'variables' ) );

		/**
		 * Now we should have errors again, because there's nothing to be deleted
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testDeletePostOfAnotherType() {

		$args = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for DeletePageMutation',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against
		 */
		$page_id = $this->factory()->post->create( $args );

		$query = '
		mutation deletePostWithPageIdShouldFail($id: ID!) {
		  deletePost(input: {id: $id}) {
		    post {
		      id
		    }
		  }
		}
		';

		$variables = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
		];

		/**
		 * Run the mutation
		 */
		$actual = graphql( compact( 'query', 'variables' ) );

		/**
		 * The mutation should fail because the ID is for a page, but we're trying to delete a post
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testUpdatePageMutation() {

		$args = [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for UpdatePageMutation',
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
		$this->assertEquals( $new_page->post_title, 'Original Title for UpdatePageMutation' );
		$this->assertEquals( $new_page->post_content, 'Original Content' );

		/**
		 * Prepare the mutation
		 */
		$query = '
		mutation updatePageTest( $id:ID! $title:String $content:String ){
			updatePage(
				input: {
					id:$id,
					title:$title,
					content:$content,
				}
			) {
				page{
					id
					title
					content
					databaseId
				}
			}
		}';

		/**
		 * Set the variables to use with the mutation
		 */
		$variables = [
			'id'      => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
			'title'   => 'Some updated title',
			'content' => 'Some updated content',
		];

		/**
		 * Set the current user as the subscriber so we can test, and expect to fail
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Execute the request
		 */
		$actual = graphql( compact( 'query', 'variables' ) );

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
		$actual = graphql( compact( 'query', 'variables' ) );

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
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Define the expected output.
		 *
		 * The mutation should've updated the article to contain the updated content
		 */
		$expected = [
			'updatePage' => [
				'page' => [
					'id'         => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
					'title'      => apply_filters( 'the_title', 'Some updated title' ),
					'content'    => apply_filters( 'the_content', 'Some updated content' ),
					'databaseId' => $page_id,
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

		// Test with no id
		$variables = [
			'id'      => '',
			'title'   => 'Some updated title 2',
			'content' => 'Some updated content 2',
		];

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		// Test with bad id
		$variables['id'] = 999999;

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		// Test with databaseId
		$variables['id'] = $page_id;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $page_id, $actual['data']['updatePage']['page']['databaseId'] );
	}

	public function testUpdatePostWithInvalidId() {

		$query = '
		mutation updatePostWithInvalidId($input:UpdatePostInput!) {
			updatePost(input:$input) {
				post {
					databaseId
				}
			}
		}
		';

		$variables = [
			'input' => [
				'id' => 'invalidIdThatShouldThrowAnError',
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );

		/**
		 * We should get an error thrown if we try and update a post with an invalid id
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		$page_id   = $this->factory()->post->create(
			[
				'post_type' => 'page',
			]
		);
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $page_id );

		$variables = [
			'input' => [
				'id' => $global_id,
			],
		];

		/**
		 * Try to update a post, with a valid ID of a page
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * We should get an error here because the updatePost mutation should only be able to update "post" objects
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * @throws \Exception
	 */
	public function testUserWithoutProperCapabilityCannotUpdateOthersPosts() {

		$admin_created_post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post from Admin, Edit by Contributor',
				'post_author' => $this->admin,
			]
		);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $admin_created_post_id );

		$query = '
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
				'id'    => $global_id,
				'title' => 'New Title',
			],
		];

		wp_set_current_user( $this->contributor );

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * @throws \Exception
	 */
	public function testUserWithoutProperCapabilityCannotUpdateOthersPages() {

		$admin_created_page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page from Admin, Edit by Contributor',
				'post_author' => $this->admin,
			]
		);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $admin_created_page_id );

		$query = '
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
				'id'    => $global_id,
				'title' => 'New Title',
			],
		];

		wp_set_current_user( $this->contributor );

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testUpdatingPostByOtherAuthorRequiresEditOtherPostCapability() {

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->author,
			]
		);

		wp_set_current_user( $this->contributor );

		$query = '
		mutation updatePost( $input: UpdatePostInput! ) {
			updatePost( input: $input ) {
				post {
					id
					title
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'input' => [
						'id'    => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
						'title' => 'Test Update',
					],
				],
			]
		);

		// A contributor cannot edit another authors posts
		$this->assertArrayHasKey( 'errors', $actual );

		wp_set_current_user( $this->admin );

		$updated_title = uniqid();

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'input' => [
						'id'    => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
						'title' => $updated_title,
					],
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $updated_title, $actual['data']['updatePost']['post']['title'] );
	}

	public function testUpdatePostWithLockFails() {

		/**
		 * Create a post to test against
		 */
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->contributor,
			]
		);

		$editor_id = $this->factory()->user->create(
			[
				'role' => 'editor',
			]
		);

		/**
		 *  Set lock and lock user
		 */
		$lock = sprintf( '%s:%s', time() - 60, $this->contributor );
		update_post_meta( $post_id, '_edit_lock', $lock );

		$query = '
		mutation updatePostWithLockShouldFail($input: UpdatePostInput!) {
			updatePost(input: $input) {
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
				'id'    => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
				'title' => 'updated title',
			],
		];

		/**
		 * Run the mutation as different user
		 */
		wp_set_current_user( $editor_id );
		$actual = graphql( compact( 'query', 'variables' ) );

		/**
		 * The mutation should fail because the ID is locked
		 */
		$this->assertEquals(
			'You cannot update this item. contributor is currently editing.',
			$actual['errors'][0]['message']
		);

		// with override allows edit
		$variables = [
			'input' => [
				'id'             => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
				'title'          => 'updated title',
				'ignoreEditLock' => true,
			],
		];

		/**
		 * Run the mutation as different user
		 */
		wp_set_current_user( $editor_id );
		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( 'updated title', $actual['data']['updatePost']['post']['title'] );
	}

	public function testDeletePostWithLockFails() {

		/**
		 * Create a post to test against
		 */
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->contributor,
			]
		);

		$editor_id = $this->factory()->user->create(
			[
				'role' => 'editor',
			]
		);

		/**
		 *  Set lock and lock user
		 */
		$lock = sprintf( '%s:%s', time() - 60, $this->contributor );
		update_post_meta( $post_id, '_edit_lock', $lock );

		$query = '
		mutation deletePostWithLockShouldFail($input: DeletePostInput!) {
			deletePost(input: $input) {
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
				'id' => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
			],
		];

		/**
		 * Run the mutation as different user
		 */
		wp_set_current_user( $editor_id );
		$actual = graphql( compact( 'query', 'variables' ) );

		/**
		 * The mutation should fail because the ID is locked
		 */
		$this->assertEquals(
			'You cannot delete this item. contributor is currently editing.',
			$actual['errors'][0]['message']
		);

		// with override allows edit
		$variables = [
			'input' => [
				'id'             => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
				'ignoreEditLock' => true,
			],
		];

		/**
		 * Run the mutation as different user
		 */
		wp_set_current_user( $editor_id );
		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ), $actual['data']['deletePost']['post']['id'] );
	}

	public function testCreatePostMutationDoesNotAppendDefaultCategoryIfOtherCategoriesAreInput() {

		// Create a category
		$category_id = $this->factory()->category->create( [ 'name' => 'Test Category' ] );

		// Create a post with the category
		$query = '
		mutation createPost($input:CreatePostInput!){
			createPost(input:$input){
				post{
					id
					title
					categories {
						nodes {
							id
							databaseId
							name
						}
					}
				}
			}
		}
		';

		// get category slug
		$category = get_category( $category_id );
		$category_slug = $category->slug;

		$variables = [
			'input' => [
				'title'      => 'Test Post',
				'categories' => [
					'nodes' => [
						[ 'slug' => $category_slug ]
					]
				],
			],
		];

		wp_set_current_user( $this->admin );
		codecept_debug( [
			'variables' => $variables,
		]);
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $category_id, $actual['data']['createPost']['post']['categories']['nodes'][0]['databaseId'] );

		// there should only be 1 category (the one we added, not the default one)
		$this->assertCount( 1, $actual['data']['createPost']['post']['categories']['nodes'] );

	}
}
