<?php

class WidgetQueriesTest extends \Codeception\TestCase\WPTestCase
{

    // tests
    public function testWidgetQuery()
    {
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
                id
                widgetId
                name
			}
		}
        ';

        $variables = array( 'id' => $relay_id );
        
        $actual = do_graphql_request( $query, 'widgetQuery', $variables );

        $expected = array(
            'id'        => $relay_id,
            'widgetId'  => 'meta-2',
            'name'      => 'Meta'
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