<?php

class SidebarQueriesTest extends \Codeception\TestCase\WPTestCase
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
    public function testSidebarQuery() {
        // Create Test Sidebar
        $sidebar_name = 'Test Sidebar';
        $sidebar_id = 'test-sidebar';

		register_sidebar(
            array (
                'name'          => $sidebar_name,
                'id'            => $sidebar_id,
                'before_widget' => '',
                'after_widget'  => ''
            )
        );

        $relay_id = \GraphQLRelay\Relay::toGlobalId( 'sidebar', $sidebar_id );

		$query = '
            query sidebarQuery($id: ID!) {
                sidebar(id: $id) {
                    id
                    name
                    sidebarId
                    beforeWidget
                    afterWidget
                }
            }
        ';
        
        $variables = [
            'id' =>  $relay_id,
        ];

        $actual = do_graphql_request( $query, 'sidebarQuery', $variables );
        
        $expected = [
            'data' => [
                'sidebar' => [
                    'id' => $relay_id,
                    'name' => $sidebar_name,
                    'sidebarId' => $sidebar_id,
                    'beforeWidget' => '',
                    'afterWidget' => '',
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