<?php

class WidgetConnectionQueriesTest extends \Codeception\TestCase\WPTestCase
{

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

        $expected = array(
            'data' => array(
                'widgets' => array(
                    'nodes' => array(
                        array(
                            'basename' => 'meta',
                            'title' => ''
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