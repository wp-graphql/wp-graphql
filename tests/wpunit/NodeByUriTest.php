<?php

class NodeByUriTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $post;
	public $page;
	public $user;

	public function setUp(): void {
		parent::setUp();
		// Set category base to empty string to avoid issues with the test

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();

		register_post_type('by_uri_cpt', [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'CustomType',
			'graphql_plural_name' => 'CustomTypes',
			'public'              => true,
		]);

		register_taxonomy( 'by_uri_tax', 'by_uri_cpt', [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'CustomTax',
			'graphql_plural_name' => 'CustomTaxes',

		]);

		flush_rewrite_rules( true );

		$this->clearSchema();

		$this->user = $this->factory()->user->create([
			'role' => 'administrator',
		]);
	}

	public function tearDown(): void {
		wp_delete_user( $this->user );
		unregister_post_type( 'by_uri_cpt' );
		unregister_taxonomy( 'by_uri_tax' );

		$this->clearSchema();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );


		parent::tearDown();
	}

	/**
	 * Get a Post by it's permalink.
	 *
	 * @throws Exception
	 */
	public function testPostByUri() {
		$post_id = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Test postByUri',
			'post_author' => $this->user,
		] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Post {
					databaseId
				}
				isContentNode
				isTermNode
				uri
			}
		}
		';

		// Test with bad URI
		$uri = '/2022/12/31/bad-uri/';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		$uri = wp_make_link_relative( get_permalink( $post_id ) );
		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 *  uri => /{year}/{month}/{day}/test-postbyuri/
		 * 'page' => '',
		 * 'year => {year},
		 * 'monthnum' => {month},
		 * 'day' => {day},
		 * 'name' => 'test-postbyuri',
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isTermNode'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => p={post_id}
		 * p => {post_id}
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test with fixed base.
		$this->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );


		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		codecept_debug( $uri );

		// Test without base.
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => str_replace( '/blog', '', $uri )
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /blog/{year}/{month}/{day}/test-postbyuri/
		 * 'page' => '',
		 * 'year => {year},
		 * 'monthnum' => {month},
		 * 'day' => {day},
		 * 'name' => 'test-postbyuri',
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isTermNode'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

	}

	public function testPostWithAnchorByUri() {
		$post_id = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Test postWithAnchorByUri',
			'post_author' => $this->user,
		] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Post {
					databaseId
				}
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		// Test with /#anchor.
		$uri .= '#test-anchor';

		codecept_debug( $uri );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertStringStartsWith( $actual['data']['nodeByUri']['uri'], $uri );

		// Test with #anchor
		$uri = str_replace( '/#', '#', $uri );

		codecept_debug( $uri );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertStringStartsWith( rtrim( $actual['data']['nodeByUri']['uri'], '/' ), $uri );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_permalink( $post_id ) );
		
		// test with /#anchor
		$uri .= '/#test-anchor';

		codecept_debug( $uri );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertStringStartsWith( $actual['data']['nodeByUri']['uri'], $uri );

		// Test with #anchor

		$uri = str_replace( '/#', '#', $uri );

		codecept_debug( $uri );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertStringStartsWith( rtrim( $actual['data']['nodeByUri']['uri'], '/' ), $uri );
	}

	/**
	 * @throws Exception
	 */
	public function testPageByUri() {
		$page_id = $this->factory()->post->create( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Test pageByUri',
			'post_author' => $this->user,
		] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Page {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		// Test with a bad URI.
		$uri = '/bad-uri/';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		// Test with valid uri.

		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /test-pagebyuri/
		 * page => '',
		 * pagename => 'test-pagebyuri',
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'page' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $page_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isTermNode'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );
		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		codecept_debug( $uri );
		
		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => page_id={page_id}
		 * page_id => {page_id}
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'page' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $page_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test with fixed base.
		$this->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		codecept_debug( $uri );

		// Test without base.
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => '/not-real'
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		//Test with unwanted base.
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => '/blog' . $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		// Test with actual uri
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'page' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $page_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	/**
	 * @throws Exception
	 */
	public function testCustomPostTypeByUri() {
		$cpt_id = $this->factory()->post->create( [
			'post_type'   => 'by_uri_cpt',
			'post_status' => 'publish',
			'post_title'  => 'Test customPostTypeByUri',
			'post_author' => $this->user,
		] );
		codecept_debug( get_post( $cpt_id ) );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on CustomType {
					databaseId
				}
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_permalink( $cpt_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /by_uri_cpt/test-customposttypebyuri/
		 * page => '',
		 * by_uri_cpt => test-customposttypebyuri,
		 * post_type => by_uri_cpt,
		 * name => test-customposttypebyuri,
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'by_uri_cpt' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $cpt_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_permalink( $cpt_id ) );

		codecept_debug( $uri );
		
		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => by_uri_cpt=test-customposttypebyuri
		 * by_uri_cpt => test-customposttypebyuri
		 * post_type => by_uri_cpt
		 * name => test-customposttypebyuri
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'by_uri_cpt' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $cpt_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	/**
	 * @throws Exception
	 */
	public function testCategoryByUri() {
		$category_id = $this->factory()->term->create( [
			'taxonomy' => 'category',
			'name'     => 'Test categoryByUri',
		] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Category {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_category_link( $category_id ));

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /category/test-categorybyuri/
		 * category_name => test-categorybyuri,
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_taxonomy( 'category' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $category_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_category_link( $category_id ));

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => cat={category_id}
		 * cat => {category_id}
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_taxonomy( 'category' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $category_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );
	}

	/**
	 * @throws Exception
	 */
	public function testTagByUri() {
		$tag_id = $this->factory()->term->create( [
			'taxonomy' => 'post_tag',
			'name'     => 'Test tagByUri',
		] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Tag {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_term_link( $tag_id ));

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /tag/test-tagbyuri/
		 * tag => test-tagbyuri,
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_taxonomy( 'post_tag' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $tag_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_term_link( $tag_id ));

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => tag={tag_id}
		 * tag => test-tagbyuri
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertSame( ucfirst( get_taxonomy( 'post_tag' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $tag_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );
	}

	public function testPostFormatByUri() {
		$post_id = $this->factory()->post->create( [
			'post_title' => 'Test postFormatByUri',
			'post_type'  => 'post',
			'post_status' => 'publish',
		] );

		set_post_format( $post_id, 'aside' );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on PostFormat {
					databaseId
				}
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_post_format_link( 'aside' ));

		codecept_debug( $uri );

		$term = get_term_by('slug', 'post-format-aside', 'post_format');
		
		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /type/aside/
		 * post_format => post-format-aside
		 * post_type => [ post ]
		 * 
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->markTestIncomplete( 'PostFormat archives not implemented. See: https://github.com/wp-graphql/wp-graphql/issues/2190' );
		$this->assertSame( `postFormat`, $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $term->term_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );
		create_initial_taxonomies();

		$uri = wp_make_link_relative( get_post_format_link( 'aside' ));

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => post_format={aside}
		 * post_format => post-format-aside
		 * post_type => [ post ]
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( `postFormat`, $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $term->term_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	/**
	 * @throws Exception
	 */
	public function testCustomTaxTermByUri() {
		$term_id = $this->factory()->term->create( [
			'taxonomy' => 'by_uri_tax',
			'name'     => 'Test customTaxTermByUri',
		] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on CustomTax {
					databaseId
				}
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_term_link( $term_id ));

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /by_uri_tax/test-customtaxtermbyuri/
		 * by_uri_tax => test-customtaxtermbyuri
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['nodeByUri'] );
		$this->assertSame( ucfirst( get_taxonomy( 'by_uri_tax' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $term_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test without pretty permalinks.
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_term_link( $term_id ));

		codecept_debug ( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => by_uri_tax={term_id}
		 * by_uri_tax => test-customtaxtermbyuri
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $uri
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['nodeByUri'] );
		$this->assertSame( ucfirst( get_taxonomy( 'by_uri_tax' )->graphql_single_name ), $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $term_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	/**
	 * @throws Exception
	 */
	public function testHomePageByUri() {

		$title   = 'Home Test' . uniqid();
		$post_id = $this->factory()->post->create([
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $title,
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
		update_option( 'show_on_front', 'posts' );

		/**
		 * For _all_ homepage queries, NodeResolver::parse_request() only generates the following query var:
		 * uri => /
		 */
		$actual = $this->graphql( [ 'query' => $query ] );

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
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['nodeByUri'] );
		$this->assertSame( '/', $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isPostsPage'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );

		// If the "show_on_front" and "page_on_front" value are both set,
		// the node should be the Page that is set
		update_option( 'page_on_front', $post_id );
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertSame( $title, $actual['data']['nodeByUri']['title'] );
		$this->assertSame( 'Page', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isPostsPage'] );

	}

	/**
	 * @throws Exception
	 */
	public function testAuthorByUri() {
		$post_id = $this->factory()->post->create([
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_author' => $this->user,
		]);

		$uri = wp_make_link_relative( get_author_posts_url( $this->user ) );

		codecept_debug( $uri );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on User {
					databaseId
				}
				uri
			}
		}
		';

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /author/{user_name}/
		 * author_name => {user_name}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'User', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->user, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test with pretty permalinks disabled
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_author_posts_url( $this->user ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /?author={user_id}
		 * author => {user_id}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `author`' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'User', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $this->user, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	public function testDateYearArchiveByUri() {
		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on ContentType {
						name
					}
					uri
				}
			}
		';

		// Test year archive
		$uri = wp_make_link_relative( get_year_link( gmdate( 'Y' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}/
		 * year => {year}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `date archives`. See https://github.com/wp-graphql/wp-graphql/issues/2191' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );

		// Test with pretty permalinks disabled
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_year_link( gmdate( 'Y' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => m={year}
		 * m => {year}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );

	}

	public function testDateMonthArchiveByUri() {
		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on ContentType {
						name
					}
					uri
				}
			}
		';

		// Test month archive
		$uri = wp_make_link_relative( get_month_link( gmdate( 'Y' ), gmdate( 'm' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}/{month}/
		 * year => {year}
		 * monthnum => {month}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `date archives`. See https://github.com/wp-graphql/wp-graphql/issues/2191' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );

		// Test with pretty permalinks disabled
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_month_link( gmdate( 'Y' ), gmdate( 'm' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => m={year}{month}
		 * m => {year}{month}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );

	}

	public function testDateDayArchiveByUri() {
		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on ContentType {
						name
					}
					uri
				}
			}
		';

		// Test day archive
		$uri = wp_make_link_relative( get_day_link( gmdate( 'Y' ), gmdate( 'm' ), gmdate( 'd' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}/{month}/{day}/
		 * year => {year}
		 * monthnum => {month}
		 * day => {day}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `date archives`. See https://github.com/wp-graphql/wp-graphql/issues/2191' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );

		// Test with pretty permalinks disabled
		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_day_link( gmdate( 'Y' ), gmdate( 'm' ), gmdate( 'd' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => m={year}{month}{day}
		 * m => {year}{month}{day}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );
	}

	public function testMediaItemByUri() {
		$attachment_id = $this->factory()->attachment->create_object( [
			'file'           => 'example.jpg',
			'post_title'     => 'Example Image',
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
		] );

		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on MediaItem {
						databaseId
					}
					uri
				}
			}'
		;

		$uri = wp_make_link_relative( get_permalink( $attachment_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{slug}/
		 * page => ''
		 * pagename => {slug}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'MediaItem', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $attachment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test with pretty permalinks disabled

		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_permalink( $attachment_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => attachment_id={attachment_id}
		 * attachment_id => {attachment_id}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `attachment_id`.');

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'MediaItem', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $attachment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	public function testMediaItemWithParentByUri() {
		$post_id = $this->factory()->post->create( [
			'post_title' => 'Example Post',
			'post_type'  => 'post',
			'post_status' => 'publish',
		] );
		$attachment_id = $this->factory()->attachment->create_object( [
			'file'           => 'example.jpg',
			'post_title'     => 'Example Image',
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id
		] );

		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on MediaItem {
						databaseId
					}
					uri
				}
			}'
		;

		$uri = wp_make_link_relative( get_permalink( $attachment_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}{monthnum}{day}/{postslug}/{slug}
		 * attachment => {slug}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `attachment`. See https://github.com/wp-graphql/wp-graphql/issues/2178');


		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'MediaItem', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $attachment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test with pretty permalinks disabled

		$this->set_permalink_structure( '' );

		$uri = wp_make_link_relative( get_permalink( $attachment_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => attachment_id={attachment_id}
		 * attachment_id => {attachment_id}
		 */
		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'uri' => $uri,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'MediaItem', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $attachment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	public function testPageQueryWhenPageIsSetToHomePage() {

		$page_id = $this->factory()->post->create([
			'post_type'   => 'page',
			'post_status' => 'publish',
		]);

		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'page' );

		$query = '
		{
			page( id:"/" idType: URI ) {
				__typename
				databaseId
				isPostsPage
				isFrontPage
				title
				uri
			}
		}
		';

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * post_type => page
		 * archive => ''
		 * nodeType => ContentNode
		 * uri = /
		 */
		$actual = $this->graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $page_id, $actual['data']['page']['databaseId'] );
		$this->assertTrue( $actual['data']['page']['isFrontPage'] );
		$this->assertSame( '/', $actual['data']['page']['uri'] );

		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'posts' );

		$actual = $this->graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( null, $actual['data']['page'] );
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
				'slug'       => 'test_hierarchical',
				'with_front' => false,
			],
			'capability_type'     => 'page',
			'has_archive'         => false,
			'hierarchical'        => true,
			'menu_position'       => null,
			'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes' ],
			'show_in_rest'        => true,
			'rest_base'           => 'test-hierarchical',
			'show_in_graphql'     => true,
			'graphql_single_name' => 'testHierarchical',
			'graphql_plural_name' => 'testHierarchicals',
		]);

		flush_rewrite_rules( true );

		$parent = $this->factory()->post->create([
			'post_type'    => 'test_hierarchical',
			'post_title'   => 'Test for HierarchicalCptNodesByUri',
			'post_content' => 'test',
			'post_status'  => 'publish',
		]);

		$child = $this->factory()->post->create([
			'post_type'    => 'test_hierarchical',
			'post_title'   => 'Test child for HierarchicalCptNodesByUri',
			'post_content' => 'child',
			'post_parent'  => $parent,
			'post_status'  => 'publish',
		]);

		// Test all nodes return
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

		$actual = $this->graphql( [ 'query' => $query ] );
		codecept_debug( wp_make_link_relative( get_permalink( $child ) ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$database_ids = wp_list_pluck( $actual['data']['testHierarchicals']['nodes'], 'databaseId' );

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

		$child_uri = wp_make_link_relative( get_permalink( $child ) );

		codecept_debug( $child_uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /test_hierarchical/test-for-hierarchicalcptnodesbyuri/test-child-for-hierarchicalcptnodesbyuri/
		 * page => ''
		 * test_hierarchical => test-for-hierarchicalcptnodesbyuri/test-child-for-hierarchicalcptnodesbyuri/
		 * 'post_type' => 'test_hierarchical'
		 * 'name' => 'test-for-hierarchicalcptnodesbyuri/test-child-for-hierarchicalcptnodesbyuri'
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $child_uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $child_uri, $actual['data']['nodeByUri']['uri'], 'Makes sure the uri of the node matches the uri queried with' );
		$this->assertSame( 'TestHierarchical', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $child, $actual['data']['nodeByUri']['databaseId'] );

		$parent_uri = wp_make_link_relative( get_permalink( $parent ) );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /test_hierarchical/test-for-hierarchicalcptnodesbyuri/
		 * page => ''
		 * test_hierarchical => test-for-hierarchicalcptnodesbyuri
		 * 'post_type' => 'test_hierarchical'
		 * 'name' => 'test-for-hierarchicalcptnodesbyuri'
		 */
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => $parent_uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $parent_uri, $actual['data']['nodeByUri']['uri'], 'Makes sure the uri of the node matches the uri queried with' );
		$this->assertSame( 'TestHierarchical', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $parent, $actual['data']['nodeByUri']['databaseId'] );

		unregister_post_type( 'test_hierarchical' );
	}

	public function testExternalUriReturnsNull() {

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

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'uri' => 'https://external-uri.com/path-to-thing',
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( null, $actual['data']['nodeByUri'] );

	}

	public function testMediaWithExternalUriReturnsNull() {

		$query = '
		query Media( $uri: ID! ){
			mediaItem(id: $uri, idType: URI) {
				id
				title
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => 'https://icd.wordsinspace.net/wp-content/uploads/2020/10/955000_2-scaled.jpg',
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( null, $actual['data']['mediaItem'] );

		$query = '
		query Media( $uri: ID! ){
			mediaItem(id: $uri, idType: SOURCE_URL) {
				id
				title
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'uri' => 'https://icd.wordsinspace.net/wp-content/uploads/2020/10/955000_2-scaled.jpg',
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( null, $actual['data']['mediaItem'] );

	}

	public function testParseRequestFilterExecutesOnNodeByUriQueries() {

		$value = null;

		// value should be null
		$this->assertNull( $value );

		// value should NOT be instance of Wp class
		$this->assertNotInstanceOf( 'Wp', $value );

		// We hook into parse_request
		// set the value of $value to the value of the $wp argument
		// that comes through the filter
		add_action( 'parse_request', function ( WP $wp ) use ( &$value ) {
			if ( is_graphql_request() ) {
				$value = $wp;
			}
		});

		$query = '
		{
			nodeByUri(uri:"/about") {
				__typename
				id
				uri
			}
		}
		';

		// execute a nodeByUri query
		graphql([
			'query' => $query,
		]);

		codecept_debug( $value );

		// ensure the $value is now an instance of Wp class
		// as set by the filter in the node resolver
		$this->assertNotNull( $value );
		$this->assertInstanceOf( 'Wp', $value );

	}

	public function testPageForPostsByUri() {

		$page_id = self::factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Blog'
		]);

		update_option( 'page_for_posts', $page_id );

		$query = '
		query NodeByUri($uri:String!) {
			nodeByUri( uri: $uri ) {
				__typename
				uri
			}
		}
		';

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri: /blog
		 * page => ''
		 * pagename => 'blog'
		 */
		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => '/blog'
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );

		delete_option( 'page_for_posts' );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => '/blog'
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Page', $actual['data']['nodeByUri']['__typename'] );

	}

}
