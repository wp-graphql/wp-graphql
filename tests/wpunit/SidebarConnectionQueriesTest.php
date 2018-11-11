<?php

class SidebarConnectionQueriesTest extends \Codeception\TestCase\WPTestCase
{
    private $default_id = 'sidebar-1';
    private $test_sidebar_one_id = 'test-sidebar-one';
    private $test_sidebar_two_id = 'test-sidebar-two';
    private $default_description = 'Add widgets here to appear in your sidebar.';
    private $description = 'some description';

    public function setUp()
    {
        // before
        parent::setUp();

        register_sidebar( [
            'name'          => 'Test Sidebar One',
            'id'            => $this->test_sidebar_one_id,
            'description'   => $this->description,
        ] );

        register_sidebar( [
            'name'          => 'Test Sidebar Two',
            'id'            => $this->test_sidebar_two_id,
            'description'   => $this->description,
        ] );
    }

    public function tearDown()
    {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    // tests
    public function testSidebarConnectionQuery()
    {
        $query = '
		{
		  sidebars {
		    edges {
		      node {
                sidebarId
                description
		      }
		    }
		    nodes {
		      sidebarId
		    }
		  }
		}
        ';
        
        $actual = do_graphql_request( $query );

        $expected = [
            'data' => [
                'sidebars' => [
                    'edges' => [
                        [ 'node' => [
                            'sidebarId'     => $this->default_id,
                            'description'   => $this->default_description,
                        ] ],
                        [ 'node' => [
                            'sidebarId'     => $this->test_sidebar_one_id,
                            'description'   => $this->description,
                        ] ],
                        [ 'node' => [
                            'sidebarId'     => $this->test_sidebar_two_id,
                            'description'   => $this->description,
                        ] ],
                    ],
                    'nodes' => [ 
                        [ 'sidebarId' => $this->default_id ],
                        [ 'sidebarId' => $this->test_sidebar_one_id ],
                        [ 'sidebarId' => $this->test_sidebar_two_id ],
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

}