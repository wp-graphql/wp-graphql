<?php

class ThemeModsQueriesTest extends \Codeception\TestCase\WPTestCase
{

    // tests
    public function testThemeModsQuery()
    {
        /**
         * Retrieve all theme mods
         */
        $theme_mods = get_theme_mods();

        /**
         * use --debug flag to view
         */
        \Codeception\Util\Debug::debug( [$theme_mods, true] );


 		$query = '
		query themeModsQuery{
			themeMods{

            }
		}
        ';
        
        $actual = do_graphql_request( $query, 'themeModsQuery' );
         $expected = array(
            'data' => array(
                
            )
        );


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