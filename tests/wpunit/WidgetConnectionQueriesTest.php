<?php

class WidgetConnectionQueriesTest extends \Codeception\TestCase\WPTestCase
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
    public function testWidgetConnectionQuery()
    {
        /**
         * Retrieve default Meta widget
         */
        $widget_base = 'meta';
        
		$query = '
            query widgetConnectionQuery($base: String!){
                widgets( where: { basename: $base } ) {
                    nodes {
                        basename
                        ... on MetaWidget {
                            title
                        }
                    }
                }
            }
        ';

        $variables = array( 'base' => $widget_base );
        
        $actual = do_graphql_request( $query, 'widgetConnectionQuery', $variables );

        $expected = [
            'data' => [
                'widgets' => [
                    'nodes' => [
                        [
                            'basename' => 'meta',
                            'title' => '',
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


}