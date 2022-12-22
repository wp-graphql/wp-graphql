<?php

class MediaItemQueriesTest  extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
		$this->subscriber       = $this->factory()->user->create( [
			'role' => 'subscriber',
		] );

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
		$post_id = $this->createPostObject( [
			'post_type' => 'post',
		] );

		/**
		 * Create an attachment with a post set as it's parent
		 */
		$image_description = 'some description';
		$attachment_id     = $this->createPostObject( [
			'post_type'    => 'attachment',
			'post_parent'  => $post_id,
			'post_content' => $image_description,
		] );

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

		$filename      = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/images/test.png' );
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
		
		$png_filename      = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/images/test.png' );
		$png_attachment_id = $this->factory()->attachment->create_upload_object( $png_filename );

		$pdf_filename      = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/media/test.pdf' );
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
	 * @throws Exception
	 */
	public function testQueryMediaItemBySourceUrl() {

		$filename          = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/images/test.png' );
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

		//force the protocols to match if needed
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

		$actual = $this->graphql([
			'query'     => $query_by_source_url,
			'variables' => [
				'id' => $source_url,
			],
		]);

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
		$filename      = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/images/test-medium.png' );
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
		$filename      = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/images/test.png' );
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
			'exclude' => 'THUMBNAIL', // phpcs:ignore
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$actual_sizes = array_column( $actual['data']['mediaItem']['mediaDetails']['sizes'], 'name' );

		$this->assertArrayNotHasKey( 'thumbnail', $actual_sizes );

		// Ensure exclude overrides include.
		$variables = [
			'id'      => $attachment_global_id,
			'include' => [ 'THUMBNAIL', 'MEDIUM' ],
			'exclude' => 'MEDIUM', // phpcs:ignore
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$actual_sizes = $actual['data']['mediaItem']['mediaDetails']['sizes'];

		$this->assertCount( 1, $actual_sizes );
		$this->assertEquals( 'thumbnail', $actual_sizes[0]['name'] );
	}

}
