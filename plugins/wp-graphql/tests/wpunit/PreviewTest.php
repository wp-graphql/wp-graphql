<?php

class PreviewTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $post;
	public $preview;
	public $editor;
	public $category;
	public $featured_image;
	public $admin;

	public function setUp(): void {

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->editor = $this->factory()->user->create(
			[
				'role' => 'editor',
			]
		);

		$this->category = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'cat test' . uniqid(),
			]
		);

		$this->post = $this->factory()->post->create(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Published Post For PreviewTest',
				'post_content' => 'Published Content',
				'post_author'  => $this->admin,
			]
		);

		wp_set_object_terms( $this->post, $this->category, 'category', false );

		$filename             = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$this->featured_image = $this->factory()->attachment->create_upload_object( $filename );
		update_post_meta( $this->post, '_thumbnail_id', $this->featured_image );

		$this->preview = $this->factory()->post->create(
			[
				'post_status'  => 'inherit',
				'post_title'   => 'Preview Post for PreviewTest',
				'post_content' => 'Preview Content',
				'post_type'    => 'revision',
				'post_parent'  => $this->post,
				'post_author'  => $this->editor,
				'post_date'    => date( 'Y-m-d H:i:s', strtotime( 'now' ) ),
			]
		);

		WPGraphQL::clear_schema();
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
		wp_delete_post( $this->post, true );
		wp_delete_post( $this->preview, true );
		wp_delete_attachment( $this->featured_image, true );
		wp_delete_user( $this->admin );
		wp_delete_user( $this->editor );
		wp_delete_term( $this->category, 'category' );
		WPGraphQL::clear_schema();
	}

	public function get_query() {

		return '
		query GetPostAndPreview( $id: ID! $idType: PostIdType ) {
			post( id: $id idType: $idType ) {
				...PostFields
				preview {
					node {
						...PostFields
					}
				}
			}
			preview:post( id: $id idType: DATABASE_ID asPreview: true ) {
				...PostFields
			}
		}
		fragment PostFields on Post {
			__typename
			id
			title
			content
			author {
				node {
					databaseId
				}
			}
			categories {
				nodes {
					databaseId
				}
			}
			tags {
				nodes {
					databaseId
				}
			}
			featuredImage {
				node {
					databaseId
				}
			}
		}
		';
	}


	public function testPreviewReturnsNullForPublicRequest() {
		$the_post = get_post( $this->post );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::IS_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::IS_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::IS_NULL ),
			]
		);
	}

	public function testReturnsPreviewNodeForAdminRequest() {
		$the_post = get_post( $this->post );
		wp_set_current_user( $this->admin );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		add_filter(
			'wp_revisions_to_keep',
			static function () {
				return 0;
			}
		);

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		add_filter(
			'wp_revisions_to_keep',
			static function ( $default ) {
				return $default;
			}
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);
	}

	public function testGetPostMetaWithNullAsSingleDoesNotBreakPreview() {
		$the_post = get_post( $this->post );
		wp_set_current_user( $this->admin );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		add_filter(
			'wp_revisions_to_keep',
			static function () {
				return 0;
			}
		);

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		// Tests #1864
		// Getting the post meta with a null key should not fail requests.
		// Previously this would cause errors
		get_post_meta( $this->post, null, null );

		add_filter(
			'wp_revisions_to_keep',
			static function ( $default ) {
				return $default;
			}
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);
	}

	public function testGetPostMetaWithNullMetaKeyDoesNotBreakPreviews() {
		$the_post = get_post( $this->post );
		wp_set_current_user( $this->admin );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		add_filter(
			'wp_revisions_to_keep',
			static function () {
				return 0;
			}
		);

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		// Tests #1864
		// Getting the post meta with a null key should not fail requests.
		// Previously this would cause errors
		get_post_meta( $this->post, null, true );

		add_filter(
			'wp_revisions_to_keep',
			static function ( $default ) {
				return $default;
			}
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
			]
		);
	}

	public function testPreviewAuthorMatchesPublishedAuthor() {
		$the_post = get_post( $this->post );
		wp_set_current_user( $this->admin );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedField( 'post.preview.node.author.node.databaseId', $this->admin ),
				$this->expectedField( 'post.author.node.databaseId', $this->admin ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedField( 'post.preview.node.author.node.databaseId', $this->admin ),
				$this->expectedField( 'post.author.node.databaseId', $this->admin ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedField( 'post.preview.node.author.node.databaseId', $this->admin ),
				$this->expectedField( 'post.author.node.databaseId', $this->admin ),
			]
		);
	}

	public function testPreviewTermsMatchPublishedTerms() {
		$the_post = get_post( $this->post );
		wp_set_current_user( $this->admin );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedNode(
					'post.preview.node.categories.nodes',
					[
						$this->expectedField( 'databaseId', $this->category ),
					]
				),
				$this->expectedNode(
					'post.categories.nodes',
					[
						$this->expectedField( 'databaseId', $this->category ),
					]
				),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedNode(
					'post.preview.node.categories.nodes',
					[
						$this->expectedField( 'databaseId', $this->category ),
					]
				),
				$this->expectedNode(
					'post.categories.nodes',
					[
						$this->expectedField( 'databaseId', $this->category ),
					]
				),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedNode(
					'post.preview.node.categories.nodes',
					[
						$this->expectedField( 'databaseId', $this->category ),
					]
				),
				$this->expectedNode(
					'post.categories.nodes',
					[
						$this->expectedField( 'databaseId', $this->category ),
					]
				),
			]
		);
	}

	public function testPreviewFeaturedImageMatchesPublishedFeaturedImage() {
		$the_post = get_post( $this->post );
		wp_set_current_user( $this->admin );

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [
					'id'     => $the_post->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedNode(
					'post.preview.node.featuredImage.node',
					[
						$this->expectedField( 'databaseId', $this->featured_image ),
					]
				),
				$this->expectedNode(
					'post.featuredImage.node',
					[
						$this->expectedField( 'databaseId', $this->featured_image ),
					]
				),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedNode(
					'post.preview.node.featuredImage.node',
					[
						$this->expectedField( 'databaseId', $this->featured_image ),
					]
				),
				$this->expectedNode(
					'post.featuredImage.node',
					[
						$this->expectedField( 'databaseId', $this->featured_image ),
					]
				),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview', self::NOT_NULL ),
				$this->expectedNode(
					'post.preview.node.featuredImage.node',
					[
						$this->expectedField( 'databaseId', $this->featured_image ),
					]
				),
				$this->expectedNode(
					'post.featuredImage.node',
					[
						$this->expectedField( 'databaseId', $this->featured_image ),
					]
				),
			]
		);
	}

	public function testMetaOnPreview() {

		WPGraphQL::clear_schema();
		$meta_key   = 'metaKey';
		$meta_value = 'metaValue...';
		update_post_meta( $this->post, $meta_key, $meta_value );

		register_graphql_field(
			'Post',
			$meta_key,
			[
				'type'    => 'String',
				'resolve' => static function ( $post ) use ( $meta_key ) {
					return get_post_meta( $post->ID, $meta_key, true );
				},
			]
		);

		wp_set_current_user( $this->admin );

		$this->assertSame( $meta_value, get_post_meta( $this->post, $meta_key, true ) );
		$this->assertEmpty( get_post_meta( $this->preview, $meta_key, true ) );

		$query = '
		query GET_POST( $id:ID! $idType: PostIdType ) {
		 post(id:$id idType: $idType ) {
			 databaseId
			 metaKey
			 title
			 content
			 author {
				 node {
					 id
				 }
			 }
			 preview {
				 node {
					 databaseId
					 metaKey
					 title
					 content
					author {
						 node {
							 id
						 }
					 }
				 }
			 }
		 }
		}
		';

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => get_post( $this->post )->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.metaKey', $meta_value ),
				$this->expectedField( 'post.preview.node.metaKey', $meta_value ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.metaKey', $meta_value ),
				$this->expectedField( 'post.preview.node.metaKey', $meta_value ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.metaKey', $meta_value ),
				$this->expectedField( 'post.preview.node.metaKey', $meta_value ),
			]
		);

		$this->assertSame( $meta_value, get_post_meta( $this->post, $meta_key, true ) );
		$this->assertEmpty( get_post_meta( $this->preview, $meta_key, true ) );

		WPGraphQL::clear_schema();
	}

	/**
	 * In this test we want to test that meta for revisions is by default resolved by getting the meta
	 * from the parent node, but using the "graphql_resolve_revision_meta_from_parent" can override that.
	 *
	 * For example, plugins such as ACF that revise meta can use this filter to tell WPGraphQL
	 * to resolve meta from the revision instead of the revision parent.
	 *
	 * This tests that the resolving only affects WPGraphQL requests and not get_post_meta requests
	 * before/after GraphQL resolution.
	 *
	 * @throws \Exception
	 */
	public function testRevisedMetaOnPreview() {

		WPGraphQL::clear_schema();
		$published_meta_key   = 'publishedMetaKey';
		$published_meta_value = 'published metaValue...';

		// Store meta on the published post
		update_post_meta( $this->post, $published_meta_key, $published_meta_value );
		codecept_debug( get_post_meta( $this->post, $published_meta_key, $published_meta_value ) );

		// Register field for the published meta
		register_graphql_field(
			'Post',
			$published_meta_key,
			[
				'type'    => 'String',
				'resolve' => static function ( $post ) use ( $published_meta_key ) {
					return get_post_meta( $post->ID, $published_meta_key, true );
				},
			]
		);

		// Store meta on the preview post
		$revised_meta_key   = 'revisedMetaKey';
		$revised_meta_value = 'revised metaValue...';
		codecept_debug( add_metadata( 'post', $this->preview, $revised_meta_key, $revised_meta_value ) );
		codecept_debug( get_post_meta( $this->preview, $revised_meta_key, true ) );

		// Register field for the revised meta
		register_graphql_field(
			'Post',
			$revised_meta_key,
			[
				'type'    => 'String',
				'resolve' => static function ( $post ) use ( $revised_meta_key ) {
					return get_post_meta( $post->ID, $revised_meta_key, true );
				},
			]
		);

		// Tell the resolver to resolve using the revision ID instead of the
		// Parent ID for the revisedMetaKey. This means that for the meta_key "revisedMetaKey"
		// WPGraphQL will look for the meta value on the revision post's meta instead of
		// looking for it in the parent's meta, which is default WPGraphQL behavior.
		add_filter(
			'graphql_resolve_revision_meta_from_parent',
			static function ( $filter_revision_meta, $object_id, $meta_key, $single ) {
				if ( $meta_key === 'revisedMetaKey' ) {
					return false;
				}
				return $filter_revision_meta;
			},
			10,
			4
		);

		wp_set_current_user( $this->admin );

		codecept_debug( get_post_meta( $this->preview, $published_meta_key, true ) );
		codecept_debug( get_post_meta( $this->preview, $revised_meta_key, true ) );

		$this->assertSame( $published_meta_value, get_post_meta( $this->post, $published_meta_key, true ) );
		$this->assertSame( $revised_meta_value, get_post_meta( $this->preview, $revised_meta_key, true ) );

		$query = '
		query GET_POST( $id:ID! $idType: PostIdType ) {
		 post(id:$id idType: $idType) {
			 databaseId
			 revisedMetaKey
			 publishedMetaKey
			 title
			 content
			 author {
				 node {
					 id
				 }
			 }
			 preview {
				 node {
					 databaseId
					 publishedMetaKey
					 revisedMetaKey
					 title
					 content
					 author {
						 node {
							 id
						 }
					 }
				 }
			 }
		 }
		}
		';

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => get_post( $this->post )->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'post.preview.node.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'post.preview.node.revisedMetaKey', $revised_meta_value ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'post.preview.node.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'post.preview.node.revisedMetaKey', $revised_meta_value ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'post.preview.node.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'post.preview.node.revisedMetaKey', $revised_meta_value ),
			]
		);

		$this->assertSame( $published_meta_value, get_post_meta( $this->post, $published_meta_key, true ) );
		$this->assertEmpty( get_post_meta( $this->preview, $published_meta_key, true ) );

		$this->assertSame( $revised_meta_value, get_post_meta( $this->preview, $revised_meta_key, true ) );

		WPGraphQL::clear_schema();
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1615#issuecomment-741817101
	 */
	public function testMultipleMetaFieldsResolveOnPreviewNodes() {

		WPGraphQL::clear_schema();
		$published_meta_key   = 'publishedMetaKey';
		$published_meta_value = 'published metaValue...';

		// Store meta on the published post
		update_post_meta( $this->post, $published_meta_key, $published_meta_value );

		// Register field for the published meta
		register_graphql_field(
			'Post',
			$published_meta_key,
			[
				'type'    => 'String',
				'resolve' => static function ( $post ) use ( $published_meta_key ) {
					return get_post_meta( $post->ID, $published_meta_key, true );
				},
			]
		);

		wp_set_current_user( $this->admin );

		// Asking for the meta of a revision directly using the get_post_meta function should
		// get the meta from the revision ID, which should be empty since we didn't set any
		// value
		$this->assertEmpty( get_post_meta( $this->preview, $published_meta_key, true ) );

		$query = '
			query GET_POST( $id:ID! $idType: PostIdType ) {
			 post(id:$id idType: $idType) {
				 databaseId
				 enclosure
				 publishedMetaKey
				 title
				 content
				 preview {
					 node {
						 databaseId
						 enclosure
						 publishedMetaKey
						 title
						 content
					 }
				 }
			 }
			 preview:post(id:$id idType: $idType asPreview:true) {
				 publishedMetaKey
			 }
			}
		';

		$actual_by_database_id = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => $this->post,
					'idType' => 'DATABASE_ID',
				],
			]
		);

		$actual_by_uri = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => get_permalink( $this->post ),
					'idType' => 'URI',
				],
			]
		);

		$actual_by_slug = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id'     => get_post( $this->post )->post_name,
					'idType' => 'SLUG',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_by_database_id,
			[
				$this->expectedField( 'post.preview.node.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'preview.publishedMetaKey', $published_meta_value ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_uri,
			[
				$this->expectedField( 'post.preview.node.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'preview.publishedMetaKey', $published_meta_value ),
			]
		);

		self::assertQuerySuccessful(
			$actual_by_slug,
			[
				$this->expectedField( 'post.preview.node.publishedMetaKey', $published_meta_value ),
				$this->expectedField( 'preview.publishedMetaKey', $published_meta_value ),
			]
		);

		// Asking for the meta of a revision directly using the get_post_meta function should
		// get the meta from the revision ID, which should be empty since we didn't set any
		// value
		$this->assertEmpty( get_post_meta( $this->preview, $published_meta_key, true ) );
	}

	/**
	 * @see https://github.com/wp-graphql/wp-graphql/pull/3422
	 */
	public function testPreviewMetaWithArrayValueAndSingleTrue() {
		WPGraphQL::clear_schema();

		$meta_key   = 'array_meta_key';
		$meta_value = [ 'first_value', 'second_value', 'third_value' ];
		update_post_meta( $this->post, $meta_key, $meta_value );

		register_graphql_field(
			'Post',
			'arrayMetaField',
			[
				'type'        => [ 'list_of' => 'String' ],
				'description' => 'Test meta field with array value',
				'resolve'     => static function ( \WPGraphQL\Model\Post $post ) use ( $meta_key ) {
					$value = get_post_meta( $post->ID, $meta_key, true );
					// If we don't wrap in array the query will fail.
					return is_array( $value ) ? $value : [ $value ];
				},
			]
		);

		wp_set_current_user( $this->admin );

		$query = '
		query GetPostWithMeta( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				databaseId
				arrayMetaField
				preview {
					node {
						databaseId
						arrayMetaField
					}
				}
			}
		}';

		// Test with old behavior by removing current method and adding a filter with old method.
		remove_filter( 'get_post_metadata', [ \WPGraphQL\Utils\Preview::class, 'filter_post_meta_for_previews' ], 10 );

		$fn_old_callback = static function ( $default_value, $object_id, $meta_key_param, $single ) {
			if ( ! is_graphql_request() ) {
				return $default_value;
			}

			$post = get_post( $object_id );
			if ( ! $post instanceof \WP_Post || 'revision' !== $post->post_type ) {
				return $default_value;
			}

			$parent = get_post( $post->post_parent );
			if ( ! isset( $parent->ID ) || ! absint( $parent->ID ) ) {
				return $default_value;
			}

			// Old behavior: returns value directly, causing WP core to extract first element.
			return get_post_meta( $parent->ID, $meta_key_param, (bool) $single );
		};

		add_filter(
			'get_post_metadata',
			$fn_old_callback,
			10,
			4
		);

		$actual_with_bug = $this->graphql(
			[
				'query'     => $query,
				'variables' => [ 'id' => $this->post ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual_with_bug );
		$this->assertEquals(
			[ 'first_value' ],
			$actual_with_bug['data']['post']['preview']['node']['arrayMetaField'],
			'Without the fix, array meta returns only first element when single=true'
		);

		// Remove old behaviour and test new behaviour.
		remove_filter( 'get_post_metadata', $fn_old_callback );
		add_filter(
			'get_post_metadata',
			[ \WPGraphQL\Utils\Preview::class, 'filter_post_meta_for_previews' ],
			10,
			4
		);

		$actual_with_fix = $this->graphql(
			[
				'query'     => $query,
				'variables' => [ 'id' => $this->post ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual_with_fix );
		$this->assertEquals(
			$meta_value,
			$actual_with_fix['data']['post']['preview']['node']['arrayMetaField'],
			'With the fix, array meta returns complete array even when single=true'
		);

		WPGraphQL::clear_schema();
	}
}
