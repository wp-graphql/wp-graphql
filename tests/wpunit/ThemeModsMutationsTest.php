<?php

class ThemeModsMutationsTest extends \Codeception\TestCase\WPTestCase
{
    private $client_mutation_id;
    private $author;
    private $admin;
    private $subscriber;
    private $background_id;
    private $background_color;
    private $custom_css_post_id;
    private $logo_id;
    private $header_id;
    private $nav_menu_id;
    
    public function setUp()
    {

		// before
        parent::setUp();
        register_nav_menu( 'my-menu-location', 'My Menu Location' );

        $this->client_mutation_id = 'someUniqueId';
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
			'post_content' => 'some logo',
        ] );
        $this->background_id = $this->createPostObject( [
			'post_type'   => 'attachment',
			'post_content' => 'some background',
        ] );
        $this->background_color = 'dd56d3';
        $this->header_id = $this->createPostObject( [
			'post_type'   => 'attachment',
			'post_content' => 'some header',
        ] );
        $this->custom_css_post_id = $this->createPostObject( [
			'post_type'   => 'post',
			'post_content' => '<p>some css</p>',
        ] );
        $this->nav_menu_id = wp_create_nav_menu( 'My Menu' );
        
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
    public function testThemeModsMutation()
    {

        wp_set_current_user( $this->admin );

        /**
         * Test mutation
         */
        $query = '
            mutation themeModsMutation(
                $clientMutationId:  String!
                $background:        CustomBackgroundInput,
                $backgroundColor:   String,
                $customCssPost:     Int,
                $customLogo:        Int,
                $headerImage:       CustomHeaderInput,
                $navMenuLocations:  NavMenuLocationsInput
            ){
                updateThemeMods( input: {
                    clientMutationId:   $clientMutationId
                    background:         $background,
                    backgroundColor:    $backgroundColor,
                    customCssPost:      $customCssPost,
                    customLogo:         $customLogo,
                    headerImage:        $headerImage,
                    navMenuLocations:   $navMenuLocations,
                } ) {
                    clientMutationId
                    themeMods {
                        backgroundColor
                        customCssPostId
                        customCss {
                            postId
                            content
                        }
                        customLogo {
                            mediaItemId
                        }
                        navMenu( location: MY_MENU_LOCATION ) {
                            menuId
                            name
                        }
                    }
                }
            }
        ';
        
        $variables = [
            'clientMutationId'  => $this->client_mutation_id,
            'background'        => [
                'imageId'       => $this->background_id,
                'preset'        => 'default',
                'size'          => '50% 100%',
                'repeat'        => 'no-repeat',
                'attachment'    => 'fixed',
            ],
            'backgroundColor'   => $this->background_color,
            'customCssPost'     => $this->custom_css_post_id,
            'customLogo'        => $this->logo_id,
            'headerImage'       => [
                'imageId'   => $this->header_id,
                'height'    => 384,
                'width'     => 1175,        
            ],
            'navMenuLocations'  => [ 'my-menu-location' => $this->nav_menu_id ],
        ];

 		$actual = do_graphql_request( $query, 'themeModsMutation', $variables );
         
        /**
         * Can't retrieve test header or background image due to no files 
         * being in the wp upload directory
         */
        $expected = [
            'data' => [
                'updateThemeMods' => [
                    'clientMutationId'  => $this->client_mutation_id,
                    'themeMods'         => [
                        'backgroundColor'   => $this->background_color,
                        'customLogo'        => [
                            'mediaItemId' => $this->logo_id,
                        ],
                        'customCssPostId'   => $this->custom_css_post_id,
                        'customCss'         => [
                            'postId'    => $this->custom_css_post_id,
                            'content'   => '<p>some css</p>
',
                        ],
                        'navMenu'           => [
                            'menuId'    => $this->nav_menu_id,
                            'name'      => 'My Menu',
                        ],
                    ],
                ],
            ],
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

    public function testThemeModsMutationNotAuthorized()
    {

        wp_set_current_user( $this->subscriber );
        
        /**
         * Test mutation
         */
        $query = '
            mutation themeModsMutation(
                $clientMutationId:   String!
                $background:        CustomBackgroundInput,
                $backgroundColor:   String,
                $customCssPost:     Int,
                $customLogo:        Int,
                $headerImage:       CustomHeaderInput,
                $navMenuLocations:  NavMenuLocationsInput
            ){
                updateThemeMods( input: {
                    clientMutationId:   $clientMutationId
                    background:         $background,
                    backgroundColor:    $backgroundColor,
                    customCssPost:      $customCssPost,
                    customLogo:         $customLogo,
                    headerImage:        $headerImage,
                    navMenuLocations:   $navMenuLocations,
                } ) {
                    clientMutationId
                    themeMods {
                        background {
                            mediaItemId
                        }
                        backgroundColor
                        customCssPostId
                        customCss {
                            postId
                            content
                        }
                        customLogo {
                            mediaItemId
                        }
                        headerImage {
                            mediaItemId
                        }
                        navMenu(location: MY_MENU_LOCATION) {
                            menuId
                            name
                        }
                    }
                }
            }
        ';
        
        $variables = [
            'clientMutationId'  => $this->client_mutation_id,
            'background'        => [
                'imageId'       => $this->background_id,
                'preset'        => 'default',
                'size'          => '50% 100%',
                'repeat'        => 'no-repeat',
                'attachment'    => 'fixed',
            ],
            'backgroundColor'   => $this->background_color,
            'customCssPost'     => $this->custom_css_post_id,
            'customLogo'        => $this->logo_id,
            'headerImage'       => [
                'imageId'   => $this->header_id,
                'height'    => 384,
                'width'     => 1175,        
            ],
            'navMenuLocations'  => [ 'my-menu-location' => $this->nav_menu_id ],
        ];

 		$actual = do_graphql_request( $query, 'themeModsMutation', $variables );
         
        $expected = [
            'errors' => [
                [
                    'message' => 'Sorry, you are not allowed to edit theme settings as this user.',
                    'category' => 'user',
                    'locations' => [
                        [
                            'line' => 11,
                            'column' => 17,
                        ],
                    ],
                    'path' => [ 'updateThemeMods' ]
                ]
            ],
            'data' => [
                'updateThemeMods' => null
            ],
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