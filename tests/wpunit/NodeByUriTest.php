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
}
