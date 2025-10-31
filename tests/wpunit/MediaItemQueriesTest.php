<?php

class MediaItemQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->subscriber       = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Post for MediaItemQueriesTest',
			'post_type'    => 'post',
			'post_date'    => $this->current_date,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 *
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;
	}

	/**
	 * Data provider for testMediaItemQuery.
	 */
	public function provideImageMeta() {
		return [
			[
				[],
			],
			[
				[
					'caption' => '',
				],
			],
		];
	}

	/**
	 * This tests creating a single post with data and retrieving said post via a GraphQL query
	 *
	 * @dataProvider provideImageMeta
	 * @param array $image_meta Image meta to merge into defaults.
	 * @since 0.0.5
	 */
	public function testMediaItemQuery( $image_meta = [] ) {

		/**
		 * Create a post to set as the attachment's parent
		 */
		$post_id = $this->createPostObject(
			[
				'post_type' => 'post',
			]
		);

		/**
		 * Create an attachment with a post set as it's parent
		 */
		$image_description = 'some description';
		$attachment_id     = $this->createPostObject(
			[
				'post_type'    => 'attachment',
				'post_parent'  => $post_id,
				'post_content' => $image_description,
			]
		);

		$default_image_meta = [
			'aperture'          => 0,
			'credit'            => 'some photographer',
			'camera'            => 'some camera',
			'caption'           => 'some caption',
			'created_timestamp' => strtotime( $this->current_date ),
			'copyright'         => 'Copyright WPGraphQL',
			'focal_length'      => 0,
			'iso'               => 0,
			'shutter_speed'     => 0,
			'title'             => 'some title',
			'orientation'       => 'some orientation',
			'keywords'          => [
				'keyword1',
				'keyword2',
			],
		];

		$meta_data = [
			'width'      => 300,
			'height'     => 300,
			'file'       => 'example.jpg',
			'sizes'      => [
				'thumbnail' => [
					'file'       => 'example-thumbnail.jpg',
					'width'      => 150,
					'height'     => 150,
					'mime-type'  => 'image/jpeg',
					'source_url' => 'example-thumbnail.jpg',
				],
				'full'      => [
					'file'       => 'example-full.jpg',
					'width'      => 1500,
					'height'     => 1500,
					'mime-type'  => 'image/jpeg',
					'source_url' => 'example-full.jpg',
				],
			],
			'image_meta' => array_merge( $default_image_meta, $image_meta ),
		];

		update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta_data );

		// Create the global ID based on the post_type and the created $id
		$attachment_global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $attachment_id );
		$post_global_id       = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		$query = '
		query testMediaItemQuery( $id:ID! ) {
			mediaItem(id: $id) {
				altText
				author{
					node {
						id
					}
				}
				caption
				commentCount
				commentStatus
				comments{
					edges{
						node{
							id
						}
					}
				}
				date
				dateGmt
				description
				desiredSlug
				lastEditedBy{
					node {
						databaseId
					}
				}
				editingLockedBy{
					lockTimestamp
				}
				enclosure
				guid
				id
				link
				mediaDetails{
					file
					height
					meta{
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
						keywords
					}
					sizes {
						name
						file
						width
						height
						mimeType
						sourceUrl
					}
					width
				}
				mediaItemId
				mediaType
				mimeType
				modified
				modifiedGmt
				parent{
					node {
						...on Post{
						id
					}
					}
				}
				slug
				sourceUrl
				status
				title
				srcSet
			}
		}';

		$variables = [
			'id' => $attachment_global_id,
		];

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$mediaItem = $actual['data']['mediaItem'];

		$this->assertNotEmpty( $mediaItem );

		$this->assertTrue( ( null === $mediaItem['altText'] || is_string( $mediaItem['altText'] ) ) );
		$this->assertTrue( ( null === $mediaItem['author'] || is_string( $mediaItem['author']['node']['id'] ) ) );
		$this->assertTrue( ( null === $mediaItem['caption'] || is_string( $mediaItem['caption'] ) ) );
		$this->assertTrue( ( null === $mediaItem['commentCount'] || is_int( $mediaItem['commentCount'] ) ) );
		$this->assertTrue( ( null === $mediaItem['commentStatus'] || is_string( $mediaItem['commentStatus'] ) ) );
		$this->assertTrue( ( empty( $mediaItem['comments']['edges'] ) || is_string( $mediaItem['comments']['edges'] ) ) );
		$this->assertTrue( ( null === $mediaItem['date'] || is_string( $mediaItem['date'] ) ) );
		$this->assertTrue( ( null === $mediaItem['dateGmt'] || is_string( $mediaItem['dateGmt'] ) ) );
		$this->assertTrue( ( null === $mediaItem['description'] || is_string( $mediaItem['description'] ) ) );
		$this->assertTrue( ( null === $mediaItem['desiredSlug'] || is_string( $mediaItem['desiredSlug'] ) ) );
		$this->assertTrue( ( empty( $mediaItem['editLast'] ) || is_integer( $mediaItem['editLast']['userId'] ) ) );
		$this->assertTrue( ( empty( $mediaItem['editLock'] ) || is_string( $mediaItem['editLock']['editTime'] ) ) );
		$this->assertTrue( ( null === $mediaItem['enclosure'] || is_string( $mediaItem['enclosure'] ) ) );
		$this->assertTrue( ( null === $mediaItem['guid'] || is_string( $mediaItem['guid'] ) ) );
		$this->assertEquals( $attachment_global_id, $mediaItem['id'] );
		$this->assertEquals( $attachment_id, $mediaItem['mediaItemId'] );
		$this->assertTrue( ( null === $mediaItem['mediaType'] || is_string( $mediaItem['mediaType'] ) ) );
		$this->assertTrue( ( null === $mediaItem['mimeType'] || is_string( $mediaItem['mimeType'] ) ) );
		$this->assertTrue( ( null === $mediaItem['modified'] || is_string( $mediaItem['modified'] ) ) );
		$this->assertTrue( ( null === $mediaItem['modifiedGmt'] || is_string( $mediaItem['modifiedGmt'] ) ) );
		$this->assertTrue( ( null === $mediaItem['slug'] || is_string( $mediaItem['slug'] ) ) );
		$this->assertTrue( ( null === $mediaItem['sourceUrl'] || is_string( $mediaItem['sourceUrl'] ) ) );
		$this->assertTrue( ( null === $mediaItem['status'] || is_string( $mediaItem['status'] ) ) );
		$this->assertTrue( ( null === $mediaItem['title'] || is_string( $mediaItem['title'] ) ) );

		$this->assertStringContainsString( '/wp-content/uploads/example-full.jpg 1500w', $mediaItem['srcSet'] );
		$this->assertStringContainsString( '/wp-content/uploads/example-thumbnail.jpg 150w', $mediaItem['srcSet'] );
		$this->assertStringContainsString( '/wp-content/uploads/example.jpg 300w', $mediaItem['srcSet'] );

		$this->assertEquals(
			[
				'id' => $post_global_id,
			],
			$mediaItem['parent']['node']
		);

		$this->assertNotEmpty( $mediaItem['description'] );
		$this->assertEquals( apply_filters( 'the_content', $image_description ), $mediaItem['description'] );

		$this->assertNotEmpty( $mediaItem['mediaDetails'] );
		$mediaDetails = $mediaItem['mediaDetails'];
		$this->assertEquals( $meta_data['file'], $mediaDetails['file'] );
		$this->assertEquals( $meta_data['height'], $mediaDetails['height'] );
		$this->assertEquals( $meta_data['width'], $mediaDetails['width'] );

		$this->assertNotEmpty( $mediaDetails['meta'] );
		$meta = $mediaDetails['meta'];

		$this->assertEquals( $meta_data['image_meta']['aperture'], $meta['aperture'] );
		$this->assertEquals( $meta_data['image_meta']['credit'], $meta['credit'] );
		$this->assertEquals( $meta_data['image_meta']['camera'], $meta['camera'] );
		$this->assertEquals( $meta_data['image_meta']['caption'], $meta['caption'] );
		$this->assertEquals( $meta_data['image_meta']['created_timestamp'], $meta['createdTimestamp'] );
		$this->assertEquals( $meta_data['image_meta']['copyright'], $meta['copyright'] );
		$this->assertEquals( $meta_data['image_meta']['focal_length'], $meta['focalLength'] );
		$this->assertEquals( $meta_data['image_meta']['iso'], $meta['iso'] );
		$this->assertEquals( $meta_data['image_meta']['shutter_speed'], $meta['shutterSpeed'] );
		$this->assertEquals( $meta_data['image_meta']['title'], $meta['title'] );
		$this->assertEquals( $meta_data['image_meta']['orientation'], $meta['orientation'] );

		$this->assertNotEmpty( $meta_data['image_meta']['keywords'] );
		$keywords = $meta_data['image_meta']['keywords'];
		$this->assertEquals( 'keyword1', $keywords[0] );
		$this->assertEquals( 'keyword2', $keywords[1] );

		$this->assertNotEmpty( $meta_data['sizes'] );
		$sizes = $mediaDetails['sizes'];
		$this->assertEquals( 'thumbnail', $sizes[0]['name'] );
		$this->assertEquals( 'example-thumbnail.jpg', $sizes[0]['file'] );
		$this->assertEquals( 150, $sizes[0]['height'] );
		$this->assertEquals( 150, $sizes[0]['width'] );
		$this->assertEquals( 'image/jpeg', $sizes[0]['mimeType'] );
		$this->assertEquals( wp_get_attachment_image_src( $attachment_id, 'thumbnail' )[0], $sizes[0]['sourceUrl'] );
	}

	/**
	 * This tests creates a single attachment and retrieves said post URL via a GraphQL query
	 *
	 * @since 0.3.6
	 */
	public function testMediaItemImageUrl() {

		$filename      = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		$expected_filesize = filesize( $filename );

		$query = '
			query GET_MEDIA_ITEM( $id: Int! ) {
				mediaItemBy(mediaItemId: $id) {
					mediaItemUrl
					fileSize
				}
			}
		';

		$variables = [
			'id' => $attachment_id,
		];

		$result = $this->graphql( compact( 'query', 'variables' ) );

		$expected = wp_get_attachment_url( $attachment_id );

		$this->assertEquals( $result['data']['mediaItemBy']['mediaItemUrl'], $expected );
		$this->assertEquals( $expected_filesize, $result['data']['mediaItemBy']['fileSize'] );
	}

	public function testQueryMediaItemsByMimeType() {

		$png_filename      = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$png_attachment_id = $this->factory()->attachment->create_upload_object( $png_filename );

		$pdf_filename      = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/media/test.pdf' );
		$pdf_attachment_id = $this->factory()->attachment->create_upload_object( $pdf_filename );

		$query = '
			query GET_MEDIA_ITEMS( $mimeType: MimeTypeEnum ) {
				mediaItems(where: {mimeType: $mimeType}) {
					nodes {
						databaseId
						mimeType
					}
				}
			}
		';

		// Test PNG
		$variables = [
			'mimeType' => 'IMAGE_PNG',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertCount( 1, $actual['data']['mediaItems']['nodes'] );

		$this->assertEquals( $png_attachment_id, $actual['data']['mediaItems']['nodes'][0]['databaseId'] );
		$this->assertEquals( 'image/png', $actual['data']['mediaItems']['nodes'][0]['mimeType'] );

		// Test PDF
		$variables = [
			'mimeType' => 'APPLICATION_PDF',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertCount( 1, $actual['data']['mediaItems']['nodes'] );

		$this->assertEquals( $pdf_attachment_id, $actual['data']['mediaItems']['nodes'][0]['databaseId'] );
		$this->assertEquals( 'application/pdf', $actual['data']['mediaItems']['nodes'][0]['mimeType'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryMediaItemBySourceUrl() {

		$filename          = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id     = $this->factory()->attachment->create_upload_object( $filename );
		$expected_filesize = filesize( $filename );

		$default_image_meta = [
			'aperture'          => 0,
			'credit'            => 'some photographer',
			'camera'            => 'some camera',
			'caption'           => 'some caption',
			'created_timestamp' => strtotime( $this->current_date ),
			'copyright'         => 'Copyright WPGraphQL',
			'focal_length'      => 0,
			'iso'               => 0,
			'shutter_speed'     => 0,
			'title'             => 'some title',
			'orientation'       => 'some orientation',
			'keywords'          => [
				'keyword1',
				'keyword2',
			],
		];

		$meta_data = [
			'width'      => 300,
			'height'     => 300,
			'file'       => 'example.jpg',
			'sizes'      => [
				'thumbnail' => [
					'file'       => 'example-thumbnail.jpg',
					'width'      => 150,
					'height'     => 150,
					'mime-type'  => 'image/jpeg',
					'source_url' => 'example-thumbnail.jpg',
				],
				'full'      => [
					'file'       => 'example-full.jpg',
					'width'      => 1500,
					'height'     => 1500,
					'mime-type'  => 'image/jpeg',
					'source_url' => 'example-full.jpg',
				],
			],
			'image_meta' => array_merge( $default_image_meta, [] ),
		];

		update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta_data );

		$query = '
			query GET_MEDIA_ITEM( $id: ID! ) {
				mediaItem(id: $id, idType: DATABASE_ID) {
					sourceUrl
					fileSize
				}
			}
		';

		$variables = [ 'id' => $attachment_id ];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected_filesize, $actual['data']['mediaItem']['fileSize'] );

		$source_url = $actual['data']['mediaItem']['sourceUrl'];

		/**
		 * Mock saving the _wp_attached_file to meta
		 *
		 * SEE: https://developer.wordpress.org/reference/functions/attachment_url_to_postid/#source
		 */
		$dir  = wp_get_upload_dir();
		$path = $source_url;

		$site_url   = wp_parse_url( $dir['url'] );
		$image_path = wp_parse_url( $path );

		// force the protocols to match if needed
		if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
			$path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
		}

		if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
			$path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
		}
		update_post_meta( $attachment_id, '_wp_attached_file', $path );

		codecept_debug( $source_url );

		$query_by_source_url = '
			query GetMediaItem($id:ID!) {
				mediaItem(
				id: $id,
				idType: SOURCE_URL
			) {
				__typename
				id
				sourceUrl
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query_by_source_url,
				'variables' => [
					'id' => $source_url,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $source_url, $actual['data']['mediaItem']['sourceUrl'] );
	}

	/**
	 * This tests creating a small size media item and retrieving bigger size image via a GraphQL query
	 *
	 * @since 1.2.5
	 */
	public function testMediaItemNotExistingSizeQuery() {

		/**
		 * Upload a medium size attachment
		 */
		$filename      = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test-medium.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$attachment_global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $attachment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		query testMediaItemNotExistingSizeQuery( $id:ID! ) {
			mediaItem(id: $id) {
				srcSet(size: LARGE)
				sizes(size: LARGE)
			}
		}';

		$variables = [
			'id' => $attachment_global_id,
		];

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$mediaItem = $actual['data']['mediaItem'];

		$this->assertNotEmpty( $mediaItem );

		$this->assertNotNull( $mediaItem['srcSet'] );
		$this->assertNotNull( $mediaItem['sizes'] );

		$img_atts = wp_get_attachment_image_src( $attachment_id, 'full' );
		$this->assertNotEmpty( $img_atts );

		$width = $img_atts[1];
		// compare with (max-width: 1024px) 100vw, 1024px
		$this->assertEquals( sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width ), $mediaItem['sizes'] );
	}

	/**
	 * This tests filtering the MediaDetails sizes.
	 *
	 * @since 1.2.5
	 */
	public function testMediaDetailsSizesWithArgs() {

		/**
		 * Upload a medium size attachment
		 */
		$filename      = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$attachment_global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $attachment_id );

		$query = '
		query testMediaDetailsSizesWithArgs( $id:ID!, $include: [MediaItemSizeEnum], $exclude: [MediaItemSizeEnum] ) {
			mediaItem(id: $id) {
				mediaDetails {
					sizes( include: $include, exclude: $exclude ) {
						name
						sourceUrl
					}
				}
			}
		}';

		// Get only the thumbnail size.
		$variables = [
			'id'      => $attachment_global_id,
			'include' => 'THUMBNAIL',
		];

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$actual_sizes = $actual['data']['mediaItem']['mediaDetails']['sizes'];

		$this->assertCount( 1, $actual_sizes );
		$this->assertEquals( 'thumbnail', $actual_sizes[0]['name'] );

		// Get all sizes except the thumbnail.
		$variables = [
			'id'      => $attachment_global_id,
			'exclude' => 'THUMBNAIL',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$actual_sizes = array_column( $actual['data']['mediaItem']['mediaDetails']['sizes'], 'name' );

		$this->assertArrayNotHasKey( 'thumbnail', $actual_sizes );

		// Ensure exclude overrides include.
		$variables = [
			'id'      => $attachment_global_id,
			'include' => [ 'THUMBNAIL', 'MEDIUM' ],
			'exclude' => 'MEDIUM',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$actual_sizes = $actual['data']['mediaItem']['mediaDetails']['sizes'];

		$this->assertCount( 1, $actual_sizes );
		$this->assertEquals( 'thumbnail', $actual_sizes[0]['name'] );
	}

	public function testSourceUrlSizes() {

		// upload large attachment that will be resized to medium and thumbnail
		$filename         = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test-2000x1000.png' );
		$attachment_id    = $this->factory()->attachment->create_upload_object( $filename );
		$attachment       = get_post( $attachment_id );
		$media_item_model = new \WPGraphQL\Model\Post( $attachment );

		$query = '
		{
		  mediaItems(first:1) {
		    nodes {
		      id
		      med: sourceUrl(size: MEDIUM)
		      large: sourceUrl(size: LARGE)
		      thumb: sourceUrl(size: THUMBNAIL)
		      fileMed: fileSize(size: MEDIUM)
		      fileLarge: fileSize(size: LARGE)
		      fileThumb: fileSize(size: THUMBNAIL)
		    }
		  }
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$mediaItems = $actual['data']['mediaItems']['nodes'];

		$this->assertCount( 1, $mediaItems );

		// get the attachment image sizes
		$expected_sizes = [
			'med'   => wp_get_attachment_image_src( $attachment_id, 'medium' )[0],
			'large' => wp_get_attachment_image_src( $attachment_id, 'large' )[0],
			'thumb' => wp_get_attachment_image_src( $attachment_id, 'thumbnail' )[0],
		];

		$expected_filesizes = [
			'med'   => $this->_getFilesize( $expected_sizes['med'], $media_item_model ),
			'large' => $this->_getFilesize( $expected_sizes['large'], $media_item_model ),
			'thumb' => $this->_getFilesize( $expected_sizes['thumb'], $media_item_model ),
		];

		$this->assertEquals( $expected_sizes['med'], $mediaItems[0]['med'] );
		$this->assertEquals( $expected_sizes['large'], $mediaItems[0]['large'] );
		$this->assertEquals( $expected_sizes['thumb'], $mediaItems[0]['thumb'] );
		$this->assertEquals( $expected_filesizes['med'], $mediaItems[0]['fileMed'] );
		$this->assertEquals( $expected_filesizes['large'], $mediaItems[0]['fileLarge'] );
		$this->assertEquals( $expected_filesizes['thumb'], $mediaItems[0]['fileThumb'] );

		wp_delete_attachment( $attachment_id, true );
	}

	public function _getFilesize( $source_url, $image_model ) {
		$path_parts    = pathinfo( $source_url );
		$original_file = get_attached_file( absint( $image_model->databaseId ) );
		$filesize_path = ! empty( $original_file ) ? path_join( dirname( $original_file ), $path_parts['basename'] ) : null;

		return ! empty( $filesize_path ) ? filesize( $filesize_path ) : null;
	}

	public function testGetSourceUrlBySize() {

		$filename         = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test-2000x1000.png' );
		$attachment_id    = $this->factory()->attachment->create_upload_object( $filename );
		$attachment       = get_post( $attachment_id );
		$media_item_model = new \WPGraphQL\Model\Post( $attachment );

		$expected_sizes = [
			'full'  => wp_get_attachment_image_src( $attachment_id, 'full' )[0],
			'med'   => wp_get_attachment_image_src( $attachment_id, 'medium' )[0],
			'large' => wp_get_attachment_image_src( $attachment_id, 'large' )[0],
			'thumb' => wp_get_attachment_image_src( $attachment_id, 'thumbnail' )[0],
		];

		$full  = $media_item_model->get_source_url_by_size();
		$large = $media_item_model->get_source_url_by_size( 'large' );
		$med   = $media_item_model->get_source_url_by_size( 'medium' );
		$thumb = $media_item_model->get_source_url_by_size( 'thumbnail' );

		codecept_debug(
			[
				'$expected_sizes' => $expected_sizes,
				'$full'           => $full,
				'$large'          => $large,
				'$med'            => $med,
				'$thumb'          => $thumb,
			]
		);

		$this->assertEquals( $expected_sizes['med'], $med );
		$this->assertEquals( $expected_sizes['large'], $large );
		$this->assertEquals( $expected_sizes['thumb'], $thumb );
		$this->assertEquals( $expected_sizes['full'], $full );

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test the filePath field returns the correct relative path for media items
	 */
	public function testMediaItemFilePath() {
		// Upload a test image that will generate different sizes
		$filename = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		$query = '
		query GetMediaItemFilePath($id: ID!) {
			mediaItem(id: $id, idType: DATABASE_ID) {
				databaseId
				# Original file path
				filePath
				# Size-specific paths
				thumbnailPath: filePath(size: THUMBNAIL)
				mediumPath: filePath(size: MEDIUM)
				largePath: filePath(size: LARGE)
				# FULL is not a valid size in the metadata, so we should use LARGE instead
				fullSizePath: filePath(size: LARGE)
				# Get metadata to verify paths
				mediaDetails {
					file
					sizes {
						name
						file
					}
				}
			}
		}
		';

		$variables = [
			'id' => $attachment_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		
		// Debug the errors if they exist
		if ( isset( $actual['errors'] ) ) {
			codecept_debug( $actual['errors'] );
		}
		
		$this->assertArrayNotHasKey( 'errors', $actual );
		
		// Get the expected relative path for original file
		$attachment_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->assertNotEmpty( $attachment_path, 'Attachment path should not be empty' );
		
		// Get upload directory info
		$upload_dir = wp_upload_dir();
		$relative_upload_path = wp_make_link_relative( $upload_dir['baseurl'] );
		
		// Test original file path
		$expected_path = path_join( $relative_upload_path, $attachment_path );
		$this->assertEquals( $expected_path, $actual['data']['mediaItem']['filePath'] );
		
		// Get the metadata to test size-specific paths
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertNotEmpty( $metadata['sizes'], 'Image sizes should be generated' );
		
		// Test thumbnail path
		if ( isset( $metadata['sizes']['thumbnail'] ) ) {
			$expected_thumbnail_path = path_join( $relative_upload_path, dirname( $metadata['file'] ) . '/' . $metadata['sizes']['thumbnail']['file'] );
			$this->assertEquals( $expected_thumbnail_path, $actual['data']['mediaItem']['thumbnailPath'] );
		}
		
		// Test medium path
		if ( isset( $metadata['sizes']['medium'] ) ) {
			$expected_medium_path = path_join( $relative_upload_path, dirname( $metadata['file'] ) . '/' . $metadata['sizes']['medium']['file'] );
			$this->assertEquals( $expected_medium_path, $actual['data']['mediaItem']['mediumPath'] );
		}
		
		// Test large path
		if ( isset( $metadata['sizes']['large'] ) ) {
			$expected_large_path = path_join( $relative_upload_path, dirname( $metadata['file'] ) . '/' . $metadata['sizes']['large']['file'] );
			$this->assertEquals( $expected_large_path, $actual['data']['mediaItem']['largePath'] );
		}
		
		// Test full size (should be same as large)
		if ( isset( $metadata['sizes']['large'] ) ) {
			$expected_full_path = path_join( $relative_upload_path, dirname( $metadata['file'] ) . '/' . $metadata['sizes']['large']['file'] );
			$this->assertEquals( $expected_full_path, $actual['data']['mediaItem']['fullSizePath'] );
		}

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test the file field returns the correct filename for media items
	 */
	public function testMediaItemFile() {
		// Upload a test image that will generate different sizes
		$filename = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		$query = '
		query GetMediaItemFile($id: ID!) {
			mediaItem(id: $id, idType: DATABASE_ID) {
				databaseId
				# Original file name
				file
				# Size-specific filenames
				thumbnailFile: file(size: THUMBNAIL)
				mediumFile: file(size: MEDIUM)
				largeFile: file(size: LARGE)
				# FULL maps to LARGE in the resolver
				fullSizeFile: file(size: LARGE)
				# Test MediaDetails file field
				mediaDetails {
					file
					sizes {
						name
						file
					}
				}
			}
		}
		';

		$variables = [
			'id' => $attachment_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		
		$this->assertArrayNotHasKey( 'errors', $actual );
		
		// Get the metadata to verify filenames
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertNotEmpty( $metadata['sizes'], 'Image sizes should be generated' );
		
		// Get the original filename
		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->assertNotEmpty( $attached_file, 'Attachment file should not be empty' );
		
		// Test original filename
		$this->assertEquals( basename( $attached_file ), $actual['data']['mediaItem']['file'] );
		
		// Test MediaDetails.file returns just the filename
		$this->assertEquals( 
			basename( $metadata['file'] ),
			$actual['data']['mediaItem']['mediaDetails']['file'],
			'MediaDetails.file should return just the filename without the path'
		);
		
		// Test thumbnail filename
		if ( isset( $metadata['sizes']['thumbnail'] ) ) {
			$this->assertEquals( 
				$metadata['sizes']['thumbnail']['file'],
				$actual['data']['mediaItem']['thumbnailFile']
			);
		}
		
		// Test medium filename
		if ( isset( $metadata['sizes']['medium'] ) ) {
			$this->assertEquals(
				$metadata['sizes']['medium']['file'],
				$actual['data']['mediaItem']['mediumFile']
			);
		}
		
		// Test large filename
		if ( isset( $metadata['sizes']['large'] ) ) {
			$this->assertEquals(
				$metadata['sizes']['large']['file'],
				$actual['data']['mediaItem']['largeFile']
			);
			
			// Test full size (should be same as large)
			$this->assertEquals(
				$metadata['sizes']['large']['file'],
				$actual['data']['mediaItem']['fullSizeFile']
			);
		}

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that MediaDetails.file returns just the filename without the path
	 */
	public function testMediaDetailsFile() {
		// Upload a test image
		$filename = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		$query = '
		query GetMediaDetailsFile($id: ID!) {
			mediaItem(id: $id, idType: DATABASE_ID) {
				mediaDetails {
					file
				}
			}
		}
		';

		$variables = [
			'id' => $attachment_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Get the metadata to verify filename
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertNotEmpty( $metadata['file'], 'Attachment metadata file should not be empty' );

		// Test that MediaDetails.file returns just the filename
		$this->assertEquals(
			basename( $metadata['file'] ),
			$actual['data']['mediaItem']['mediaDetails']['file'],
			'MediaDetails.file should return just the filename without the path'
		);

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that MediaDetails.filePath returns the correct relative path
	 */
	public function testMediaDetailsFilePath() {
		// Upload a test image
		$filename = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		$query = '
		query GetMediaDetailsFilePath($id: ID!) {
			mediaItem(id: $id, idType: DATABASE_ID) {
				mediaDetails {
					file
					filePath
				}
			}
		}
		';

		$variables = [
			'id' => $attachment_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		
		$this->assertArrayNotHasKey( 'errors', $actual );
		
		// Get the metadata to verify paths
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertNotEmpty( $metadata['file'], 'Attachment metadata file should not be empty' );
		
		// Test that MediaDetails.file returns just the filename
		$this->assertEquals( 
			basename( $metadata['file'] ),
			$actual['data']['mediaItem']['mediaDetails']['file'],
			'MediaDetails.file should return just the filename without the path'
		);
		
		// Test that MediaDetails.filePath returns the full relative path
		$upload_dir = wp_upload_dir();
		$relative_upload_path = wp_make_link_relative( $upload_dir['baseurl'] );
		$expected_path = path_join( $relative_upload_path, $metadata['file'] );
		
		$this->assertEquals(
			$expected_path,
			$actual['data']['mediaItem']['mediaDetails']['filePath'],
			'MediaDetails.filePath should return the full path relative to uploads directory'
		);

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that MediaItemSizeEnum has values even when intermediate image sizes are disabled.
	 * 
	 * This reproduces the bug on WordPress VIP where get_intermediate_image_sizes() returns
	 * an empty array, causing MediaItemSizeEnum to have zero values and breaking the schema.
	 * 
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3432
	 */
	public function testMediaItemSizeEnumWithNoIntermediateSizes() {
		// Filter get_intermediate_image_sizes to return empty array (simulating VIP)
		add_filter( 'intermediate_image_sizes', function() {
			return [];
		}, 999 );

		// Clear and rebuild the schema with the filtered function
		$this->clearSchema();

		// Introspection query to get MediaItemSizeEnum values
		$query = '
		query IntrospectMediaItemSizeEnum {
			__type(name: "MediaItemSizeEnum") {
				name
				kind
				enumValues {
					name
					description
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		// Assert no errors in the response
		$this->assertArrayNotHasKey( 'errors', $actual, 'Schema introspection should not return errors' );

		// Assert the type exists
		$this->assertNotNull( $actual['data']['__type'], 'MediaItemSizeEnum type should exist in the schema' );
		$this->assertEquals( 'MediaItemSizeEnum', $actual['data']['__type']['name'] );
		$this->assertEquals( 'ENUM', $actual['data']['__type']['kind'] );

		// Assert the enum has at least one value (GraphQL spec requirement)
		$this->assertNotEmpty( 
			$actual['data']['__type']['enumValues'], 
			'MediaItemSizeEnum must have at least one value. GraphQL spec requires enums to define one or more values.'
		);

		// Verify that standard sizes are available as fallbacks
		$enumValueNames = array_column( $actual['data']['__type']['enumValues'], 'name' );
		$this->assertContains( 'FULL', $enumValueNames, 'FULL size should always be available' );

		// Clean up the filter
		remove_all_filters( 'intermediate_image_sizes', 999 );
		$this->clearSchema();
	}
}
