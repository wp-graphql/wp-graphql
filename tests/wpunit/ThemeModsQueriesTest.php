<?php

class ThemeModsQueriesTest extends \Codeception\TestCase\WPTestCase
{

    private $author;
    private $admin;
    private $subscriber;
    private $logo_id;
    private $custom_css_post_id;
    private $nav_menu_id;

    public function setUp()
    {

		// before
		parent::setUp();
		$this->author = $this->factory()->user->create( [
			'role' => 'author',
		] );
		$this->admin = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
		$this->subscriber = $this->factory()->user->create( [
			'role' => 'subscriber',
        ] );
        $this->logo_id = $this->createPostObject( [
			'post_type'   => 'attachment',
			'post_content' => 'some description',
        ] );
        $this->custom_css_post_id = $this->createPostObject( [
			'post_type'   => 'post',
			'post_content' => 'some css',
        ] );
        $this->nav_menu_id = wp_create_nav_menu( 'My Menu' );
        register_nav_menu( 'my-menu-location', 'My Menu Location' );
        
        set_theme_mod( 'custom_logo', $this->logo_id );
        set_theme_mod( 'custom_css_post_id', $this->custom_css_post_id );
		set_theme_mod( 'nav_menu_locations', [ 'my-menu-location' => $this->nav_menu_id ] );

    }

    public function tearDown() {
		// your tear down methods here

		// then
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
			'post_title'   => 'Test Title',
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

    // tests
    public function testThemeModsQuery()
    {
        /**
         * Test Query
         */
 		$query = '
            query themeModsQuery{
                themeMods {
                    background {
                        id
                        sourceUrl
                    }
                    backgroundColor
                    customCssPostId
                    customLogo
                    headerImage {
                        id
                        sourceUrl
                    }
                    navMenu(location: MY_MENU_LOCATION) {
                        menuId
                        name
                    }
                }
            }
        ';
        
        $variables = [ 

        ];

 		$actual = do_graphql_request( $query, 'themeModsQuery' );

        $expected = [
            'data' => [
                'themeMods'         => [
                    'background'        => null,
                    'backgroundColor'   => 'f1f1f1',
                    'customLogo'        => $this->logo_id,
                    'customCssPostId'   => $this->custom_css_post_id,
                    'headerImage'       => null,
                    'navMenu'           => [
                        'menuId'    => $this->nav_menu_id,
                        'name'      => 'My Menu',
                    ]
                ],
            ]
        ];

        /**
         * use --debug flag to view
         */
        \Codeception\Util\Debug::debug( $actual );

 		/**
		 * Compare the actual output vs the expected output
		 */
        $this->assertEquals( $expected, $actual );
        
	}

}