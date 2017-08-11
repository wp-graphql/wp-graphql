<?php

/**
 * WPGraphQL Test mediaItem Mutations
 *
 * Test the WPGraphQL mediaItem Mutations for the proper user permissions and fields
 *
 * @package WPGraphQL
 *
 */
class WP_GraphQL_Test_Media_Item_Mutations extends WP_UnitTestCase {

	/**
	 * Declaration of all of the mediaItem mutation input fields
	 */
	public $altText;
	public $authorId;
	public $caption;
	public $commentStatus;
	public $date;
	public $dateGmt;
	public $description;
	public $filePath;
	public $fileType;
	public $slug;
	public $status;
	public $title;
	public $pingStatus;
	public $parentId;
	public $clientMutationId;

	/**
	 * Declaration of the user roles
	 */
	public $author;
	public $subscriber;
	public $admin;

	/**
	 * This function is run before each method
	 */
	public function setUp() {

		/**
		 * Populate the mediaItem input fields
		 */
		$this->altText          = 'A gif of Shia doing Magic.';
		$this->authorId         = 1;
		$this->caption          = 'Shia shows off some magic in this caption.';
		$this->commentStatus    = 'closed';
		$this->date             = '2017-08-01 15:00:00';
		$this->dateGmt          = '2017-08-01 21:00:00';
		$this->description      = 'This is a magic description.';
		$this->filePath         = 'http://www.reactiongifs.com/r/mgc.gif';
		$this->fileType         = 'IMAGE_GIF';
		$this->slug             = 'magic-shia';
		$this->status           = 'INHERIT';
		$this->title            = 'Magic Shia Gif';
		$this->pingStatus       = 'closed';
		$this->parentId         = false;
		$this->clientMutationId = 'someUniqueId';

		/**
		 * Set up different roles for permissions testing
		 */
		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		parent::setUp();

	}

	/**
	 * This function is run after each method
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * This function tests the createMediaItem mutation
	 *
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemMutation() {

		/**
		 * Set up the createMediaItem mutation
		 */
		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
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
		    }
		  }
		}
		';

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$variables = [
			'input' => [
				'filePath'         => $this->filePath,
				'fileType'         => $this->fileType,
				'clientMutationId' => $this->clientMutationId,
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
			],
		];

		/**
		 * Set the current user to subscriber (someone who can't create posts)
		 * and test whether they can create posts
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:61
		 */
		wp_set_current_user( $this->subscriber );
		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the filePath to a URL that isn't valid to test whether the mediaItem will
		 * still get created
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:105
		 */
		$variables['input']['filePath'] = 'https://i-d-images.vice.com/images/2016/09/16/bill-murray-has-a-couple-of-shifts-at-a-brooklyn-bar-this-weekend-body-image-1473999364.jpg?crop=1xw:1xh;center,center&resize=1440:*';
		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['filePath'] = $this->filePath;

		/**
		 * Create a post as the admin and then try to upload a mediaItem
		 * to that post as an author. It should error out since Authors can't
		 * edit other users posts.
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:157
		 */
		$post = $this->factory()->post->create([
			'post_author' => $this->admin,
		] );
		wp_set_current_user( $this->author );
		$variables['input']['parentId'] = $post;
		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['parentId'] = $this->parentId;

		/**
		 * Set the current user as the admin role so we
		 * can properly test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Set the input variables to an empty array and then
		 * make the request with those empty input variables. We should
		 * get an error back from the source because they are required.
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:54
		 */
		$empty_variables = [];
		$actual = do_graphql_request( $mutation, 'createMediaItem', $empty_variables );
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Create the createMediaItem
		 */
		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );

		$expected = [
			'data' => [
				'createMediaItem' => [
					'clientMutationId' => $this->clientMutationId,
					'mediaItem' => [
						'title'            => $this->title,
						'description'      => apply_filters( 'the_content', $this->description ),
						'altText'          => $this->altText,
						'caption'          => apply_filters( 'the_content', $this->caption ),
						'commentStatus'    => $this->commentStatus,
						'date'             => $this->date,
						'dateGmt'          => $this->dateGmt,
						'slug'             => $this->slug,
						'status'           => strtolower( $this->status ),
						'pingStatus'       => $this->pingStatus,
						'mimeType'         => 'image/gif',
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * This function tests the updateMediaItem mutation
	 *
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemMutation() {

		/**
		 * Set up the createMediaItem mutation so that
		 * we can update it later
		 */
		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
		      id
		      mediaItemId
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
		    }
		  }
		}
		';

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$variables = [
			'input' => [
				'filePath'         => $this->filePath,
				'fileType'         => $this->fileType,
				'clientMutationId' => $this->clientMutationId,
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
			],
		];

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create a mediaItem to update and store it's WordPress post ID
		 * and it's WPGraphQL ID for using in our updateMediaItem mutation
		 */
		$media_item = do_graphql_request( $mutation, 'createMediaItem', $variables );
		$media_item_id = $media_item["data"]["createMediaItem"]["mediaItem"]["id"];
		$attachment_id = $media_item["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];

		$new_attachment = get_post( $attachment_id );
		$this->assertObjectHasAttribute( 'ID', $new_attachment );

		/**
		 * Prepare the updateMediaItem mutation
		 */
		$mutation = '
		mutation updateMediaItem( $input: updateMediaItemInput! ){
		  updateMediaItem (input: $input){
		    clientMutationId
		    mediaItem {
		      id
		      mediaItemId
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
		    }
		  }
		}
		';

		/**
		 * Set up the updateMediaItem variables
		 */
		$updated_title = 'Updated - Magic Shia Gif';
		$updated_description = 'This is an updated magic description.';
		$updated_altText = 'Some updated alt text';
		$updated_caption = 'Shia shows off some magic in this updated caption.';
		$updated_commentStatus = 'open';
		$updated_date = '2017-08-01 16:00:00';
		$updated_dateGmt = '2017-08-01 22:00:00';
		$updated_slug = 'updated-shia-magic';
		$updated_status = 'INHERIT';
		$updated_pingStatus = 'open';
		$updated_clientMutationId = 'someUpdatedUniqueId';

		/**
		 * Set the updateMediaItem input variables
		 */
		$variables = [
			'input' => [
				'id'               => $media_item_id,
				'clientMutationId' => $updated_clientMutationId,
				'title'            => $updated_title,
				'description'      => $updated_description,
				'altText'          => $updated_altText,
				'caption'          => $updated_caption,
				'commentStatus'    => $updated_commentStatus,
				'date'             => $updated_date,
				'dateGmt'          => $updated_dateGmt,
				'slug'             => $updated_slug,
				'status'           => $updated_status,
				'pingStatus'       => $updated_pingStatus,
			]
		];

		$variables['input']['id'] = 12345;


		/**
		 * Execute the request with a fake mediaItem id. An error
		 * should occur because we didn't pass the id of the mediaItem we
		 * wanted to update.
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:57
		 */
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['id'] = $media_item_id;

		/**
		 * Set the current user to a subscriber (someone who can't create posts)
		 * amd test whether they can create posts
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:72
		 */
		wp_set_current_user( $this->subscriber );
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Create a post as the admin and then try to upload a mediaItem
		 * to that post as an author. It should error out since Authors can't
		 * edit other users posts.
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:83
		 */
		$post = $this->factory()->post->create([
			'post_author' => $this->admin,
		] );
		wp_set_current_user( $this->author );
		$variables['input']['parentId'] = $post;
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['parentId'] = $this->parentId;

		/**
		 * Set the current user as the admin role so we
		 * successfully run the mutation
		 */
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $mutation, 'updateMediaItem', $variables );

		/**
		 * Define the expected output.
		 */
		$expected = [
			'data' => [
				'updateMediaItem' => [
					'clientMutationId' => $updated_clientMutationId,
					'mediaItem'             => [
						'id'               => $media_item_id,
						'title'            => $updated_title,
						'description'      => apply_filters( 'the_content', $updated_description ),
						'mediaItemId'      => $attachment_id,
						'altText'          => $updated_altText,
						'caption'          => apply_filters( 'the_content', $updated_caption ),
						'commentStatus'    => $updated_commentStatus,
						'date'             => $updated_date,
						'dateGmt'          => $updated_dateGmt,
						'slug'             => $updated_slug,
						'status'           => strtolower( $updated_status ),
						'pingStatus'       => $updated_pingStatus,
						'mimeType'         => 'image/gif',
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
	 * This function tests the deleteMediaItem mutation
	 *
	 * @access public
	 * @return void
	 */
	public function testDeleteMediaItemMutation() {

		/**
		 * Set up the createMediaItem mutation so
		 * we can delete it later
		 */
		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
		      id
		      mediaItemId
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
		    }
		  }
		}
		';

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$variables = [
			'input' => [
				'filePath'         => $this->filePath,
				'fileType'         => $this->fileType,
				'clientMutationId' => $this->clientMutationId,
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
			],
		];

		/**
		 * Set the current user as the admin role so we
		 * can test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create a mediaItem to test against
		 */
		$media_item = do_graphql_request( $mutation, 'createMediaItem', $variables );
		$media_item_id = $media_item["data"]["createMediaItem"]["mediaItem"]["id"];
		$attachment_id = $media_item["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];
		$new_attachment = get_post( $attachment_id );

		/**
		 * Verify the mediaItem was created with the original content as expected
		 */
		$this->assertEquals( $new_attachment->post_type, 'attachment' );
		$this->assertEquals( $new_attachment->post_title, $this->title );
		$this->assertEquals( $new_attachment->post_content, $this->description );

		/**
		 * Prepare the deleteMediaItem mutation
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
		 * Set the deleteMediaItem input variables
		 */
		$variables = [
			'input' => [
				'id'               => $media_item_id,
				'clientMutationId' => $this->clientMutationId,
				'forceDelete'      => true,
			]
		];

		/**
		 * Set the mediaItem id to a fake id and the mutation should fail
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemDelete.php:79
		 */
		$variables['input']['id'] = 12345;
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['id'] = $media_item_id;

		/**
		 * Set the current user as the subscriber role and
		 * the deletion should fail because we're a subscriber.
		 *
		 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemDelete.php:86
		 */
		wp_set_current_user( $this->subscriber );
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the user to an admin and try again
		 */
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );

		/**
		 * Define the expected output.
		 */
		$expected = [
			'data' => [
				'deleteMediaItem' => [
					'clientMutationId' => $this->clientMutationId,
					'mediaItem' => [
						'id'               => $media_item_id,
						'title'            => $this->title,
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
		 * Try to delete again but we should have errors, because there's nothing to be deleted
		 */
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );

	}

}
