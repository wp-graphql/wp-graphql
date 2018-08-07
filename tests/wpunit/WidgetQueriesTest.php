<?php

class WidgetQueriesTest extends \Codeception\TestCase\WPTestCase
{

    // tests
    public function testWidgetQuery()
    {
        /**
         * Retrieve default Meta widget
         */
        $widget_id = 'meta-2';
        $widget = \WPGraphQL\Data\DataSource::resolve_widget($widget_id);

        /**
         * use --debug flag to view
         */
        \Codeception\Util\Debug::debug( $widget );

        $relay_id = \GraphQLRelay\Relay::toGlobalId( 'widget', $widget_id );

		$query = '
		query widgetQuery($id: ID!){
			widget( id: $id ) {
                widgetId
                name
                id
                ... on MetaWidget {
                    title
                }
			}
		}
        ';

        $variables = array( 'id' => $relay_id );
        
        $actual = do_graphql_request( $query, 'widgetQuery', $variables );

        $expected = array(
            'data' => array(
                'widget' => array(
                  'widgetId' => 'meta-2',
                  'name' => 'Meta',
                  'id' => $relay_id,
                  'title' => ''
                )
            )
        );

        /**
         * use --debug flag to view
         */
        \Codeception\Util\Debug::debug( $relay_id );

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