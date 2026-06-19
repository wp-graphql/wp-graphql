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

	/**
	 * A `preview` envelope in the request `extensions` carrying a `thumbnailId` should
	 * override the featured image when previewing, mirroring how core reads the previewed
	 * featured image from the `_thumbnail_id` request parameter.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/2664
	 */
	public function testPreviewThumbnailIdExtensionOverridesFeaturedImage() {
		wp_set_current_user( $this->admin );

		// A different image than the published post's featured image ($this->featured_image),
		// representing the in-progress (previewed) featured image change.
		$filename  = WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png';
		$new_image = $this->factory()->attachment->create_upload_object( $filename );

		$query = '
		query Preview( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				databaseId
				featuredImageDatabaseId
				featuredImageId
				featuredImage {
					node {
						databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'      => $query,
				'variables'  => [ 'id' => $this->post ],
				'extensions' => [
					'preview' => [
						'id'          => $this->post,
						'thumbnailId' => $new_image,
					],
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				// Identity is preserved: the node is still the published post.
				$this->expectedField( 'post.databaseId', $this->post ),
				// The featured image is overlaid from the previewed thumbnailId.
				$this->expectedField( 'post.featuredImageDatabaseId', $new_image ),
				$this->expectedField( 'post.featuredImageId', \GraphQLRelay\Relay::toGlobalId( 'post', (string) $new_image ) ),
				$this->expectedField( 'post.featuredImage.node.databaseId', $new_image ),
			]
		);

		$this->assertNotEquals(
			$this->featured_image,
			$actual['data']['post']['featuredImageDatabaseId'],
			'The preview should reflect the previewed thumbnailId, not the published featured image'
		);

		wp_delete_attachment( $new_image, true );
	}

	/**
	 * The previewed `thumbnailId` must only be honored for a viewer who can edit the post
	 * being previewed. A viewer without edit caps should never have the featured image
	 * overridden by a client-supplied thumbnail id.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/2664
	 */
	public function testPreviewThumbnailIdExtensionIgnoredWithoutEditCapability() {
		$subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$filename  = WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png';
		$new_image = $this->factory()->attachment->create_upload_object( $filename );

		// Query the published post (not asPreview, since a subscriber can't access the
		// preview node anyway) and try to force the featured image via the extension.
		$query = '
		query Published( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				featuredImageDatabaseId
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'      => $query,
				'variables'  => [ 'id' => $this->post ],
				'extensions' => [
					'preview' => [
						'id'          => $this->post,
						'thumbnailId' => $new_image,
					],
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.featuredImageDatabaseId', $this->featured_image ),
			]
		);

		wp_delete_attachment( $new_image, true );
		wp_delete_user( $subscriber );
	}

	/**
	 * A valid `preview` envelope overlays the previewable fields (e.g. content) from the
	 * revision while preserving the node's published identity (databaseId is unchanged),
	 * for an authenticated user who can edit the post, without needing `asPreview`.
	 */
	public function testPreviewEnvelopeOverlaysContentAndPreservesIdentity() {
		wp_set_current_user( $this->admin );

		$query = '
		query Post( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				databaseId
				content
			}
		}
		';

		$variables = [ 'id' => $this->post ];

		// Without the envelope, the published content is returned.
		$published = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertStringContainsString( 'Published Content', $published['data']['post']['content'] );

		// With the envelope, the content overlays from the revision, but the identity
		// (databaseId) remains the published post's.
		$preview = $this->graphql(
			[
				'query'      => $query,
				'variables'  => $variables,
				'extensions' => [ 'preview' => [ 'id' => $this->post ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $preview );
		$this->assertEquals( $this->post, $preview['data']['post']['databaseId'], 'The node keeps its published databaseId (identity is preserved)' );
		$this->assertStringContainsString( 'Preview Content', $preview['data']['post']['content'], 'The content overlays from the revision' );
	}

	/**
	 * Meta keys that WordPress revisions (registered with `revisions_enabled`, e.g. core's
	 * `footnotes`) must resolve from the revision's own value in a preview, not from the
	 * parent. Previously the blanket parent-fallback overwrote the revision's value.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3260
	 */
	public function testRevisionedMetaResolvesFromRevisionInPreview() {
		WPGraphQL::clear_schema();

		// A meta key WordPress revisions (stored on the revision itself).
		register_post_meta(
			'post',
			'revisionedMetaKey',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'revisions_enabled' => true,
			]
		);

		register_graphql_field(
			'Post',
			'revisionedMetaKey',
			[
				'type'    => 'String',
				'resolve' => static function ( $post ) {
					return get_post_meta( $post->ID, 'revisionedMetaKey', true );
				},
			]
		);

		// Different values on the published post and on the revision.
		update_post_meta( $this->post, 'revisionedMetaKey', 'published value' );
		update_metadata( 'post', $this->preview, 'revisionedMetaKey', 'revised value' );

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => 'query( $id: ID! ) { post( id: $id, idType: DATABASE_ID, asPreview: true ) { revisionedMetaKey } }',
				'variables' => [ 'id' => $this->post ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'revised value', $actual['data']['post']['revisionedMetaKey'], '#3260: a revisioned meta key resolves from the revision, not the parent' );

		unregister_post_meta( 'post', 'revisionedMetaKey' );
		WPGraphQL::clear_schema();
	}

	/**
	 * The same revisioned-meta resolution applies when querying a revision directly through
	 * the `revisions` connection, not only through the preview flow. The revision node must
	 * resolve a revisioned meta key from its own value.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3260
	 */
	public function testRevisionedMetaResolvesFromRevisionInRevisionsConnection() {
		WPGraphQL::clear_schema();

		register_post_meta(
			'post',
			'revisionedMetaKey',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'revisions_enabled' => true,
			]
		);

		register_graphql_field(
			'Post',
			'revisionedMetaKey',
			[
				'type'    => 'String',
				'resolve' => static function ( $post ) {
					return get_post_meta( $post->ID, 'revisionedMetaKey', true );
				},
			]
		);

		update_post_meta( $this->post, 'revisionedMetaKey', 'published value' );
		update_metadata( 'post', $this->preview, 'revisionedMetaKey', 'revised value' );

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => 'query( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { revisions { nodes { databaseId revisionedMetaKey } } } }',
				'variables' => [ 'id' => $this->post ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$revision_node = null;
		foreach ( $actual['data']['post']['revisions']['nodes'] as $node ) {
			if ( (int) $node['databaseId'] === (int) $this->preview ) {
				$revision_node = $node;
			}
		}

		$this->assertNotNull( $revision_node, 'The revision should appear in the revisions connection' );
		$this->assertSame( 'revised value', $revision_node['revisionedMetaKey'], '#3260: a revisioned meta key resolves from the revision in the revisions connection too' );

		unregister_post_meta( 'post', 'revisionedMetaKey' );
		WPGraphQL::clear_schema();
	}

	/**
	 * With the preview extension, a previewed (non-hierarchical) post keeps its published
	 * databaseId, so a client can identify the published post directly while still showing
	 * the draft content. This is the capability requested in #2876, which the legacy
	 * `asPreview` swap could not provide because it returned the revision's id.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/2876
	 */
	public function testPreviewExtensionExposesPublishedIdForNonHierarchicalPost() {
		wp_set_current_user( $this->admin );

		// The legacy `asPreview: true` swap returns the REVISION's databaseId (the #2876 problem).
		$legacy = $this->graphql(
			[
				'query'     => 'query( $id: ID! ) { post( id: $id, idType: DATABASE_ID, asPreview: true ) { databaseId } }',
				'variables' => [ 'id' => $this->post ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $legacy );
		$this->assertNotEquals( $this->post, $legacy['data']['post']['databaseId'], 'Legacy asPreview returns the revision id, not the published post id' );

		// The extension preserves the published databaseId while overlaying the draft content.
		$extension = $this->graphql(
			[
				'query'      => 'query( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { databaseId content } }',
				'variables'  => [ 'id' => $this->post ],
				'extensions' => [ 'preview' => [ 'id' => $this->post ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $extension );
		$this->assertEquals( $this->post, $extension['data']['post']['databaseId'], '#2876: the published post id is exposed directly via databaseId' );
		$this->assertStringContainsString( 'Preview Content', $extension['data']['post']['content'], 'The draft content is still overlaid' );
	}

	/**
	 * When both the deprecated `asPreview: true` argument and a `preview` extension are
	 * provided, the extension wins (the node keeps its published identity and overlays),
	 * and the `asPreview` argument is ignored rather than swapping to the revision node.
	 */
	public function testExtensionWinsOverDeprecatedAsPreviewArg() {
		wp_set_current_user( $this->admin );

		$query = '
		query Post( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID, asPreview: true ) {
				databaseId
				content
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'      => $query,
				'variables'  => [ 'id' => $this->post ],
				'extensions' => [ 'preview' => [ 'id' => $this->post ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		// asPreview:true would have swapped to the revision id; the extension keeps identity.
		$this->assertEquals( $this->post, $actual['data']['post']['databaseId'], 'The extension preserves identity; the asPreview swap is ignored' );
		$this->assertStringContainsString( 'Preview Content', $actual['data']['post']['content'] );
	}

	/**
	 * The overlay also applies to a previewed post appearing inside a connection: only the
	 * targeted node overlays, and it keeps its published identity.
	 */
	public function testPreviewEnvelopeOverlaysContentForNodeInConnection() {
		wp_set_current_user( $this->admin );

		// A second published post that is NOT the preview target.
		$other = $this->factory()->post->create(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Other Published Post',
				'post_content' => 'Other Published Content',
				'post_author'  => $this->admin,
			]
		);

		$query = '
		query Posts {
			posts( first: 50 ) {
				nodes {
					databaseId
					content
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'      => $query,
				'extensions' => [ 'preview' => [ 'id' => $this->post ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$by_id = [];
		foreach ( $actual['data']['posts']['nodes'] as $node ) {
			$by_id[ $node['databaseId'] ] = $node['content'];
		}

		$this->assertArrayHasKey( $this->post, $by_id, 'The previewed post keeps its published identity in the connection' );
		$this->assertStringContainsString( 'Preview Content', $by_id[ $this->post ], 'The previewed node overlays its content' );
		$this->assertStringContainsString( 'Other Published Content', $by_id[ $other ], 'Non-targeted nodes are unaffected' );

		wp_delete_post( $other, true );
	}

	/**
	 * A preview envelope from an unauthenticated request must be ignored: the published
	 * node is returned, identical to a request with no envelope. This prevents the
	 * envelope from being used to read unpublished content.
	 */
	public function testPreviewEnvelopeIgnoredForUnauthenticatedRequest() {
		wp_set_current_user( 0 );

		$query = '
		query Post( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				content
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'      => $query,
				'variables'  => [ 'id' => $this->post ],
				'extensions' => [ 'preview' => [ 'id' => $this->post ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertStringContainsString( 'Published Content', $actual['data']['post']['content'], 'An unauthenticated request must never see preview content via the envelope' );

		// A debug-only notice explains why the preview was ignored (GRAPHQL_DEBUG is on in tests).
		$debug_types = wp_list_pluck( $actual['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'PREVIEW_EXTENSION_IGNORED', $debug_types, 'A debug notice should explain the ignored preview' );
	}

	/**
	 * An invalid preview id in the envelope (a post that does not exist, or one the
	 * viewer cannot edit) must be treated as if no envelope were provided. No error is
	 * thrown, so the envelope cannot be used to enumerate inaccessible content.
	 */
	public function testInvalidPreviewEnvelopeIdIsSilentlyIgnored() {
		wp_set_current_user( $this->admin );

		$query = '
		query Post( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				content
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'      => $query,
				'variables'  => [ 'id' => $this->post ],
				// A preview id that targets a different, non-existent post.
				'extensions' => [ 'preview' => [ 'id' => 99999999 ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual, 'Invalid preview input must not throw' );
		$this->assertStringContainsString( 'Published Content', $actual['data']['post']['content'], 'An envelope whose id does not match the queried post must be ignored' );
	}

	/**
	 * The `asPreview` argument should be marked deprecated in the schema in favor of the
	 * preview envelope.
	 */
	public function testAsPreviewArgumentIsDeprecatedInSchema() {
		$query = '
		query {
			__type( name: "RootQuery" ) {
				fields {
					name
					args( includeDeprecated: true ) {
						name
						isDeprecated
						deprecationReason
					}
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$found = false;
		foreach ( $actual['data']['__type']['fields'] as $field ) {
			if ( 'post' !== $field['name'] ) {
				continue;
			}
			foreach ( $field['args'] as $arg ) {
				if ( 'asPreview' === $arg['name'] ) {
					$found = true;
					$this->assertTrue( $arg['isDeprecated'], 'asPreview should be deprecated' );
					$this->assertNotEmpty( $arg['deprecationReason'] );
				}
			}
		}

		$this->assertTrue( $found, 'The post field should expose a deprecated asPreview argument' );
	}
}
