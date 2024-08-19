<?php

class MediaItemMutationsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {


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
	public $title;
	public $parentId;
	public $updated_title;
	public $updated_description;
	public $updated_altText;
	public $updated_caption;
	public $updated_commentStatus;
	public $updated_date;
	public $updated_dateGmt;
	public $updated_slug;
	public $updated_status;

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

	public function setUp(): void {
		// before
		parent::setUp();

		// We don't want this funking with our tests
		remove_image_size( 'twentyseventeen-thumbnail-avatar' );

		/**
		 * Set up different user roles for permissions testing
		 */
		$this->subscriber      = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);
		$this->subscriber_name = 'User ' . $this->subscriber;

		$this->author      = $this->factory()->user->create(
			[
				'role' => 'author',
			]
		);
		$this->author_name = 'User ' . $this->author;

		$this->admin      = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->admin_name = 'User ' . $this->admin;

		/**
		 * Populate the mediaItem input fields
		 */
		$this->altText       = 'A gif of Shia doing Magic.';
		$this->authorId      = $this->admin;
		$this->caption       = 'Shia shows off some magic in this caption.';
		$this->commentStatus = 'closed';
		$this->date          = '2017-08-01T15:00:00';
		$this->dateGmt       = '2017-08-01T21:00:00';
		$this->description   = 'This is a magic description.';
		$this->filePath      = 'https://content.wpgraphql.com/wp-content/uploads/2020/12/mgc.gif';
		$this->fileType      = 'IMAGE_GIF';
		$this->slug          = 'magic-shia';
		$this->status        = 'INHERIT';
		$this->title         = 'Magic Shia Gif';
		$this->parentId      = null;

		/**
		 * Set up the updateMediaItem variables
		 */
		$this->updated_title         = 'Updated Magic Shia Gif';
		$this->updated_description   = 'This is an updated magic description.';
		$this->updated_altText       = 'Some updated alt text';
		$this->updated_caption       = 'Shia shows off some magic in this updated caption.';
		$this->updated_commentStatus = 'open';
		$this->updated_date          = '2017-08-01T16:00:00';
		$this->updated_dateGmt       = '2017-08-01T22:00:00';
		$this->updated_slug          = 'updated-shia-magic';
		$this->updated_status        = 'INHERIT';

		/**
		 * Create a mediaItem to update and store it's WordPress post ID
		 * and it's WPGraphQL ID for using in our updateMediaItem mutation
		 */
		$this->attachment_id = $this->factory()->attachment->create(
			[
				'post_mime_type' => 'image/gif',
				'post_author'    => $this->admin,
			]
		);
		$this->media_item_id = \GraphQLRelay\Relay::toGlobalId( 'post', $this->attachment_id );

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$this->create_variables = [
			'input' => [
				'filePath'      => $this->filePath,
				'fileType'      => $this->fileType,
				'title'         => $this->title,
				'description'   => $this->description,
				'altText'       => $this->altText,
				'parentId'      => $this->parentId,
				'caption'       => $this->caption,
				'commentStatus' => $this->commentStatus,
				'date'          => $this->date,
				'dateGmt'       => $this->dateGmt,
				'slug'          => $this->slug,
				'status'        => $this->status,
				'authorId'      => $this->authorId,
			],
		];

		/**
		 * Set the updateMediaItem mutation input variables
		 */
		$this->update_variables = [
			'input' => [
				'id'            => $this->media_item_id,
				'title'         => $this->updated_title,
				'description'   => $this->updated_description,
				'altText'       => $this->updated_altText,
				'caption'       => $this->updated_caption,
				'commentStatus' => $this->updated_commentStatus,
				'date'          => $this->updated_date,
				'dateGmt'       => $this->updated_dateGmt,
				'slug'          => $this->updated_slug,
				'status'        => $this->updated_status,
				'fileType'      => $this->fileType,
			],
		];

		/**
		 * Set the deleteMediaItem input variables
		 */
		$this->delete_variables = [
			'input' => [
				'id'          => $this->media_item_id,
				'forceDelete' => true,
			],
		];
	}

	public function tearDown(): void {
		// your tear down methods here

		// then
		parent::tearDown();
	}

	/**
	 * This function tests the createMediaItem mutation
	 * and is reused throughout the createMediaItem tests
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php
	 * @return array $actual
	 */
	public function createMediaItemMutation() {
		/**
		 * Set up the createMediaItem mutation
		 */
		$query = '
			mutation createMediaItem( $input: CreateMediaItemInput! ){
				createMediaItem(input: $input){
					mediaItem{
						id
						databaseId
						mediaType
						date
						dateGmt
						slug
						status
						title
						commentStatus
						altText
						caption
						description
						mimeType
						parent {
							node {
							... on Post {
								id
							}
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

		return $this->graphql(
			[
				'query'     => $query,
				'variables' => $this->create_variables,
			]
		);
	}

	/**
	 * Set the current user to subscriber (someone who can't create posts)
	 * and test whether they can create posts
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:54
	 * @return void
	 */
	public function testCreateMediaItemAsSubscriber() {
		wp_set_current_user( $this->subscriber );
		$actual = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Test with a local file path. This is going to fail because the file
	 * does not exist on the test server.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php:89
	 * @return void
	 */
	public function testCreateMediaItemFilePath() {
		wp_set_current_user( $this->admin );
		$this->create_variables['input']['filePath'] = 'file:///Users/hdevore/Desktop/Current/colorado_lake.jpeg';
		$actual                                      = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['filePath'] = $this->filePath;
	}

	/**
	 * Set the input variables to an empty array and then
	 * make the request with those empty input variables. We should
	 * get an error back from the source because they are required.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php:211
	 * @return void
	 */
	public function testCreateMediaItemNoInput() {

		/**
		 * Set up the createMediaItem mutation
		 */
		$query = '
		mutation createMediaItem( $input: CreateMediaItemInput! ){
			createMediaItem(input: $input){
				mediaItem{
					id
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => '',
			]
		);
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Test whether the current can create posts with someone else's id
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:61
	 * @return void
	 */
	public function testCreateMediaItemOtherAuthor() {

		/**
		 * Set up the createMediaItem mutation
		 */
		$query = '
		mutation createMediaItem( $input: CreateMediaItemInput! ){
			createMediaItem(input: $input){
				mediaItem{
					id
					author {
						node {
							name
							databaseId
						}
					}
				}
			}
		}
		';

		/**
		 * Set the createMediaItem mutation input variables
		 */
		$variables = [
			'input' => [
				'filePath'      => $this->filePath,
				'fileType'      => $this->fileType,
				'title'         => $this->title,
				'description'   => $this->description,
				'altText'       => $this->altText,
				'parentId'      => $this->parentId,
				'caption'       => $this->caption,
				'commentStatus' => $this->commentStatus,
				'date'          => $this->date,
				'dateGmt'       => $this->dateGmt,
				'slug'          => $this->slug,
				'status'        => $this->status,
				'authorId'      => $this->admin,
			],
		];

		wp_set_current_user( $this->author );
		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with permissions
		wp_set_current_user( $this->admin );

		// test with database Id
		$variables['input']['authorId'] = $this->author;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->author, $actual['data']['createMediaItem']['mediaItem']['author']['node']['databaseId'] );

		// test with global Id
		$variables['input']['authorId'] = \GraphQLRelay\Relay::toGlobalId( 'user', $this->author );

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->author, $actual['data']['createMediaItem']['mediaItem']['author']['node']['databaseId'] );
	}

	/**
	 * Set the filePath to a URL that isn't valid to test whether the mediaItem will
	 * still get created
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:89
	 * @return void
	 */
	public function testCreateMediaItemWithInvalidUrl() {
		wp_set_current_user( $this->author );
		$this->create_variables['input']['filePath'] = 'htt://vice.co.um/images/2016/09/16/bill-murray-has-a-couple-of-shifts-at-a-brooklyn-bar-this-weekend-body-image-1473999364.jpg?crop=1xw:1xh;center,center&resize=1440:*';
		$actual                                      = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['filePath'] = $this->filePath;
	}

	/**
	 * Set the filePath to a URL that isn't valid to test whether the mediaItem will
	 * still get created
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:121
	 * @return void
	 */
	public function testCreateMediaItemWithNoFile() {
		wp_set_current_user( $this->author );
		$this->create_variables['input']['filePath'] = 'https://i-d-images.vice.com/images/2016/09/16/bill-murray-has-a-couple-of-shifts-at-a-brooklyn-bar-this-weekend-body-image-1473999364.jpg?crop=1xw:1xh;center,center&resize=1440:*';
		$actual                                      = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['filePath'] = $this->filePath;
	}

	/**
	 * Create a post as the admin and then attach the media item
	 * it should fail at first when we try as an author but then
	 * succeed as an admin
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php:142
	 * @return void
	 */
	public function testCreateMediaItemAttachToParentAsAuthor() {
		$post                                        = $this->factory()->post->create(
			[
				'post_author' => $this->admin,
				'post_status' => 'publish',
			]
		);
		$this->create_variables['input']['parentId'] = absint( $post );

		/**
		 * Test the mutation as someone who can't edit the parent post,
		 * this should fail
		 */
		wp_set_current_user( $this->author );
		$actual = $this->createMediaItemMutation();

		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testCreateMediaItemAttachToParentAsAdmin() {

		$post                                        = $this->factory()->post->create(
			[
				'post_author' => $this->admin,
				'post_status' => 'publish',
			]
		);
		$this->create_variables['input']['parentId'] = absint( $post );

		wp_set_current_user( $this->admin );
		// Test with databaseId
		$actual = $this->createMediaItemMutation();

		$media_item_id      = $actual['data']['createMediaItem']['mediaItem']['id'];
		$attachment_id      = $actual['data']['createMediaItem']['mediaItem']['databaseId'];
		$attachment_url     = wp_get_attachment_url( $attachment_id );
		$attachment_details = wp_get_attachment_metadata( $attachment_id );

		$expected = [
			'createMediaItem' => [
				'mediaItem' => [
					'id'            => $media_item_id,
					'databaseId'    => $attachment_id,
					'title'         => $this->title,
					'description'   => apply_filters( 'the_content', $this->description ),
					'altText'       => $this->altText,
					'caption'       => apply_filters( 'the_content', $this->caption ),
					'commentStatus' => $this->commentStatus,
					'date'          => $this->date,
					'dateGmt'       => $this->dateGmt,
					'slug'          => $this->slug,
					'status'        => strtolower( $this->status ),
					'mimeType'      => 'image/gif',
					'parent'        => [
						'node' => [
							'id' => \GraphQLRelay\Relay::toGlobalId( 'post', $post ),
						],
					],
					'mediaType'     => 'image',
					'sourceUrl'     => $attachment_url,
					'mediaDetails'  => [
						'file'   => $attachment_details['file'],
						'height' => $attachment_details['height'],
						'meta'   => [
							'aperture'         => 0.0,
							'credit'           => '',
							'camera'           => '',
							'caption'          => '',
							'createdTimestamp' => null,
							'copyright'        => '',
							'focalLength'      => null,
							'iso'              => 0,
							'shutterSpeed'     => null,
							'title'            => '',
							'orientation'      => '0',
						],
						'width'  => $attachment_details['width'],
						'sizes'  => [
							0 => [
								'name'      => 'thumbnail',
								'file'      => $attachment_details['sizes']['thumbnail']['file'],
								'width'     => (int) $attachment_details['sizes']['thumbnail']['width'],
								'height'    => (int) $attachment_details['sizes']['thumbnail']['height'],
								'mimeType'  => $attachment_details['sizes']['thumbnail']['mime-type'],
								'sourceUrl' => wp_get_attachment_image_src( $attachment_id, 'thumbnail' )[0],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

		// Test with globalId
		$this->create_variables['input']['parentId'] = \GraphQLRelay\Relay::toGlobalId( 'post', $this->parentId );

		$actual = $this->createMediaItemMutation();
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->create_variables['input']['parentId'] = $this->parentId;
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an author. It should error out since Authors can't
	 * edit other users posts.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemCreate.php:151
	 * @return void
	 */
	public function testCreateMediaItemEditOthersPosts() {
		$post = $this->factory()->post->create(
			[
				'post_author' => $this->admin,
			]
		);
		wp_set_current_user( $this->author );
		$this->create_variables['input']['parentId'] = $post;
		$actual                                      = $this->createMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->create_variables['input']['parentId'] = $this->parentId;
	}

	/**
	 * Test the MediaItemMutation by setting the default values: post_status
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemMutation.php:136
	 *
	 * post_title
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemMutation.php:142
	 *
	 * post_author
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemMutation.php:148
	 *
	 * post_content
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemMutation.php:165
	 *
	 * post_mime_type
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemMutation.php:171
	 *
	 * @return void
	 */
	public function testCreateMediaItemDefaultValues() {
		/**
		 * Set the current user as the admin role so we
		 * can properly test the mutation
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Set up the createMediaItem mutation
		 */
		$query = '
		mutation createMediaItem( $input: CreateMediaItemInput! ){
			createMediaItem(input: $input){
				mediaItem{
					id
					databaseId
					status
					title
					author {
						node {
							id
						}
					}
					description
					mimeType
					parent {
						node {
								... on Post {
									id
							}
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

		/**
		 * Set new input variables without changing defaults
		 */
		$variables = [
			'input' => [
				'filePath' => $this->filePath,
			],
		];

		/**
		 * Do the graphQL request using the above variables for input in the above mutation
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$media_item_id      = $actual['data']['createMediaItem']['mediaItem']['id'];
		$attachment_id      = $actual['data']['createMediaItem']['mediaItem']['databaseId'];
		$attachment_data    = get_post( $attachment_id );
		$attachment_title   = $attachment_data->post_title;
		$attachment_url     = wp_get_attachment_url( $attachment_id );
		$attachment_details = wp_get_attachment_metadata( $attachment_id );

		$expected = [
			'createMediaItem' => [
				'mediaItem' => [
					'id'           => $media_item_id,
					'databaseId'   => $attachment_id,
					'status'       => strtolower( $this->status ),
					'title'        => $attachment_title,
					'description'  => '',
					'mimeType'     => 'image/gif',
					'author'       => [
						'node' => [
							'id' => \GraphQLRelay\Relay::toGlobalId( 'user', $this->admin ),
						],
					],
					'parent'       => null,
					'sourceUrl'    => $attachment_url,
					'mediaDetails' => [
						'file'   => $attachment_details['file'],
						'height' => $attachment_details['height'],
						'meta'   => [
							'aperture'         => 0.0,
							'credit'           => '',
							'camera'           => '',
							'caption'          => '',
							'createdTimestamp' => null,
							'copyright'        => '',
							'focalLength'      => null,
							'iso'              => 0,
							'shutterSpeed'     => null,
							'title'            => '',
							'orientation'      => '0',
						],
						'width'  => $attachment_details['width'],
						'sizes'  => [
							0 => [
								'name'      => 'thumbnail',
								'file'      => $attachment_details['sizes']['thumbnail']['file'],
								'width'     => (int) $attachment_details['sizes']['thumbnail']['width'],
								'height'    => (int) $attachment_details['sizes']['thumbnail']['height'],
								'mimeType'  => $attachment_details['sizes']['thumbnail']['mime-type'],
								'sourceUrl' => wp_get_attachment_image_src( $attachment_id, 'thumbnail' )[0],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * This function tests the createMediaItem mutation
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemCreate.php
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

		$media_item_id      = $actual['data']['createMediaItem']['mediaItem']['id'];
		$attachment_id      = $actual['data']['createMediaItem']['mediaItem']['databaseId'];
		$attachment_url     = wp_get_attachment_url( $attachment_id );
		$attachment_details = wp_get_attachment_metadata( $attachment_id );

		$expected = [
			'createMediaItem' => [
				'mediaItem' => [
					'id'            => $media_item_id,
					'databaseId'    => $attachment_id,
					'title'         => $this->title,
					'description'   => apply_filters( 'the_content', $this->description ),
					'altText'       => $this->altText,
					'caption'       => apply_filters( 'the_content', $this->caption ),
					'commentStatus' => $this->commentStatus,
					'date'          => $this->date,
					'dateGmt'       => $this->dateGmt,
					'slug'          => $this->slug,
					'status'        => strtolower( $this->status ),
					'mimeType'      => 'image/gif',
					'parent'        => null,
					'mediaType'     => 'image',
					'sourceUrl'     => $attachment_url,
					'mediaDetails'  => [
						'file'   => $attachment_details['file'],
						'height' => $attachment_details['height'],
						'meta'   => [
							'aperture'         => 0.0,
							'credit'           => '',
							'camera'           => '',
							'caption'          => '',
							'createdTimestamp' => null,
							'copyright'        => '',
							'focalLength'      => null,
							'iso'              => 0,
							'shutterSpeed'     => null,
							'title'            => '',
							'orientation'      => '0',
						],
						'width'  => $attachment_details['width'],
						'sizes'  => [
							0 => [
								'name'      => 'thumbnail',
								'file'      => $attachment_details['sizes']['thumbnail']['file'],
								'width'     => (int) $attachment_details['sizes']['thumbnail']['width'],
								'height'    => (int) $attachment_details['sizes']['thumbnail']['height'],
								'mimeType'  => $attachment_details['sizes']['thumbnail']['mime-type'],
								'sourceUrl' => wp_get_attachment_image_src( $attachment_id, 'thumbnail' )[0],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * This function tests the updateMediaItem mutation
	 * and is reused throughout the updateMediaItem tests
	 *
	 * @return array $actual
	 */
	public function updateMediaItemMutation() {

		/**
		 * Prepare the updateMediaItem mutation
		 */
		$query = '
		mutation updateMediaItem( $input: UpdateMediaItemInput! ){
			updateMediaItem (input: $input){
				mediaItem {
					id
					databaseId
					date
					dateGmt
					slug
					status
					title
					commentStatus
					altText
					caption
					description
					mimeType
					author {
						node {
							databaseId
						}
					}
				}
			}
		}
		';

		return $this->graphql(
			[
				'query'     => $query,
				'variables' => $this->update_variables,
			]
		);
	}

	/**
	 * Execute the request with a fake mediaItem id. An error
	 * should occur because we didn't pass the id of the mediaItem we
	 * wanted to update.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:57
	 * @return void
	 */
	public function testUpdateMediaItemInvalidId() {
		$this->update_variables['input']['id'] = \GraphQLRelay\Relay::toGlobalId( 'post', 123456 );
		$actual                                = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->update_variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * Test whether the mediaItem we're updating is actually a mediaItem
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemUpdate.php:67
	 * @return void
	 */
	public function testUpdateMediaItemUpdatePost() {
		$test_post                             = $this->factory()->post->create();
		$this->update_variables['input']['id'] = \GraphQLRelay\Relay::toGlobalId( 'post', $test_post );
		$actual                                = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->update_variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * Set the current user to a subscriber (someone who can't create posts)
	 * and test whether they can create posts
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:74
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
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:91
	 * @return void
	 */
	public function testUpdateMediaItemEditOthersPosts() {
		$post = $this->factory()->post->create(
			[
				'post_author' => $this->admin,
			]
		);
		wp_set_current_user( $this->author );
		$this->update_variables['input']['parentId'] = $post;
		$actual                                      = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->update_variables['input']['parentId'] = $this->parentId;
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an author. It should error out since Authors can't
	 * edit other users posts.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:91
	 * @return void
	 */
	public function testUpdateMediaItemAddOtherAuthorsAsAuthor() {
		wp_set_current_user( $this->author );
		$this->update_variables['input']['authorId'] = $this->admin;
		$actual                                      = $this->updateMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->update_variables['input']['authorId'] = false;
	}

	/**
	 * Create a post as the admin and then try to upload a mediaItem
	 * to that post as an admin. It should be created.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemUpdate.php:91
	 * @return void
	 */
	public function testUpdateMediaItemAddOtherAuthorsAsAdmin() {
		wp_set_current_user( $this->admin );

		// Test as databaseId
		$this->update_variables['input']['authorId'] = $this->author;
		$actual                                      = $this->updateMediaItemMutation();

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->author, $actual['data']['updateMediaItem']['mediaItem']['author']['node']['databaseId'] );

		// Test as global Id
		$this->update_variables['input']['authorId'] = \GraphQLRelay\Relay::toGlobalId( 'user', $this->author );
		$actual                                      = $this->updateMediaItemMutation();

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->author, $actual['data']['updateMediaItem']['mediaItem']['author']['node']['databaseId'] );
	}

	/**
	 * This function tests the updateMediaItem mutation
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemUpdate.php
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
			'updateMediaItem' => [
				'mediaItem' => [
					'id'            => $this->media_item_id,
					'title'         => $this->updated_title,
					'description'   => apply_filters( 'the_content', $this->updated_description ),
					'databaseId'    => $this->attachment_id,
					'altText'       => $this->updated_altText,
					'caption'       => apply_filters( 'the_content', $this->updated_caption ),
					'commentStatus' => $this->updated_commentStatus,
					'date'          => $this->updated_date,
					'dateGmt'       => $this->updated_dateGmt,
					'slug'          => $this->updated_slug,
					'status'        => strtolower( $this->updated_status ),
					'mimeType'      => 'image/gif',
					'author'        => [
						'node' => [
							'databaseId' => $this->admin,
						],
					],
				],
			],
		];

		get_post( $this->attachment_id );

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * This function tests the deleteMediaItem mutation
	 * and is reused throughout the deleteMediaItem tests
	 *
	 * @return array $actual
	 */
	public function deleteMediaItemMutation() {

		/**
		 * Prepare the deleteMediaItem mutation
		 */
		$mutation = '
		mutation deleteMediaItem( $input: DeleteMediaItemInput! ){
			deleteMediaItem(input: $input) {
				deletedId
				mediaItem{
					id
					databaseId
				}
			}
		}
		';

		return $this->graphql(
			[
				'query'     => $mutation,
				'variables' => $this->delete_variables,
			]
		);
	}

	/**
	 * Set the mediaItem id to a fake id and the mutation should fail
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemDelete.php:79
	 * @return void
	 */
	public function testDeleteMediaItemInvalidId() {
		$this->delete_variables['input']['id'] = 12345;
		$actual                                = $this->deleteMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$this->delete_variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * Set the current user as the subscriber role and
	 * the deletion should fail because we're a subscriber.
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/MediaItemDelete.php:86
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
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemDelete.php:92
	 * @return array $actual
	 */
	public function testDeleteMediaItemAlreadyInTrash() {

		$deleted_media_item = $this->factory()->attachment->create( [ 'post_status' => 'trash' ] );
		$post               = get_post( $deleted_media_item );

		/**
		 * Prepare the deleteMediaItem mutation
		 */
		$mutation = '
		mutation deleteMediaItem( $input: DeleteMediaItemInput! ){
			deleteMediaItem(input: $input) {
				deletedId
				mediaItem {
					id
					databaseId
				}
			}
		}
		';

		/**
		 * Set the deleteMediaItem input variables
		 */
		$delete_trash_variables = [
			'input' => [
				'id'          => \GraphQLRelay\Relay::toGlobalId( 'post', $deleted_media_item ),
				'forceDelete' => false,
			],
		];

		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $mutation, 'deleteMediaItem', $delete_trash_variables );

		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Set the force delete input to false and the
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemDelete.php:92
	 * @return array $actual
	 */
	public function testForceDeleteMediaItemAlreadyInTrash() {

		$deleted_media_item = $this->factory()->attachment->create( [ 'post_status' => 'trash' ] );

		/**
		 * Prepare the deleteMediaItem mutation
		 */
		$mutation = '
		mutation deleteMediaItem( $input: DeleteMediaItemInput! ){
			deleteMediaItem(input: $input) {
				deletedId
				mediaItem {
					id
					databaseId
				}
			}
		}
		';

		/**
		 * Set the deleteMediaItem input variables
		 */
		$delete_trash_variables = [
			'input' => [
				'id'          => \GraphQLRelay\Relay::toGlobalId( 'post', $deleted_media_item ),
				'forceDelete' => false,
			],
		];

		wp_set_current_user( $this->admin );

		$delete_trash_variables['input']['forceDelete'] = true;
		$actual              = do_graphql_request( $mutation, 'deleteMediaItem', $delete_trash_variables );
		$actual_deleted_item = $actual['data']['deleteMediaItem'];
		$this->assertArrayHasKey( 'deletedId', $actual_deleted_item );
	}

	/**
	 * This function tests the deleteMediaItem mutation by trying to delete a post
	 * instead of an attachment
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemDelete.php:103
	 * @return void
	 */
	public function testDeleteMediaItemAsPost() {

		/**
		 * Set the user to an admin
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create a post that we can try to delete with the deleteMediaItem mutaton
		 */
		$args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Original Title for MediaItemMutationsTest',
			'post_content' => 'Original Content',
		];

		/**
		 * Create a page to test against and set the post id in the mutation variables
		 */
		$post_to_delete                        = $this->factory->post->create( $args );
		$this->delete_variables['input']['id'] = \GraphQLRelay\Relay::toGlobalId( 'post', $post_to_delete );

		/**
		 * Define the expected output
		 */
		$actual = $this->deleteMediaItemMutation();

		/*
		 * Compare it to the actual output and reset the id delete variable
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->delete_variables['input']['id'] = $this->media_item_id;
	}

	/**
	 * This function tests the deleteMediaItem mutation
	 *
	 * @source wp-content/plugins/wp-graphql/src/Type/MediaItem/Mutation/MediaItemDelete.php
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
			'deleteMediaItem' => [
				'deletedId' => $this->media_item_id,
				'mediaItem' => [
					'id'         => $this->media_item_id,
					'databaseId' => $this->attachment_id,
				],
			],
		];

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $expected, $actual['data'] );

		/**
		 * Try to delete again but we should have errors, because there's nothing to be deleted
		 */
		$actual = $this->deleteMediaItemMutation();
		$this->assertArrayHasKey( 'errors', $actual );

		// test global Id
		$deleted_media_item                    = $this->factory()->attachment->create();
		$this->delete_variables['input']['id'] = \GraphQLRelay\Relay::toGlobalId( 'post', $deleted_media_item );

		$actual = $this->deleteMediaItemMutation();
		$this->assertArrayNotHasKey( 'error', $actual );
		$this->assertEquals( $deleted_media_item, $actual['data']['deleteMediaItem']['mediaItem']['databaseId'] );

		$this->delete_variables['input']['id'] = $this->media_item_id;
	}

	public function testUpdateMediaItemOwnedByUserUpdatingIt() {

		$media_item_1 = $this->factory()->attachment->create(
			[
				'post_mime_type' => 'image/gif',
				'post_author'    => $this->author,
			]
		);

		wp_set_current_user( $this->author );

		$this->update_variables = [
			'input' => [
				'id'    => \GraphQLRelay\Relay::toGlobalId( 'post', $media_item_1 ),
				'title' => 'Test update title...',
			],
		];

		$actual = $this->updateMediaItemMutation();

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	/**
	 * @throws \Exception
	 */
	public function testCannotInputPharAsFilePath() {

		$query = '
		mutation CreateMediaItem( $input: CreateMediaItemInput! ) {
			createMediaItem(input: $input) {
				mediaItem {
					link
					sourceUrl
					uri
				}
			}
		}
		';

		$variables = [
			'input' => [
				'filePath' => 'php://filter/resource=phar://phar.jpeg',
			],
		];

		// set the user as admin, as they have privileges to create media items
		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);

		codecept_debug( $actual );

		$expected = [
			$this->expectedErrorPath( 'createMediaItem' ),
			$this->expectedErrorMessage( 'Invalid filePath', self::MESSAGE_STARTS_WITH ),
			$this->expectedField( 'createMediaItem', self::IS_NULL ),
		];

		$this->assertQueryError( $actual, $expected );
	}

	public function testCannotInputLocalhostAsFilePath() {

		$query = '
		mutation CreateMediaItem( $input: CreateMediaItemInput! ) {
			createMediaItem(input: $input) {
				mediaItem {
					link
					sourceUrl
					uri
				}
			}
		}
		';

		$variables = [
			'input' => [
				'filePath' => 'http://127.0.0.1:8000/?t=img.png',
			],
		];

		// set the user as admin, as they have privileges to create media items
		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);

		codecept_debug( $actual );

		$expected = [
			$this->expectedErrorPath( 'createMediaItem' ),
			$this->expectedErrorMessage( 'Invalid filePath', self::MESSAGE_STARTS_WITH ),
			$this->expectedField( 'createMediaItem', self::IS_NULL ),
		];

		$this->assertQueryError( $actual, $expected );
	}
}
