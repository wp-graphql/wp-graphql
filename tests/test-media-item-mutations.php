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
	public $updated_title;
	public $updated_description;
	public $updated_altText;
	public $updated_caption;
	public $updated_commentStatus;
	public $updated_date;
	public $updated_dateGmt;
	public $updated_slug;
	public $updated_status;
	public $updated_pingStatus;
	public $updated_clientMutationId;

	public $create_variables;
	public $update_variables;
	public $delete_variables;

	public $subscriber;
	public $subscriber_name;
	public $author;
	public $author_name;
	public $admin;
	public $admin_name;

	public $attachment_id;
	public $media_item_id;

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
		 * Set up the updateMediaItem variables
		 */
		$this->updated_title = 'Updated - Magic Shia Gif';
		$this->updated_description = 'This is an updated magic description.';
		$this->updated_altText = 'Some updated alt text';
		$this->updated_caption = 'Shia shows off some magic in this updated caption.';
		$this->updated_commentStatus = 'open';
		$this->updated_date = '2017-08-01 16:00:00';
		$this->updated_dateGmt = '2017-08-01 22:00:00';
		$this->updated_slug = 'updated-shia-magic';
		$this->updated_status = 'INHERIT';
		$this->updated_pingStatus = 'open';
		$this->updated_clientMutationId = 'someUpdatedUniqueId';

		/**
		 * Set up different roles for permissions testing
		 */
		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );
		$this->subscriber_name = 'User ' . $this->subscriber;

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );
		$this->author_name = 'User ' . $this->author;

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );
		$this->admin_name = 'User ' . $this->admin;

		/**
		 * Create a mediaItem to update and store it's WordPress post ID
		 * and it's WPGraphQL ID for using in our updateMediaItem mutation
		 */
		$this->attachment_id = $this->factory()->attachment->create( ['post_mime_type' => 'image/gif'] );
		$this->media_item_id = \GraphQLRelay\Relay::toGlobalId( 'attachment', $this->attachment_id );

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$this->create_variables = [
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
		 * Set the updateMediaItem mutation input variables
		 */
		$this->update_variables = [
			'input' => [
				'id'               => $this->media_item_id,
				'clientMutationId' => $this->updated_clientMutationId,
				'title'            => $this->updated_title,
				'description'      => $this->updated_description,
				'altText'          => $this->updated_altText,
				'caption'          => $this->updated_caption,
				'commentStatus'    => $this->updated_commentStatus,
				'date'             => $this->updated_date,
				'dateGmt'          => $this->updated_dateGmt,
				'slug'             => $this->updated_slug,
				'status'           => $this->updated_status,
				'pingStatus'       => $this->updated_pingStatus,
				'authorId'           => \GraphQLRelay\Relay::toGlobalId( 'user', $this->admin ),
			]
		];

		/**
		 * Set the deleteMediaItem input variables
		 */
		$this->delete_variables = [
			'input' => [
				'id'               => $this->media_item_id,
				'clientMutationId' => $this->clientMutationId,
				'forceDelete'      => true,
			]
		];

		parent::setUp();

	}

	/**
	 * This function is run after each method
	 *
	 * @access public
	 * @return void
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
	public function createMediaItemMutation() {

		/**
		 * Set up the createMediaItem mutation
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

		$actual = do_graphql_request( $mutation, 'createMediaItem', $this->create_variables );

		return $actual;
	}

	/**
	 * Test with a local file path. This is going to fail because the file
	 * does not exist on the test server.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php:81
	 */
	public function testCreateMediaItemFilePath() {
		wp_set_current_user( $this->admin );
		$this->create_variables['input']['filePath'] = "file:///Users/hdevore/Desktop/Current/colorado_lake.jpeg";
		$actual = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['filePath'] = $this->filePath;
	}

	/**
	 * Set the input variables to an empty array and then
	 * make the request with those empty input variables. We should
	 * get an error back from the source because they are required.
	 *
	 * @source WPGraphql - createMediaItemInput!
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemNoInput() {

		/**
		 * Set up the createMediaItem mutation
		 */
		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
		      id
		    }
		  }
		}
		';

		$empty_variables = '';
		$actual = do_graphql_request( $mutation, 'createMediaItem', $empty_variables );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Set the current user to subscriber (someone who can't create posts)
	 * and test whether they can create posts
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:61
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemAsSubscriber() {
		wp_set_current_user( $this->subscriber );
		$actual = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Set the current user to subscriber (someone who can't create posts)
	 * and test whether they can create posts with someone else's id
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:61
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemOtherAuthor() {

		/**
		 * Set up the createMediaItem mutation
		 */
		$mutation = '
		mutation createMediaItem( $input: createMediaItemInput! ){
		  createMediaItem(input: $input){
		    clientMutationId
		    mediaItem{
		      id
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
				'authorId'         => $this->admin,
			],
		];

		wp_set_current_user( $this->author );
		$actual = do_graphql_request( $mutation, 'createMediaItem', $variables );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Test whether we need to include the file.php file
	 * from the wp-admin
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php:76
	 */
	public function testCreateMediaItemRequireFilePhp() {

		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		wp_set_current_user( $this->admin );
		$actual = $this->createMediaItemMutation();

		$media_item_id = $actual["data"]["createMediaItem"]["mediaItem"]["id"];
		$attachment_id = $actual["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];

		$expected = [
			'data' => [
				'createMediaItem' => [
					'clientMutationId' => $this->clientMutationId,
					'mediaItem' => [
						'id'               => $media_item_id,
						'mediaItemId'      => $attachment_id,
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
	 * Test whether we need to include the image.php file
	 * from the wp-admin
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php:167
	 */
	public function testCreateMediaItemRequireImagePhp() {

		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		wp_set_current_user( $this->admin );
		$actual = $this->createMediaItemMutation();

		$media_item_id = $actual["data"]["createMediaItem"]["mediaItem"]["id"];
		$attachment_id = $actual["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];

		$expected = [
			'data' => [
				'createMediaItem' => [
					'clientMutationId' => $this->clientMutationId,
					'mediaItem' => [
						'id'               => $media_item_id,
						'mediaItemId'      => $attachment_id,
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
	 * Set the filePath to a URL that isn't valid to test whether the mediaItem will
	 * still get created
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:98
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemWithInvalidUrl() {
		wp_set_current_user( $this->author );
		$this->create_variables['input']['filePath'] = 'htt://vice.co.um/images/2016/09/16/bill-murray-has-a-couple-of-shifts-at-a-brooklyn-bar-this-weekend-body-image-1473999364.jpg?crop=1xw:1xh;center,center&resize=1440:*';
		$actual = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['filePath'] = $this->filePath;
	}

	/**
	 * Set the filePath to a URL that isn't valid to test whether the mediaItem will
	 * still get created
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:105
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemWithNoFile() {
		wp_set_current_user( $this->author );
		$this->create_variables['input']['filePath'] = 'https://i-d-images.vice.com/images/2016/09/16/bill-murray-has-a-couple-of-shifts-at-a-brooklyn-bar-this-weekend-body-image-1473999364.jpg?crop=1xw:1xh;center,center&resize=1440:*';
		$actual = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['filePath'] = $this->filePath;
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an author. It should error out since Authors can't
	 * edit other users posts.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:157
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemEditOthersPosts() {
		$post = $this->factory()->post->create( [
			'post_author' => $this->admin,
		] );
		wp_set_current_user( $this->author );
		$this->create_variables['input']['parentId'] = $post;
		$actual = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['parentId'] = $this->parentId;
	}

	/**
	 * This function tests the createMediaItem mutation
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php
	 * @access public
	 * @return void
	 */
	public function testCreateMediaItemMutation() {

		/**
		 * Set the current user as the admin role so we
		 * can properly test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create the createMediaItem
		 */
		$actual = $this->createMediaItemMutation();

		$media_item_id = $actual["data"]["createMediaItem"]["mediaItem"]["id"];
		$attachment_id = $actual["data"]["createMediaItem"]["mediaItem"]["mediaItemId"];

		$expected = [
			'data' => [
				'createMediaItem' => [
					'clientMutationId' => $this->clientMutationId,
					'mediaItem' => [
						'id'               => $media_item_id,
						'mediaItemId'      => $attachment_id,
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
	 * Update a media item using the updateMediaItem mutation
	 *
	 * @access public
	 * @return array $actual
	 */
	public function updateMediaItemMutation() {

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
		      author {
		        id
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $mutation, 'updateMediaItem', $this->update_variables );

		return $actual;
	}

	/**
	 * Execute the request with a fake mediaItem id. An error
	 * should occur because we didn't pass the id of the mediaItem we
	 * wanted to update.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:57
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemInvalidId() {
		$this->update_variables['input']['id'] = '12345';
		$actual = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * Test whether the mediaItem we're updating is actually a mediaItem
	 *
	 * @souce wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemUpdate.php:63
	 */
	public function testUpdateMediaItemUpdatePost() {
		$test_post = $this->factory()->post->create();
		$this->update_variables['input']['id'] = \GraphQLRelay\Relay::toGlobalId( 'post', $test_post );
		$actual = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * Set the current user to a subscriber (someone who can't create posts)
	 * amd test whether they can create posts
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:72
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemAsSubscriber() {
		wp_set_current_user( $this->subscriber );
		$actual = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an author. It should error out since Authors can't
	 * edit other users posts.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:83
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemEditOthersPosts() {
		$post = $this->factory()->post->create( [
			'post_author' => $this->admin,
		] );
		wp_set_current_user( $this->author );
		$variables['input']['parentId'] = $post;
		$actual = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['parentId'] = $this->parentId;
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an author. It should error out since Authors can't
	 * edit other users posts.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:83
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemAddOtherAuthorsAsAuthor() {
		wp_set_current_user( $this->author );
		$variables['input']['authorId'] = \GraphQLRelay\Relay::toGlobalId( 'user', $this->admin );
		$actual = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$variables['input']['authorId'] = false;
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an admin. It should be created.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:83
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemAddOtherAuthorsAsAdmin() {
		wp_set_current_user( $this->admin );
		$variables['input']['authorId'] = \GraphQLRelay\Relay::toGlobalId( 'user', $this->author );
		$actual = $this->updateMediaItemMutation();
		$actual_created = $actual['data']['updateMediaItem']['mediaItem'];
		$this->assertArrayHasKey( 'id', $actual_created );
		$variables['input']['authorId'] = false;
	}

	/**
	 * This function tests the updateMediaItem mutation
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemUpdate.php
	 * @access public
	 * @return void
	 */
	public function testUpdateMediaItemMutation() {

		/**
		 * Set the current user as the admin role so we
		 * successfully run the mutation
		 */
		wp_set_current_user( $this->admin );

		$actual = $this->updateMediaItemMutation();

		/**
		 * Define the expected output.
		 */
		$expected = [
			'data' => [
				'updateMediaItem' => [
					'clientMutationId' => $this->updated_clientMutationId,
					'mediaItem'             => [
						'id'               => $this->media_item_id,
						'title'            => apply_filters( 'the_title', $this->updated_title ),
						'description'      => apply_filters( 'the_content', $this->updated_description ),
						'mediaItemId'      => $this->attachment_id,
						'altText'          => $this->updated_altText,
						'caption'          => apply_filters( 'the_content', $this->updated_caption ),
						'commentStatus'    => $this->updated_commentStatus,
						'date'             => $this->updated_date,
						'dateGmt'          => $this->updated_dateGmt,
						'slug'             => $this->updated_slug,
						'status'           => strtolower( $this->updated_status ),
						'pingStatus'       => $this->updated_pingStatus,
						'mimeType'         => 'image/gif',
						'author'           => [
							'id'       => \GraphQLRelay\Relay::toGlobalId( 'user', $this->admin ),
						],
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
	 * Delete mediaItems using the deleteMediaItem mutation
	 *
	 * @access public
	 * @return array $actual
	 */
	public function deleteMediaItemMutation() {

		/**
		 * Prepare the deleteMediaItem mutation
		 */
		$mutation = '
		mutation deleteMediaItem( $input: deleteMediaItemInput! ){
		  deleteMediaItem(input: $input) {
		    clientMutationId
		    mediaItem{
		      id
		      mediaItemId
		    }
		  }
		}
		';

		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $this->delete_variables );

		return $actual;
	}

	/**
	 * Set the mediaItem id to a fake id and the mutation should fail
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemDelete.php:79
	 * @access public
	 * @return void
	 */
	public function testDeleteMediaItemInvalidId() {
		$this->delete_variables['input']['id'] = 12345;
		$actual = $this->deleteMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->delete_variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * Set the current user as the subscriber role and
	 * the deletion should fail because we're a subscriber.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemDelete.php:86
	 * @access public
	 * @return void
	 */
	public function testDeleteMediaItemAsSubscriber() {
		wp_set_current_user( $this->subscriber );
		$actual = $this->deleteMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Set the force delete input to false and the
	 *
	 * @access public
	 * @return array $actual
	 */
	public function testDeleteMediaItemAlreadyInTrash() {

		$deleted_media_item = $this->factory()->attachment->create( ['post_status' => 'trash'] );
		$post = get_post( $deleted_media_item );

		/**
		 * Prepare the deleteMediaItem mutation
		 */
		$mutation = '
		mutation deleteMediaItem( $input: deleteMediaItemInput! ){
		  deleteMediaItem(input: $input) {
		    clientMutationId
		    mediaItem{
		      id
		      mediaItemId
		    }
		  }
		}
		';

		/**
		 * Set the deleteMediaItem input variables
		 */
		$delete_trash_variables = [
			'input' => [
				'id'               => \GraphQLRelay\Relay::toGlobalId( 'attachment', $deleted_media_item ),
				'clientMutationId' => $this->clientMutationId,
				'forceDelete'      => false,
			]
		];

		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $delete_trash_variables );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * This function tests the deleteMediaItem mutation
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemDelete.php
	 * @access public
	 * @return void
	 */
	public function testDeleteMediaItemMutation() {

		/**
		 * Set the user to an admin and try again
		 */
		wp_set_current_user( $this->admin );
		$actual = $this->deleteMediaItemMutation();

		/**
		 * Define the expected output.
		 */
		$expected = [
			'data' => [
				'deleteMediaItem' => [
					'clientMutationId' => $this->clientMutationId,
					'mediaItem' => [
						'id'               => $this->media_item_id,
						'mediaItemId'      => $this->attachment_id,
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
		$actual = $this->deleteMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );

	}

}
