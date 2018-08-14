<?php

class ThemeModsTest extends \Codeception\TestCase\WPTestCase
{
    private $author;
    private $admin;
    private $subscriber;

    public function setUp() {
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
	}

    // tests
    public function testThemeModsQueryAndMutation()
    {

        $theme_mods = \WPGraphQL\Data\DataSource::get_theme_mods_data();
        /**
         * use --debug flag to view
         */
        \Codeception\Util\Debug::debug( $theme_mods );

        wp_set_current_user( $this->admin );

        /**
         * Create nav menu
         */
        $menu_id = wp_create_nav_menu('Test Menu');

        /**
         * Create clientMutationID
         */
        $mutation_id = 'someMutationId';

        /**
         * Test mutation
         */
        $query = '
		mutation themeModsMutation($mutationId: String!, $menuId: Int!){
			updateThemeMods(input: {
                    clientMutationId: $mutationId
                    navMenuLocations: {
                        primary: $menuId 
                    }
            } ) {
                clientMutationId
                themeMods {
                    navMenuLocations(location: "primary") {
                        menuId
                    }
                }
            }
        }
        ';
        
        $variables = [ 'mutationId' => $mutation_id, 'menuId' => $menu_id ];

 		$actual = do_graphql_request( $query, 'themeModsMutation', $variables );
         
        $expected = [
            'data' => [
                'updateThemeMods' => [
                    'clientMutationId'  => $mutation_id,
                    'themeMods'         => [
                        'navMenuLocations' => [
                            'menuId' => $menu_id
                        ]
                    ],
                ]
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

        /**
         * Test Query
         */
 		$query = '
		query themeModsQuery($location: String!){
			themeMods {
                navMenuLocations(location: $location){
                    menuId
                }
            }
        }
        ';
        
        $variables = [ 'location' => 'primary' ];

 		$actual = do_graphql_request( $query, 'themeModsQuery', $variables );
         
        $expected = [
            'data' => [
                'themeMods'         => [
                    'navMenuLocations' => [
                        'menuId' => $menu_id,
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