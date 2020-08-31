<?php

class NodeBySlugTest extends \Codeception\TestCase\WPTestCase {

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

		register_post_type('faq', [
			'show_in_graphql' => true,
			'graphql_single_name' => 'faq',
			'graphql_plural_name' => 'faqs',
			'public' => true,
		]);



		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->user = $this->factory()->user->create([
			'role' => 'administrator',
		]);

		$this->post = $this->factory()->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_author' => $this->user,
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
		wp_delete_post( $this->custom_post_type );
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
	public function testPostBySlug() {

	    $post = get_post($this->post);


		$query = '
        query GET_POST_BY_URI( $slug: ID! ) {
           post(id: $slug, idType: SLUG) {
                databaseId
                title
           }
		}
		';

		codecept_debug( get_post( $this->post ) );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'slug' => $post->post_name,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->post, $actual['data']['post']['databaseId'] );
		$this->assertSame( $post->post_title, $actual['data']['post']['title'] );


        $this->set_permalink_structure( "/$post->post_type/%postname%" );

        codecept_debug( $actual );

        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->post, $actual['data']['post']['databaseId'] );
        $this->assertSame( $post->post_title, $actual['data']['post']['title'] );


		$this->set_permalink_structure( '' );

		codecept_debug( $actual );

        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->post, $actual['data']['post']['databaseId'] );
        $this->assertSame( $post->post_title, $actual['data']['post']['title'] );
	}


    /**
     * Get a Post by it's permalink
     * @throws Exception
     */
    public function testCustomPostBySlug() {

        $post = get_post($this->custom_type);

        $query = '
        query GET_POST_BY_URI( $slug: ID! ) {
           customType(id: $slug, idType: SLUG) {
                databaseId
                title
           }
		}
		';

        codecept_debug( get_post( $this->custom_type ) );

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'slug' => $post->post_name,
            ],
        ]);

        codecept_debug( $actual );

        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->custom_type, $actual['data']['customType']['databaseId'] );
        $this->assertSame( $post->post_title, $actual['data']['customType']['title'] );


        $this->set_permalink_structure( "/$post->post_type/%postname%" );

        codecept_debug( $actual );

        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->custom_type, $actual['data']['customType']['databaseId'] );
        $this->assertSame( $post->post_title, $actual['data']['customType']['title'] );


        $this->set_permalink_structure( '' );

        codecept_debug( $actual );

        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->custom_type, $actual['data']['customType']['databaseId'] );
        $this->assertSame( $post->post_title, $actual['data']['customType']['title'] );
    }

    function testPageWithNameOfPostType () {

        $this->custom_type = $this->factory()->post->create( [
            'post_type' => 'faq',
            'post_status' => 'publish',
            'post_title' => 'Test FAQ',
            'post_author' => $this->user
        ] );

        $this->page = $this->factory()->post->create( [
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'FAQ',
            'post_author' => $this->user
        ] );


        $query = '
        query GET_POST_BY_SLUG( $slug: ID! ) {
           faq(id: $slug, idType: SLUG) {
                databaseId
                title
           }
		}
		';

        codecept_debug( get_post( $this->custom_type ) );

        $faqPost = get_post($this->custom_type);


        $actual = graphql([
            'query' => $query,
            'variables' => [
                'slug' => $faqPost->post_name,
            ],
        ]);

        codecept_debug( $actual );


        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->custom_type, $actual['data']['faq']['databaseId'] );
        $this->assertSame( $faqPost->post_title, $actual['data']['faq']['title'] );


        $query = '
        query GET_PAGE_BY_URI( $slug: ID! ) {
           page(id: $slug, idType: URI) {
                databaseId
                title
           }
		}
		';

        codecept_debug( get_post( $this->custom_type ) );

        $faqPage = get_post($this->page);


        $actual = graphql([
            'query' => $query,
            'variables' => [
                'slug' => $faqPage->post_name,
            ],
        ]);

        codecept_debug( $actual );

        $this->assertArrayNotHasKey( 'errors', $actual );
        $this->assertSame( $this->page, $actual['data']['page']['databaseId'] );
        $this->assertSame( $faqPage->post_title, $actual['data']['page']['title'] );

    }

}
