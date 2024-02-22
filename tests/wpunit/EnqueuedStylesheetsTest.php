<?php

class EnqueuedStylesheetsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $tag_id;
	public $post_id;
	public $page_id;
	public $category_id;
	public $page_handle;
	public $page_src;
	public $post_handle;
	public $post_src;
	public $custom_tax_id;
	public $custom_post_id;
	public $admin_id;
	public $author_id;
	public $media_id;

	public function setUp(): void {

		parent::setUp();

		$this->clearSchema();

		$this->admin_id = self::factory()->user->create(
			[
				'user_login' => uniqid(),
				'user_email' => uniqid() . '@test.com',
				'user_role'  => 'administrator',
			]
		);

		$this->author_id = self::factory()->user->create(
			[
				'user_login' => uniqid(),
				'user_email' => uniqid() . '@test.com',
				'user_role'  => 'author',
			]
		);

		register_post_type(
			'test_style_cpt',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestEnqueueCpt',
				'graphql_plural_name' => 'TestEnqueueCpts',
			]
		);

		register_taxonomy(
			'test_style_tax',
			[ 'test_style_cpt' ],
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestEnqueueTax',
				'graphql_plural_name' => 'TestEnqueueTaxes',
			]
		);

		$this->category_id = self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => uniqid(),
			]
		);

		$this->tag_id = self::factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => uniqid(),
			]
		);

		$this->custom_tax_id = self::factory()->term->create(
			[
				'taxonomy' => 'test_style_tax',
				'name'     => uniqid(),
			]
		);

		$this->page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test Page for EnqueuedStylesheetsTest',
				'post_author'  => $this->author_id,
				'post_excerpt' => '',
			]
		);

		$this->post_id = self::factory()->post->create(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => 'Test Post for EnqueuedStylesheetsTest',
				'post_category' => [ $this->category_id ],
				'tags_input'    => [ $this->tag_id ],
				'post_author'   => $this->author_id,
				'post_excerpt'  => 'Test excerpt',
			]
		);

		$filename       = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$this->media_id = self::factory()->attachment->create_upload_object( $filename, $this->post_id );

		$this->custom_post_id = self::factory()->post->create(
			[
				'post_type'    => 'test_style_cpt',
				'post_status'  => 'publish',
				'post_title'   => 'Test CPT for EnqueuedStylesheetsTest',
				'post_excerpt' => 'Test excerpt',
			]
		);

		$GLOBALS['post']       = null;
		$GLOBALS['authordata'] = null;
	}

	public function tearDown(): void {
		$this->clearSchema();
		wp_delete_term( $this->tag_id, 'post_tag' );
		wp_delete_term( $this->category_id, 'post_tag' );
		wp_delete_term( $this->custom_tax_id, 'post_tag' );
		wp_delete_post( $this->post_id, true );
		wp_delete_post( $this->page_id, true );
		wp_delete_post( $this->custom_post_id, true );
		wp_delete_attachment( $this->media_id, true );
		unregister_post_type( 'test_style_cpt' );
		unregister_taxonomy( 'test_style_tax' );
		$GLOBALS['post']       = null;
		$GLOBALS['authordata'] = null;
		parent::tearDown();
	}

	public function get_enqueued_assets_fragment() {
		return '
		fragment EnqueuedStyleFragment on EnqueuedStylesheet {
			handle
			src
		}
		';
	}

	/**
	 * Query a page by it's ID and get enqueued assets
	 *
	 * @param $page_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_page_query( $page_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query PageById( $id: ID! ) {
			page( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $page_id,
				],
			]
		);
	}

	/**
	 * Query a post by it's ID and get enqueued assets
	 *
	 * @param $post_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_post_query( $post_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query postById( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post_id,
				],
			]
		);
	}

	/**
	 * Query a post by it's ID and get enqueued assets
	 *
	 * @param $custom_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_custom_post_query( $custom_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query testEnqueueCpt( $id: ID! ) {
			testEnqueueCpt( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $custom_id,
				],
			]
		);
	}

	/**
	 * Query a tag by it's ID and get enqueued assets
	 *
	 * @param $tag_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_tag_query( $tag_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query tagById( $id: ID! ) {
			tag( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $tag_id,
				],
			]
		);
	}

	/**
	 * Query a category by it's database ID and get enqueued assets
	 *
	 * @param $cat_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_category_query( $cat_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query catById( $id: ID! ) {
			category( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $cat_id,
				],
			]
		);
	}

	/**
	 * Query a custom taxonomy by it's database ID and get enqueued assets
	 *
	 * @param $cat_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_custom_tax_query( $cat_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query catById( $id: ID! ) {
			category( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $cat_id,
				],
			]
		);
	}

	/**
	 * Query a User by it's database ID and get enqueued assets
	 *
	 * @param $user_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_user_query( $user_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query userById( $id: ID! ) {
			user( id: $id, idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $user_id,
				],
			]
		);
	}

	/**
	 * Query a MediaItem by it's database ID and get enqueued assets
	 *
	 * @param $media_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_media_query( $media_id ) {

		$fragment = $this->get_enqueued_assets_fragment();

		$query = '
		query mediaItem( $id: ID! ) {
			mediaItem( id: $id idType: DATABASE_ID ) {
				databaseId
				enqueuedStylesheets {
					nodes {
						...EnqueuedStyleFragment
					}
				}
			}
		}
		' . $fragment;

		return graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $media_id,
				],
			]
		);
	}

	/**
	 * Test whether assets enqueued to is_front_page are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Front_Page() {

		$handle = 'test-front-page';
		$src    = 'test-front-page.css';

		// Set the page to be the home page
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->page_id );

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_front_page() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		$actual = $this->get_page_query( $this->page_id );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT connected to another page
		$another_page_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Style for unique' . uniqid(),
			]
		);

		$actual = $this->get_page_query( $another_page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on POSTS
		$actual = $this->get_post_query( $this->post_id );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		wp_delete_post( $another_page_id, true );
	}

	/**
	 * Test whether assets enqueued to is_page are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Page() {

		$handle = 'test-is-page';
		$src    = 'test-is-page.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_page() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on POSTS
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}


	/**
	 * Test whether assets enqueued to is_single are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Single() {

		$handle = 'test-is-single';
		$src    = 'test-is-single.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_single() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_singular are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Singular() {

		$handle = 'test-is-singular';
		$src    = 'test-is-singular.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_singular( [ 'post', 'test_style_cpt' ] ) ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on posts
		$actual = $this->get_custom_post_query( $this->custom_post_id );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['testEnqueueCpt']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_sticky are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Sticky() {

		$handle = 'test-is-sticky';
		$src    = 'test-is-sticky.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_sticky() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// SET THE POST AS STICKY
		update_option( 'sticky_posts', [ $this->post_id ] );

		// Make sure the script IS enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_post_type_hierarchical are properly shown as connections
	 * to pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Post_Type_Hierarchical() {

		$handle = 'test-is-post-type-hierarchical';
		$src    = 'test-is-post-type-hierarchical.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				global $post;
				// codecept_debug( 'GLOBALPOST....' );
				// codecept_debug( $post );
				wp_register_style( $handle, $src );
				if ( isset( $post->post_type ) && is_post_type_hierarchical( $post->post_type ) ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		// codecept_debug( $handles );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Test that the script IS enqueued on pages
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to comments_open are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetComments_Open() {

		$handle = 'test-comments-open';
		$src    = 'test-comments-open.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {

				wp_register_style( $handle, $src );
				// codecept_debug( comments_open() );
				if ( comments_open() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );
		$this->assertFalse( comments_open( $this->page_id ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );
		$this->assertTrue( comments_open( $this->post_id ) );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to pings_open are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetPings_Open() {

		$handle = 'test-pings-open';
		$src    = 'test-pings-open.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				global $post;
				// codecept_debug( 'PING_STATUS' );
				// codecept_debug( $post );
				if ( is_a( $post, 'WP_Post' ) && pings_open() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );
		$this->assertFalse( pings_open( $this->page_id ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );
		$this->assertTrue( pings_open( $this->post_id ) );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_page_template are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Page_Template() {

		$page_template = 'test-page-template';
		$page_handle   = 'test-is-page-template';
		$page_src      = 'test-is-page-template.css';

		$post_template = 'test-post-template';
		$post_handle   = 'test-is-post-template';
		$post_src      = 'test-is-post-template.css';

		update_post_meta( $this->post_id, '_wp_page_template', $post_template );
		update_post_meta( $this->page_id, '_wp_page_template', $page_template );

		// codecept_debug( get_page_template_slug( $this->post_id ) );
		// codecept_debug( get_page_template_slug( $this->page_id ) );

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $page_handle, $page_src, $post_handle, $post_src, $page_template, $post_template ) {
				wp_register_style( $page_handle, $page_src );
				wp_register_style( $post_handle, $post_src );
				if ( is_page_template( $page_template ) ) {
					wp_enqueue_style( $page_handle );
				}
				if ( is_page_template( $post_template ) ) {
					wp_enqueue_style( $post_handle );
				}
			}
		);

		// Test that the script is enqueued on the page with the template
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $page_handle, $handles, true ) );
		$this->assertTrue( in_array( $page_src, $sources, true ) );

		$this->assertFalse( in_array( $post_handle, $handles, true ) );
		$this->assertFalse( in_array( $post_src, $sources, true ) );
		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $post_handle, $handles, true ) );
		$this->assertTrue( in_array( $post_src, $sources, true ) );
		$this->assertFalse( in_array( $page_handle, $handles, true ) );
		$this->assertFalse( in_array( $page_src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $page_handle, $handles, true ) );
		$this->assertFalse( in_array( $page_src, $sources, true ) );
		$this->assertFalse( in_array( $post_handle, $handles, true ) );
		$this->assertFalse( in_array( $post_src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_category are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Category() {

		$handle = 'test-is-category';
		$src    = 'test-is-category.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_category() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );
		$this->assertFalse( pings_open( $this->page_id ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );
		$this->assertTrue( pings_open( $this->post_id ) );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_tag are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Tag() {

		$handle = 'test-is-tag';
		$src    = 'test-is-tag.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_tag() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );
		$this->assertFalse( pings_open( $this->page_id ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );
		$this->assertTrue( pings_open( $this->post_id ) );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_tax are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Tax() {

		$handle = 'test-is-tax';
		$src    = 'test-is-tax.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_tax( 'post_tag' ) || is_tax( 'category' ) ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );
		$this->assertFalse( pings_open( $this->page_id ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );
		$this->assertTrue( pings_open( $this->post_id ) );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to has_term are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetHas_Term() {

		$handle = 'test-has-term';
		$src    = 'test-has-term.css';

		add_action(
			'wp_enqueue_scripts',
			function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( has_term( $this->tag_id, 'post_tag' ) ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );
		$this->assertFalse( pings_open( $this->page_id ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );
		$this->assertTrue( pings_open( $this->post_id ) );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_author are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Author() {

		$handle = 'test-is-author';
		$src    = 'test-is-author.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_author() ) {
					// codecept_debug( 'AUTHOR, YO!!' );
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on Tags
		$actual = $this->get_user_query( $this->author_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['user']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_author are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Attachment() {

		$handle = 'test-is-attachment';
		$src    = 'test-is-attachment.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( is_attachment() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Test that the script is NOT enqueued on pages
		$actual = $this->get_page_query( $this->page_id );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		// codecept_debug( $sources );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on Tags
		$actual = $this->get_user_query( $this->author_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['user']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on Tags
		$actual = $this->get_media_query( $this->media_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['mediaItem']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to wp_attachment_is_image are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetWP_Attachment_Is_Image() {

		$handle = 'test-is-wp-attachment-is-image';
		$src    = 'test-is-wp-attachment-is-image.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( wp_attachment_is_image() ) {
					wp_enqueue_style( $handle );
				}
			}
		);

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_tag_query( $this->tag_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['tag']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_category_query( $this->category_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['category']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on Tags
		$actual = $this->get_user_query( $this->author_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['user']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script IS enqueued on Tags
		$actual = $this->get_media_query( $this->media_id );
		// codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$styles  = $actual['data']['mediaItem']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );
	}

	/**
	 * Test whether assets enqueued to is_preview are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetIs_Preview() {
		// @todo
	}

	/**
	 * Test whether assets enqueued to has_excerpt are properly shown as connections to
	 * pages but not other nodes.
	 *
	 * @throws \Exception
	 */
	public function testEnqueuedStylesheetsHas_Excerpt() {

		$handle = 'test-has-excerpt';
		$src    = 'test-has-excerpt.css';

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $handle, $src ) {
				wp_register_style( $handle, $src );
				if ( has_excerpt() ) {
					global $post;
					// codecept_debug( 'HAS_EXCERPT, YO' );
					// codecept_debug( $post );
					wp_enqueue_style( $handle );
				}
			}
		);

		// Make sure the script is NOT enqueued on posts
		$actual = $this->get_post_query( $this->post_id );

		// codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles = $actual['data']['post']['enqueuedStylesheets']['nodes'];

		// codecept_debug( $styles );

		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );
		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_page_query( $this->page_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['page']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertFalse( in_array( $handle, $handles, true ) );
		$this->assertFalse( in_array( $src, $sources, true ) );

		// Make sure the script is NOT enqueued on Tags
		$actual = $this->get_custom_post_query( $this->custom_post_id );
		$this->assertArrayNotHasKey( 'errors', $actual );
		// codecept_debug( $actual );
		$styles  = $actual['data']['testEnqueueCpt']['enqueuedStylesheets']['nodes'];
		$handles = wp_list_pluck( $styles, 'handle' );
		$sources = wp_list_pluck( $styles, 'src' );

		$this->assertTrue( in_array( $handle, $handles, true ) );
		$this->assertTrue( in_array( $src, $sources, true ) );
	}
}
