<?php

class NodeByUriTest extends \Codeception\TestCase\WPTestCase {

	public $post;
	public $page;
	public $user;
	public $tag;
	public $category;
	public $custom_type;
	public $custom_taxonomy;

	public function setUp(): void {

		WPGraphQL::clear_schema();

		register_post_type('custom_type', [
			'show_in_graphql' => true,
			'graphql_single_name' => 'CustomType',
			'graphql_plural_name' => 'CustomTypes',
			'public' => true,
		]);

		register_taxonomy( 'custom_tax', 'custom_type', [
			'show_in_graphql' => true,
			'graphql_single_name' => 'CustomTax',
			'graphql_plural_name' => 'CustomTaxes',
		]);

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->user = $this->factory()->user->create([
			'role' => 'administrator',
		]);

		$this->tag = $this->factory()->term->create([
			'taxonomy' => 'post_tag',
		]);

		$this->category = $this->factory()->term->create([
			'taxonomy' => 'category',
		]);

		$this->custom_taxonomy = $this->factory()->term->create([
			'taxonomy' => 'custom_tax',
		]);

		$this->post = $this->factory()->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_author' => $this->user,
		] );

		$this->page = $this->factory()->post->create( [
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Test Page',
			'post_author' => $this->user
		] );

		$this->custom_type = $this->factory()->post->create( [
			'post_type' => 'custom_type',
			'post_status' => 'publish',
			'post_title' => 'Test Page',
			'post_author' => $this->user
		] );

		parent::setUp();

	}

	public function tearDown(): void {

		unregister_post_type( 'custom_type' );
		WPGraphQL::clear_schema();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		parent::tearDown();
		wp_delete_post( $this->post );
		wp_delete_post( $this->page );
		wp_delete_post( $this->custom_post_type );
		wp_delete_term( $this->tag, 'post_tag' );
		wp_delete_term( $this->category, 'category' );
		wp_delete_term( $this->custom_taxonomy, 'custom_tax' );
		wp_delete_user( $this->user );

	}

	public function set_permalink_structure( $structure = '' ) {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );
		$wp_rewrite->flush_rules( true );
	}

	/**
	 * Get a Post by it's permalink
	 * @throws Exception
	 */
	public function testPostByUri() {
		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Post {
				  postId
				}
			}
		}
		';

		codecept_debug( get_permalink( $this->post ) );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->post ),
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->post, $actual['data']['nodeByUri']['postId'] );

		$this->set_permalink_structure( '' );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->post ),
			],
		]);

		codecept_debug( get_permalink( $this->post ) );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->post, $actual['data']['nodeByUri']['postId'] );
	}

	/**
	 * @throws Exception
	 */
	function testPageByUri() {
		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Page {
				  pageId
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->page ),
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'page' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->page, $actual['data']['nodeByUri']['pageId'] );

		$this->set_permalink_structure( '' );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->page ),
			],
		]);


		codecept_debug( get_permalink( $this->page ) );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'page' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->page, $actual['data']['nodeByUri']['pageId'] );


	}

	/**
	 * @throws Exception
	 */
	function testCustomPostTypeByUri() {

		codecept_debug( get_post( $this->custom_type ) );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on CustomType {
				  customTypeId
				}
			}
		}
		';

		flush_rewrite_rules( true );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->custom_type ),
			],
		]);

		codecept_debug( get_permalink( $this->custom_type ) );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'custom_type' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->custom_type, $actual['data']['nodeByUri']['customTypeId'] );

		$this->set_permalink_structure( '' );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->custom_type ),
			],
		]);


		codecept_debug( get_permalink( $this->page ) );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'custom_type' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->custom_type, $actual['data']['nodeByUri']['customTypeId'] );


	}

	/**
	 * @throws Exception
	 */
	function testCategoryByUri() {
		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Category {
				  categoryId
				}
			}
		}
		';

		codecept_debug( get_term_link( $this->category ) );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_term_link( $this->category ),
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_taxonomy( 'category' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->category, $actual['data']['nodeByUri']['categoryId'] );


	}

	/**
	 * @throws Exception
	 */
	function testTagByUri() {

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Tag {
				  tagId
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_term_link( $this->tag ),
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_taxonomy( 'post_tag' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->tag, $actual['data']['nodeByUri']['tagId'] );

	}

	/**
	 * @throws Exception
	 */
	function testCustomTaxTermByUri() {

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on CustomTax {
				  customTaxId
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_term_link( $this->custom_taxonomy ),
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_taxonomy( 'custom_tax' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->custom_taxonomy, $actual['data']['nodeByUri']['customTaxId'] );

	}

	/**
	 * @throws Exception
	 */
	public function testHomePageByUri() {

		$title = 'Home Test' . uniqid();
		$post_id = $this->factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => $title
		]);

		$query = '
		{
		  nodeByUri(uri: "/") {
		    __typename
		    uri
		    ... on Page {
		      title
		      isPostsPage
		      isFrontPage
		    }
		    ... on ContentType {
		      name
		      isPostsPage
		      isFrontPage
		    }
		  }
		}
		';

		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', 0 );
		update_option( 'show_on_front', 0 );

		$actual = graphql([ 'query' => $query ]);
		codecept_debug( $actual );

		// When the page_on_front, page_for_posts and show_on_front are all not set, the `/` uri should return
		// the post ContentType as the homepage node
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['nodeByUri'] );
		$this->assertSame( '/', $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isPostsPage'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );

		// if the "show_on_front" is set to page, but no page is specifically set, the
		// homepage should still be the Post ContentType
		update_option( 'show_on_front', 'page' );
		$actual = graphql([ 'query' => $query ]);
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['nodeByUri'] );
		$this->assertSame( '/', $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isPostsPage'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );

		// If the "show_on_front" and "page_on_front" value are both set,
		// the node should be the Page that is set
		update_option( 'page_on_front', $post_id );
		$actual = graphql([ 'query' => $query ]);
		codecept_debug( $actual );
		$this->assertSame( $title, $actual['data']['nodeByUri']['title'] );
		$this->assertSame( 'Page', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isPostsPage'] );

	}

	/**
	 * @throws Exception
	 */
	public function testHierarchicalCptNodesByUri() {

		register_post_type( 'test_hierarchical', [
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => true,
			'rewrite'             => [
				'slug' => 'test_hierarchical',
				'with_front' => false
			],
			'capability_type'     => 'page',
			'has_archive'         => false,
			'hierarchical'        => true,
			'menu_position'       => null,
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes' ),
			'show_in_rest'        => true,
			'rest_base'           => 'test-hierarchical',
			'show_in_graphql'     => true,
			'graphql_single_name' => 'testHierarchical',
			'graphql_plural_name' => 'testHierarchicals',
		]);

		flush_rewrite_rules( true );

		$parent = $this->factory()->post->create([
			'post_type' => 'test_hierarchical',
			'post_title' => 'test',
			'post_content' => 'test',
			'post_status' => 'publish',
		]);

		$child = $this->factory()->post->create([
			'post_type' => 'test_hierarchical',
			'post_title' => 'child',
			'post_content' => 'child',
			'post_parent' => $parent,
			'post_status' => 'publish',
		]);

		$query = '
		{
		  testHierarchicals {
		    nodes {
		      id
		      databaseId
		      title
		      uri
		    }
		  }
		}
		';

		$actual = graphql(['query' => $query]);
		codecept_debug( $actual );
		codecept_debug( parse_url( get_permalink( $child ), PHP_URL_PATH ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$database_ids = wp_list_pluck( $actual['data']['testHierarchicals']['nodes'], 'databaseId' );
		codecept_debug( $database_ids );
		$this->assertTrue( in_array( $child, $database_ids, true ) );
		$this->assertTrue( in_array( $parent, $database_ids, true ) );

		$query = '
		query NodeByUri( $uri: String! ) {
		  nodeByUri( uri: $uri ) {
		     uri
		     __typename
		     ...on DatabaseIdentifier {
		       databaseId
		     }
		  }
		}
		';

		$child_uri = parse_url( get_permalink( $child ), PHP_URL_PATH );
		codecept_debug( $child_uri );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => $child_uri,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $child_uri, $actual['data']['nodeByUri']['uri'], 'Makes sure the uri of the node matches the uri queried with' );
		$this->assertSame( 'TestHierarchical', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $child, $actual['data']['nodeByUri']['databaseId'] );

		codecept_debug( $actual );

		$parent_uri = parse_url( get_permalink( $parent ), PHP_URL_PATH );
		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => $parent_uri,
			],
		]);

		codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $parent_uri, $actual['data']['nodeByUri']['uri'], 'Makes sure the uri of the node matches the uri queried with' );
		$this->assertSame( 'TestHierarchical', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $parent, $actual['data']['nodeByUri']['databaseId'] );

	}
}
