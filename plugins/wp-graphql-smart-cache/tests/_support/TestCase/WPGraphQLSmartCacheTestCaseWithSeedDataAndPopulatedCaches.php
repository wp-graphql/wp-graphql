<?php
namespace TestCase\WPGraphQLSmartCache\TestCase;

use Exception;
use Tests\WPGraphQL\TestCase\WPGraphQLTestCase;
use WP_Comment;
use WP_Nav_Menu_Item;
use WP_Post;
use WP_Term;
use WP_User;
use WPGraphQL\SmartCache\Cache\Collection;

class WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches extends WPGraphQLTestCase {

	/**
	 * @var WP_User
	 */
	public $admin;

	/**
	 * @var WP_User
	 */
	public $editor;

	/**
	 * @var Collection
	 */
	public $collection;

	/**
	 * @var WP_Post
	 */
	public $published_post;

	/**
	 * @var WP_Post
	 */
	public $draft_post;

	/**
	 * @var WP_Post
	 */
	public $published_page;

	/**
	 * @var WP_Post
	 */
	public $draft_page;

	/**
	 * @var WP_Post
	 */
	public $published_test_post_type;

	/**
	 * @var WP_Post
	 */
	public $published_test_post_type_with_term;

	/**
	 * @var WP_Post
	 */
	public $draft_test_post_type;

	/**
	 * @var WP_Post;
	 */
	public $published_private_post_type;

	/**
	 * @var WP_Post;
	 */
	public $draft_private_post_type;

	/**
	 * @var WP_Post
	 */
	public $published_publicly_queryable_post_type;

	/**
	 * @var WP_Post
	 */
	public $draft_publicly_queryable_post_type;

	/**
	 * @var WP_Post
	 */
	public $scheduled_post;

	/**
	 * @var WP_Post
	 */
	public $published_post_by_editor;

	/**
	 * @var WP_Post
	 */
	public $scheduled_post_with_category;

	/**
	 * @var WP_Post
	 */
	public $scheduled_custom_post_type;

	/**
	 * @var WP_Post
	 */
	public $scheduled_custom_post_type_with_term;

	/**
	 * @var WP_Term
	 */
	public $category;

	/**
	 * @var WP_Term
	 */
	public $empty_category;

	/**
	 * @var WP_Term
	 */
	public $test_taxonomy_term;

	/**
	 * @var WP_Term
	 */
	public $private_taxonomy_term;

	/**
	 * @var WP_Post
	 */
	public $mediaItem;

	/**
	 * @var WP_Comment
	 */
	public $approved_comment;

	/**
	 * @var WP_Comment
	 */
	public $unapproved_comment;

	/**
	 * @var WP_Term
	 */
	public $tag;

	/**
	 * @var WP_Term
	 */
	public $empty_tag;

	/**
	 * @var WP_Term
	 */
	public $empty_test_taxonomy_term;

	/**
	 * Holds the results of the executed queries. For reference in assertions in tests.
	 *
	 * @var array
	 */
	public $query_results;

	/**
	 * @var WP_Term
	 */
	public $public_menu;

	/**
	 * @var WP_Term
	 */
	public $private_menu;

	/**
	 * @var WP_Nav_Menu_Item
	 */
	public $private_menu_item;

	/**
	 * @var WP_Nav_Menu_Item
	 */
	public $menu_item_1;

	/**
	 * @var WP_Nav_Menu_Item
	 */
	public $child_menu_item;

	public function setUp(): void {
		parent::setUp();

		/**
		 * Clear the schema
		 */
		$this->clearSchema();

		// enable graphql cache maps
		add_filter( 'wpgraphql_cache_enable_cache_maps', '__return_true' );

		// prevent default category from being added to posts on creation
		update_option( 'default_category', 0 );

		// enable caching for the whole test suite
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		register_post_type( 'test_post_type', [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestPostType',
			'graphql_plural_name' => 'TestPostTypes'
		] );

		register_taxonomy( 'test_taxonomy', [ 'test_post_type' ], [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestTaxonomyTerm',
			'graphql_plural_name' => 'TestTaxonomyTerms'
		] );

		register_post_type( 'private_post_type', [
			'public' => false,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PrivatePostType',
			'graphql_plural_name' => 'PrivatePostTypes'
		] );

		register_post_type( 'publicly_queryable', [
			'public' => false,
			'publicly_queryable' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PubliclyQueryablePost',
			'graphql_plural_name' => 'PubliclyQueryablePosts'
		]);

		register_taxonomy( 'private_taxonomy', [ 'private_post_type' ], [
			'public' => false,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PrivateTaxonomyTerm',
			'graphql_plural_name' => 'PrivateTaxonomyTerms'
		] );

		$this->_createSeedData();
		$this->_populateCaches();

	}

	public function tearDown(): void {

		// disable caching
		delete_option( 'graphql_cache_section' );

		parent::tearDown();
	}

	public function _createSeedData() {

		// setup access to the Cache Collection class
		$this->collection = new Collection();

		// create an admin user
		$this->admin = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		$this->editor = self::factory()->user->create_and_get([
			'role' => 'editor'
		]);

		// create a test tag
		$this->tag = self::factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
			'term' => 'Test Tag'
		]);

		$this->empty_tag = self::factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
			'term' => 'Empty Tag'
		]);

		// create a test category
		$this->category = self::factory()->term->create_and_get([
			'taxonomy' => 'category',
			'term' => 'Test Category'
		]);

		$filename = WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . '/tests/_data/images/test.png';
		$image_id = self::factory()->attachment->create_upload_object( $filename );
		$this->mediaItem = get_post( $image_id );

		$this->empty_category = self::factory()->term->create_and_get([
			'taxonomy' => 'category',
			'term' => 'Empty Category'
		]);

		$this->test_taxonomy_term = self::factory()->term->create_and_get([
			'taxonomy' => 'test_taxonomy',
			'term' => 'Test Custom Tax Term'
		]);

		$this->empty_test_taxonomy_term = self::factory()->term->create_and_get([
			'taxonomy' => 'test_taxonomy',
			'term' => 'Empty Test Custom Tax Term'
		]);

		$this->private_taxonomy_term = self::factory()->term->create_and_get([
			'taxonomy' => 'private_taxonomy',
			'term' => 'Private Term'
		]);

		// create a published post
		$this->published_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->published_post_by_editor = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $this->editor->ID,
		]);

		$this->scheduled_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'future',
			'post_title' => 'Test Scheduled Post',
			'post_author' => $this->admin->ID,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
		]);

		$this->scheduled_post_with_category = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'future',
			'post_title' => 'Test Scheduled Post',
			'post_author' => $this->admin->ID,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
			'post_category' => [ $this->category->term_id ]
		]);

		$this->scheduled_custom_post_type = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'future',
			'post_title' => 'Test Custom Post Type Post',
			'post_author' => $this->admin->ID,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
		]);

		$this->scheduled_custom_post_type_with_term = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'future',
			'post_title' => 'Test Custom Post Type Post',
			'post_author' => $this->admin->ID,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
			'tax_input' => [
				'test_taxonomy' => [ $this->test_taxonomy_term->slug ]
			]
		]);

		// associate the scheduled type with the taxonomy term
		// for whatever reason, tax_input above isn't working 😢🤷‍♂️
		wp_set_object_terms( $this->scheduled_custom_post_type_with_term->ID, $this->test_taxonomy_term->term_id, 'test_taxonomy' );

		$this->published_test_post_type_with_term = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'publish',
			'post_title' => 'Test Custom Post Type Post with Term',
			'post_author' => $this->admin->ID,
			'tax_input' => [
				'test_taxonomy' => [ $this->test_taxonomy_term->slug ]
			]
		]);

		// associate the scheduled type with the taxonomy term
		// for whatever reason, tax_input above isn't working 😢🤷‍♂️
		wp_set_object_terms( $this->published_test_post_type_with_term->ID, $this->test_taxonomy_term->term_id, 'test_taxonomy' );


		// create a draft post
		$this->draft_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		// create a published page
		$this->published_page = self::factory()->post->create_and_get([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->draft_page = self::factory()->post->create_and_get([
			'post_type' => 'page',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		$this->published_test_post_type = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->draft_test_post_type = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		$this->published_private_post_type = self::factory()->post->create_and_get([
			'post_type' => 'private_post_type',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->draft_private_post_type = self::factory()->post->create_and_get([
			'post_type' => 'private_post_type',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		$this->published_publicly_queryable_post_type = self::factory()->post->create_and_get([
			'post_type' => 'publicly_queryable',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->draft_publicly_queryable_post_type = self::factory()->post->create_and_get([
			'post_type' => 'publicly_queryable',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		$this->public_menu = self::factory()->term->create_and_get([
			'name' => 'test menu',
			'taxonomy' => 'nav_menu'
		]);

		$this->private_menu = self::factory()->term->create_and_get([
			'name' => 'private menu',
			'taxonomy' => 'nav_menu'
		]);

		$this->menu_item_1 = self::factory()->post->create_and_get([
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish'
		]);

		// set the parent menu item
		wp_update_nav_menu_item( $this->public_menu->term_id, $this->menu_item_1->ID, [
			'menu-item-title' => 'Test Item',
			'menu-item-object'    => 'post',
			'menu-item-object-id' => $this->published_post->ID,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'post_type',
		]);

		$this->child_menu_item = self::factory()->post->create_and_get([
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish'
		]);

		// set a child menu item
		wp_update_nav_menu_item( $this->public_menu->term_id, $this->child_menu_item->ID, [
			'menu-item-title' => 'Child Item',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $this->published_page->ID,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'post_type',
			'menu-item-parent-id'    => $this->menu_item_1->ID
		]);

		$this->private_menu_item = self::factory()->post->create_and_get([
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish'
		]);

		wp_update_nav_menu_item( $this->private_menu->term_id, $this->private_menu_item->ID, [
			'menu-item-title' => 'Private Menu Item',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $this->published_page->ID,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'post_type',
		]);

		// register a menu location
		register_nav_menu( 'default-location', 'Test Menu Location' );

		// add the menu to a default location
		set_theme_mod( 'nav_menu_locations', [ 'default-location' => (int) $this->public_menu->term_id ] );

		$this->approved_comment = self::factory()->comment->create_and_get([
			'comment_approved' => 1,
			'comment_post_ID' => $this->published_post->ID,
		]);

		$this->unapproved_comment = self::factory()->comment->create_and_get([
			'comment_approved' => 0,
		]);

//		$this->assertInstanceOf( \WP_User::class, $this->admin );
//		$this->assertInstanceOf( \WP_Post::class, $this->published_post );
//		$this->assertInstanceOf( \WP_Post::class, $this->draft_post );
//		$this->assertInstanceOf( \WP_Post::class, $this->published_page );
//		$this->assertInstanceOf( \WP_Post::class, $this->published_test_post_type );
//		$this->assertInstanceOf( \WP_Post::class, $this->draft_test_post_type );
//		$this->assertInstanceOf( \WP_Post::class, $this->published_private_post_type );
//		$this->assertInstanceOf( \WP_Post::class, $this->draft_private_post_type );
//		$this->assertInstanceOf( \WP_Post::class, $this->scheduled_post_with_category );
//		$this->assertInstanceOf( \WP_Term::class, $this->category );
//		$this->assertInstanceOf( \WP_Term::class, $this->tag );
//		$this->assertInstanceOf( \WP_Term::class, $this->test_taxonomy_term );
//		$this->assertInstanceOf( \WP_Term::class, $this->private_taxonomy_term );
	}

	public function _populateCaches() {

		// purge all caches to clean up
		$this->collection->purge_all();

		// clear the query results as they'll be populated again
		// when the queries are executed and cached
		$this->query_results = [];

		// execute and cache the queries
		$this->executeAndCacheQueries();

	}

	/**
	 * @param string $query_name The name of the query to get
	 *
	 * @return string|null
	 */
	public function getQuery( string $query_name ): ?string {
		$queries = $this->getQueries();
		return $queries[ $query_name ]['query'] ?? null;

	}

	public function getQueries() {

		return [
			'listPost' => [
				'name' => 'listPost',
				'query' => '
					query GetPosts {
					  posts {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'posts.nodes', [
						'__typename' => 'Post',
						'databaseId' => $this->published_post->ID
					])
				],
				'expectedCacheKeys' => [
					'list:post'
				]
			],
			'singlePost' => [
				'name' => 'singlePost',
				'query' => '
					query GetPost($id:ID!) {
					  post(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->published_post->ID ],
				'assertions' => [
					$this->expectedField( 'post.__typename', 'Post' ),
					$this->expectedField( 'post.databaseId', $this->published_post->ID )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_post->ID )
				]
			],
			'singlePostByEditor' => [
				'name' => 'singlePostByEditor',
				'query' => '
					query GetPost($id:ID!) {
					  post(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->published_post_by_editor->ID ],
				'assertions' => [
					$this->expectedField( 'post.__typename', 'Post' ),
					$this->expectedField( 'post.databaseId', $this->published_post_by_editor->ID )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_post_by_editor->ID )
				]
			],
			'listPage' => [
				'name' => 'listPage',
				'query' => '
					query GetPages {
					  pages {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'pages.nodes', [
						'__typename' => 'Page',
						'databaseId' => $this->published_page->ID,
					])
				],
				'expectedCacheKeys' => [
					'list:page'
				]
			],
			'singlePage' => [
				'name' => 'singlePage',
				'query' => '
					query GetPage($id:ID!) {
					  page(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->published_page->ID ],
				'assertions' => [
					$this->expectedField( 'page.__typename', 'Page' ),
					$this->expectedField( 'page.databaseId', $this->published_page->ID )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_page->ID ),
				]
			],
			'listTestPostType' => [
				'name' => 'listTestPostType',
				'query' => '
					query GetTestPostTypes {
					  testPostTypes {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'testPostTypes.nodes', [
						'__typename' => 'TestPostType',
						'databaseId' => $this->published_test_post_type->ID,
					])
				],
				'expectedCacheKeys' => [
					'list:testPostType'
				]
			],
			'singleTestPostType' => [
				'name' => 'singleTestPostType',
				'query' => '
					query GetTestPostType($id:ID!) {
					  testPostType(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->published_test_post_type->ID ],
				'assertions' => [
					$this->expectedField( 'testPostType.__typename', 'TestPostType' ),
					$this->expectedField( 'testPostType.databaseId', $this->published_test_post_type->ID )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_test_post_type->ID )
				]
			],
			'listPrivatePostType' => [
				'name' => 'listPrivatePostType',
				'query' => '
					query GetPrivatePostTypes {
					  privatePostTypes {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					// the nodes should be empty because it's a private post type
					$this->expectedField( 'privatePostTypes.nodes', [] )
				],
				'expectedCacheKeys' => null,
			],
			'listPubliclyQueryablePostType' => [
				'name' => 'listPubliclyQueryablePostType',
				'query' => 'query{publiclyQueryablePosts{nodes{databaseId __typename}}}'
			],
			'singlePrivatePostType' => [
				'name' => 'singlePrivatePostType',
				'query' => '
					query GetPrivatePostType($id:ID!) {
					  privatePostType(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->published_private_post_type->ID ],
				'assertions' => [
					// since it's a private post type, the data should be null
					$this->expectedField( 'privatePostType', self::IS_NULL ),
				],
				'expectedCacheKeys' => null,
			],
			'singleContentNode' => [
				'name' => 'singleContentNode',
				'query' => '
					query GetContentNode($id:ID!) {
					  contentNode(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [
					'id' => $this->published_post->ID,
				],
				'assertions' => [
					$this->expectedField( 'contentNode.__typename', 'Post' ),
					$this->expectedField( 'contentNode.databaseId', $this->published_post->ID )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_post->ID ),
				],
			],
			'listContentNode' => [
				'name' => 'listContentNode',
				'query' => '
					query GetContentNodes {
					  contentNodes {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => [
					'id' => $this->published_post->ID,
				],
				'assertions' => [
					$this->expectedObject( 'contentNodes.nodes', [
						'__typename' => 'Post',
						'databaseId' => $this->published_post->ID
					]),
					$this->expectedObject( 'contentNodes.nodes', [
						'__typename' => 'Page',
						'databaseId' => $this->published_page->ID
					])
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_post->ID ),
					$this->toRelayId( 'post', $this->published_page->ID ),
				],
			],
			'singleNodeById' => [
				'name' => 'singleNodeById',
				'query' => '
					query GetNode($id: ID!) {
					  node(id: $id) {
					    __typename
					    id
					    ... on DatabaseIdentifier {
					      databaseId
					    }
					  }
					}
				',
				'variables' => [ 'id' => $this->toRelayId( 'post', $this->published_post->ID ) ],
				'assertions' => [
					$this->expectedField( 'node.__typename', 'Post' ),
					$this->expectedField( 'node.databaseId', $this->published_post->ID )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'post', $this->published_post->ID )
				]
			],
			'singleNodeByUri' => [
				'name' => 'singleNodeByUri',
				'query' => '
					query GetNodeByUri($uri: String!) {
					  nodeByUri(uri: $uri) {
					    __typename
					    id
					    ... on DatabaseIdentifier {
					      databaseId
					    }
					  }
					}
				',
				'variables' => [ 'uri' => get_permalink( $this->published_post->ID ) ],
				'assertions' => [
					$this->expectedField( 'nodeByUri.__typename', 'Post' ),
					$this->expectedField( 'nodeByUri.databaseId', $this->published_post->ID )
				]
			],
			'listTag' => [
				'name' => 'listTag',
				'query' => '
					query GetTags {
					  tags {
					    nodes {
					      __typename
					      databaseId
					      name
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'tags.nodes', [
						'__typename' => 'Tag',
						'databaseId' => $this->tag->term_id,
						'name' => $this->tag->name,
					])
				],
				'expectedCacheKeys' => [
					'list:tag'
				],
			],
			'singleTag' => [
				'name' => 'singleTag',
				'query' => '
					query GetTag($id:ID!) {
					  tag(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->tag->term_id ],
				'assertions' => [
					$this->expectedField( 'tag.__typename', 'Tag' ),
					$this->expectedField( 'tag.databaseId', $this->tag->term_id )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'term', $this->tag->term_id )
				]
			],
			'listCategory' => [
				'name' => 'listCategory',
				'query' => '
					query GetCategories {
					  categories {
					    nodes {
					      __typename
					      databaseId
					      name
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'categories.nodes', [
						'__typename' => 'Category',
						'databaseId' => $this->category->term_id,
						'name' => $this->category->name,
					])
				],
				'expectedCacheKeys' => [
					'list:category'
				],
			],
			'singleCategory' => [
				'name' => 'singleCategory',
				'query' => '
					query GetCategory($id:ID!) {
					  category(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->category->term_id ],
				'assertions' => [
					$this->expectedField( 'category.__typename', 'Category' ),
					$this->expectedField( 'category.databaseId', $this->category->term_id )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'term', $this->category->term_id )
				]
			],
			'singleTestTaxonomyTerm' => [
				'name' => 'singleTestTaxonomyTerm',
				'query' => '
					query GetTestTaxonomyTerm($id:ID!) {
					  testTaxonomyTerm(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->test_taxonomy_term->term_id ],
				'assertions' => [
					$this->expectedField( 'testTaxonomyTerm.__typename', 'TestTaxonomyTerm' ),
					$this->expectedField( 'testTaxonomyTerm.databaseId', $this->test_taxonomy_term->term_id )
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'term', $this->test_taxonomy_term->term_id )
				]
			],
			'listTestTaxonomyTerm' => [
				'name' => 'listTestTaxonomyTerm',
				'query' => '
					query GetTestTaxonomyTerms {
					  testTaxonomyTerms {
					    nodes {
					      __typename
					      databaseId
					      name
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'testTaxonomyTerms.nodes', [
						'__typename' => 'TestTaxonomyTerm',
						'databaseId' => $this->test_taxonomy_term->term_id,
						'name' => $this->test_taxonomy_term->name,
					])
				],
				'expectedCacheKeys' => [
					'list:testTaxonomyTerm'
				],
			],
			'userWithPostsConnection' => [
				'name' => 'userWithPostsConnection',
				'query' => '
					query GetUser($id:ID!) {
					  user(id:$id idType:DATABASE_ID) {
				        __typename
				        databaseId
				        posts {
				          nodes {
				            __typename
				            databaseId
				          }
				        }
					  }
					}
				',
				'variables' => [ 'id' => $this->admin->ID ],
				'assertions' => [
					$this->expectedField( 'user.__typename', 'User' ),
					$this->expectedField( 'user.databaseId', $this->admin->ID ),
					$this->expectedNode( 'user.posts.nodes', [
						'__typename' => 'Post',
						'databaseId' => $this->published_post->ID,
					])
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'user', $this->admin->ID )
				]
			],
			'editorUserWithPostsConnection' => [
				'name' => 'editorUserWithPostsConnection',
				'query' => '
					query GetUser($id:ID!) {
					  user(id:$id idType:DATABASE_ID) {
				        __typename
				        databaseId
				        posts {
				          nodes {
				            __typename
				            databaseId
				          }
				        }
					  }
					}
				',
				'variables' => [ 'id' => $this->editor->ID ],
				'assertions' => [
					$this->expectedField( 'user.__typename', 'User' ),
					$this->expectedField( 'user.databaseId', $this->editor->ID ),
					$this->expectedNode( 'user.posts.nodes', [
						'__typename' => 'Post',
						'databaseId' => $this->published_post_by_editor->ID,
					])
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'user', $this->editor->ID )
				]
			],
			'adminUserByDatabaseId' => [
				'name' => 'adminUserByDatabaseId',
				'query' => '
					query GetUser($id:ID!) {
					  user(id:$id idType:DATABASE_ID) {
				        __typename
				        databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->admin->ID ],
				'assertions' => [
					$this->expectedField( 'user.__typename', 'User' ),
					$this->expectedField( 'user.databaseId', $this->admin->ID ),
				],
				'expectedCacheKeys' => [
					$this->toRelayId( 'user', $this->admin->ID )
				],

			],
			'listUser' => [
				'name' => 'listUser',
				'query' => '
					{
					  users {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'users.nodes', [
						'__typename' => 'User',
						'databaseId' => $this->admin->ID,
					]),
					$this->expectedObject( 'users.nodes', [
						'__typename' => 'User',
						'databaseId' => $this->editor->ID,
					])
				],
			],
			'generalSettings' => [
				'name' => 'generalSettings',
				'query' => '
					query GetGeneralSettings {
					  generalSettings {
					    dateFormat
					    description
					    language
					    startOfWeek
					    timeFormat
					    timezone
					    title
					    url
					  }
					}
				',
			],
			'writingSettings' => [
				'name' => 'writingSettings',
				'query' => '
				query GetWritingSettings {
				  writingSettings {
				    defaultCategory
				    defaultPostFormat
				    useSmilies
				  }
				}
				',
			],
			'discussionSettings' => [
				'name' => 'discussionSettings',
				'query' => '
					query GetDiscussionSettings {
						discussionSettings {
					        defaultCommentStatus
					        defaultPingStatus
					    }
					}
				',
 			],
			'allSettings' => [
				'name' => 'allSettings',
				'query' => '
					query GetAllSettings {
					  allSettings {
					    discussionSettingsDefaultCommentStatus
					    discussionSettingsDefaultPingStatus
					    generalSettingsDateFormat
					    generalSettingsDescription
					    generalSettingsLanguage
					    generalSettingsStartOfWeek
					    generalSettingsTimeFormat
					    readingSettingsPostsPerPage
					    writingSettingsDefaultCategory
					    writingSettingsDefaultPostFormat
					    writingSettingsUseSmilies
					  }
					}
				'
			],
			'singleMenu' => [
				'name' => 'singleMenu',
				'query' => '
					query GetMenu($id:ID!) {
					  menu(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [
					'id' => (int) $this->public_menu->term_id
				]
			],
			'listMenu' => [
				'name' => 'listMenu',
				'query' => '
					query GetMenus {
					  menus {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				'
			],
			'singlePrivateMenu' => [
				'name' => 'singlePrivateMenu',
				'query' => '
					query GetMenu($id:ID!) {
					  menu(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [
					'id' => (int) $this->private_menu->term_id
				]
			],
			'listMenuItem' => [
				'name' => 'listMenuItem',
				'query' => '
					query GetMenuItems {
					  menuItems {
					    nodes {
					      __typename
					      databaseId
					      parentDatabaseId
					    }
					  }
					}
				',
			],
			'singleMenuItem' => [
				'name' => 'singleMenuItem',
				'query' => '
					query GetMenuItem($id:ID!) {
					  menuItem(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					    parentDatabaseId
					  }
					}
				',
				'variables' => [
					'id' => $this->menu_item_1->ID,
				]
			],
			'singleChildMenuItem' => [
				'name' => 'singleChildMenuItem',
				'query' => '
					query GetMenuItem($id:ID!) {
					  menuItem(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					    parentDatabaseId
					  }
					}
				',
				'variables' => [
					'id' => $this->child_menu_item->ID
				]
			],
			'singlePrivateMenuItem' => [
				'name' => 'singlePrivateMenuItem',
				'query' => '
					query GetMenuItem($id:ID!) {
					  menuItem(id:$id idType: DATABASE_ID) {
					    __typename
					    databaseId
					    parentDatabaseId
					  }
					}
				',
				'variables' => [
					'id' => $this->private_menu_item->ID,
				]
			],
			'singleMediaItem' => [
				'name' => 'singleMediaItem',
				'query' => '
					query GetSingleMediaItem($id:ID!) {
					  mediaItem(id:$id idType:DATABASE_ID) {
					    __typename
					    databaseId
					  }
					}
				',
				'variables' => [
					'id' => $this->mediaItem->ID
				],
			],
			'listMediaItem' => [
				'name' => 'listMediaItem',
				'query' => '
					query GetListMediaItems {
					  mediaItems {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
			],
			'singleApprovedCommentByGlobalId' => [
				'name' => 'singleApprovedCommentByGlobalId',
				'query' => '
					query GetComment($id:ID!) {
					  comment(id:$id) {
				        __typename
				        databaseId
					  }
					}
				',
				'variables' => [ 'id' => $this->toRelayId( 'comment', $this->approved_comment->comment_ID ) ],
				'expectedCacheKeys' => [
					$this->toRelayId( 'comment', $this->approved_comment->comment_ID )
				],
			],
			'listComment' => [
				'name' => 'listComment',
				'query' => '
					query GetComments {
					  comments {
					    nodes {
					      __typename
					      databaseId
					    }
					  }
					}
				',
				'variables' => null,
			],

//			@todo: I believe the WPGraphQL Model Layer might have some bugs to fix re: private taxonomies? 🤔
//			'singlePrivateTaxonomyTerm' => [
//				'name' => 'singlePrivateTaxonomyTerm',
//				'query' => $this->getSinglePrivateTaxonomyTermByDatabaseIdQuery(),
//				'variables' => [ 'id' => $this->private_taxonomy_term->term_id ],
//				'assertions' => [
//					$this->expectedField( 'privateTaxonomyTerm', self::IS_NULL ),
//				],
//				'expectedCacheKeys' => []
//			],
//			'listPrivateTaxonomyTerm' => [
//				'name' => 'listPrivateTaxonomyTerm',
//				'query' => $this->getListTestTaxonomyTermsQuery(),
//				'variables' => null,
//				'assertions' => [
//					$this->expectedField( 'privateTaxonomyTerms.nodes', [])
//				],
//				'expectedCacheKeys' => [],
//			],
			// @todo: menus, menuItems, comments, users, settings...
		];
	}

	/**
	 * NOTE: I tried using a dataProvider but data providers run BEFORE setUp and that
	 * caused a lot of other headaches
	 *
	 * @throws Exception
	 */
	public function executeAndCacheQueries() {

		$queries = $this->getQueries();

		foreach ( $queries as $query_name => $testable_query ) {

			$name = isset( $testable_query['name'] ) ? $testable_query['name'] : $query_name;
			$query = isset( $testable_query['query'] ) ? $testable_query['query'] : null;
			$variables = isset( $testable_query['variables'] ) ? $testable_query['variables'] : null;
			$assertions = isset( $testable_query['assertions'] ) ? $testable_query['assertions'] : [];
			$expectedCacheKeys = isset( $testable_query['expectedCacheKeys'] ) ? $testable_query['expectedCacheKeys'] : [];

			if ( ! $query ) {
				continue;
			}

			// build the cache key
			$cache_key = $this->collection->build_key( null, $query, $variables );

			// ensure there's no value already cached under the cache key
			// $this->assertEmpty( $this->collection->get( $cache_key ) );

			// execute the query
			$actual = graphql( [
				'query'     => $query,
				'variables' => $variables,
			] );

			// we only want to do this if there are errors to surface
			if ( array_key_exists( 'errors', $actual ) ) {
				codecept_debug( [ 'actual' => $actual ]);
				$this->assertArrayNotHasKey( 'errors', $actual );
			}

			// ensure the query was successful
 		    // $this->assertQuerySuccessful( $actual, $assertions );

			// ensure the results are now cached under the cache key
			// $this->assertNotEmpty( $this->collection->get( $cache_key ) );
			// $this->assertSame( $actual, $this->collection->get( $cache_key ) );

			// check any expected cache keys to ensure they're not empty
			if ( ! empty( $expectedCacheKeys ) ) {
				foreach ( $expectedCacheKeys as $expected_cache_key ) {
					$this->assertNotEmpty( $this->collection->get( $expected_cache_key ) );
					$this->assertContains( $cache_key, $this->collection->get( $expected_cache_key ) );
				}
			}

			$this->query_results[ $name ] = [
				'name' => $name,
				'actual'   => $actual,
				'cacheKey' => $cache_key,
				'expectedCacheKeys' => $expectedCacheKeys,
				'query' => $query,
				'variables' => $variables,
			];

		}

		return $this->query_results;
	}


	/**
	 * @return array
	 */
	public function getEvictedCaches() {
		$evicted = [];
		if ( ! empty( $this->query_results ) ) {
			foreach ( $this->query_results as $name => $result ) {
				$cache = $this->collection->get( $result['cacheKey'] );
				if ( empty( $cache ) ) {
					$evicted[] = $name;
				}
			}
		}

		return $evicted;
	}

	/**
	 * @return int[]|string[]
	 */
	public function getNonEvictedCaches() {
		return array_diff( array_keys( $this->query_results ), $this->getEvictedCaches() );
	}

}
