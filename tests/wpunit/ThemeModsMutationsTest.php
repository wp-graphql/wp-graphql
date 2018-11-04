<?php

class ThemeModsMutationsTest extends \Codeception\TestCase\WPTestCase
{
    private $author;
    private $admin;
    private $subscriber;
    public function setUp()
    {;
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

    public function tearDown()
    {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    // tests
    public function testThemeModsMutation()
    {
        $theme_mods = \WPGraphQL\Data\DataSource::resolve_theme_mods_data();
        /**
         * use --debug flag to view
         */
        \Codeception\Util\Debug::debug( $theme_mods );

        wp_set_current_user( $this->admin );

        /**
         * Test mutation
         */
        $query = '
            mutation themeModsMutation(  ){
                updateThemeMods( ) {
                    themeMods {
                        
                    }
                }
            }
        ';
        
        $variables = [ 

        ];

 		$actual = do_graphql_request( $query, 'themeModsMutation', $variables );
         
        $expected = [
            'data' => [
                'updateThemeMods' => [
                    'themeMods'         => [
                        
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
    }

    public function testThemeModsMutationNotAuthorized()
    {

        wp_set_current_user( $this->subscriber );
        
        /**
         * Test mutation
         */
        $query = '
            mutation themeModsMutation(  ){
                updateThemeMods( ) {
                    themeMods {
                        
                    }
                }
            }
        ';
        
        $variables = [ 

        ];

 		$actual = do_graphql_request( $query, 'themeModsMutation', $variables );
         
        $expected = [
            'data' => [
                'updateThemeMods' => [
                    'themeMods'         => [
                        
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

    }

}