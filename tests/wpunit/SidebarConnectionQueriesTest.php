<?php

class SidebarConnectionQueriesTest extends \Codeception\TestCase\WPTestCase
{

    public function setUp()
    {
        // before
        parent::setUp();

        // your set up methods here
    }

    public function tearDown()
    {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    // tests
    public function testSidebarsQuery()
    {
        $query = '
		{
		  sidebars {
		    edges {
		      node {
		        id
		        name
		      }
		    }
		    nodes {
		      id
		    }
		  }
		}
        ';
        
        $actual = do_graphql_request( $query );

        $expected = array(
            'data' => array(
                'sidebars' => array(
                    'edges' => array(
                        array(
                            'node' => array(
                                'id' => 'c2lkZWJhcjpzaWRlYmFyLTE=',
                                'name' => 'Blog Sidebar',
                            )      
                        ),
                        array(
                            'node' => array(
                                'id' => 'c2lkZWJhcjpzaWRlYmFyLTI=',
                                'name' => 'Footer 1',
                            )
                        ),
                        array(
                            'node' => array(
                                'id' => 'c2lkZWJhcjpzaWRlYmFyLTM=',
                                'name' => 'Footer 2',
                            )
                        ),
                    ),
                    'nodes' => array(
                        array(
                            'id' => 'c2lkZWJhcjpzaWRlYmFyLTE='
                        ),
                        array(
                            'id' => 'c2lkZWJhcjpzaWRlYmFyLTI='
                        ),
                        array(
                            'id' => 'c2lkZWJhcjpzaWRlYmFyLTM='
                        )
                    )
                )
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