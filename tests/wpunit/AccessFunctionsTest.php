<?php

class AccessFunctionsTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		// before
		parent::setUp();

		// your set up methods here
	}

	public function tearDown() {
		// your tear down methods here

		// then
		parent::tearDown();
		WPGraphQL::__clear_schema();
	}

	// tests
	public function testMe() {
		$actual   = graphql_format_field_name( 'This is some field name' );
		$expected = 'thisIsSomeFieldName';
		self::assertEquals( $expected, $actual );
	}

	public function testRegisterInputField() {

		/**
		 * Register Test CPT
		 */
		register_post_type( 'test_cpt', [
			"label"               => __( 'Test CPT', 'wp-graphql' ),
			"labels"              => [
				"name"          => __( 'Test CPT', 'wp-graphql' ),
				"singular_name" => __( 'Test CPT', 'wp-graphql' ),
			],
			"description"         => __( 'test-post-type', 'wp-graphql' ),
			"supports"            => [ 'title' ],
			"show_in_graphql"     => true,
			"graphql_single_name" => 'TestCpt',
			"graphql_plural_name" => 'TestCpts',
		] );

		/**
		 * Register a GraphQL Input Field to the connection where args
		 */
		register_graphql_field(
			'RootQueryToTestCptConnectionWhereArgs',
			'testTest',
			[
				'type'        => 'String',
				'description' => 'just testing here'
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			__type( name: "RootQueryToTestCptConnectionWhereArgs" ) { 
				inputFields {
					name
				}
			} 
		}';

		$actual = graphql( [
			'query' => $query,
		] );

		/**
		 * Get an array of names from the inputFields
		 */
		$names = array_column( $actual['data']['__type']['inputFields'], 'name' );

		/**
		 * Assert that `testTest` exists in the $names (the field was properly registered)
		 */
		$this->assertTrue( in_array( 'testTest', $names ) );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToTestCptConnectionWhereArgs', 'testTest' );
		unregister_post_type( 'action_monitor' );
		WPGraphQL::__clear_schema();

	}

	/**
	 * Test to make sure "testInputField" doesn't exist in the Schema already
	 * @throws Exception
	 */
	public function testFilteredInputFieldDoesntExistByDefault() {
		/**
		 * Query the "RootQueryToPostConnectionWhereArgs" Type
		 */
		$query = '
		{
		  __type(name: "RootQueryToPostConnectionWhereArgs") {
		    name
		    kind
		    inputFields {
		      name
		    }
		  }
		}
		';

		$actual = graphql([ 'query' => $query ]);

		codecept_debug( $actual );

		/**
		 * Map the names of the inputFields to be an array so we can properly
		 * assert that the input field is there
		 */
		$field_names = array_map( function( $field ) {
			return $field['name'];
		}, $actual['data']['__type']['inputFields'] );

		codecept_debug( $field_names );

		/**
		 * Assert that there is no `testInputField` on the Type already
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'testInputField', $field_names );
	}

	/**
	 * Test to make sure filtering in "testInputField" properly adds the input to the Schema
	 * @throws Exception
	 */
	public function testFilterInputFields() {

		/**
		 * Query the "RootQueryToPostConnectionWhereArgs" Type
		 */
		$query = '
		{
		  __type(name: "RootQueryToPostConnectionWhereArgs") {
		    name
		    kind
		    inputFields {
		      name
		    }
		  }
		}
		';

		/**
		 * Filter in the "testInputField"
		 */
		add_filter( 'graphql_input_fields', function( $fields, $type_name, $config, $type_registry ) {
			if ( isset( $config['queryClass'] ) && 'WP_Query' === $config['queryClass'] ) {
				$fields['testInputField'] = [
					'type' => 'String'
				];
			}
			return $fields;
		}, 10, 4 );

		$actual = graphql([ 'query' => $query ]);

		codecept_debug( $actual );

		/**
		 * Map the names of the inputFields to be an array so we can properly
		 * assert that the input field is there
		 */
		$field_names = array_map( function( $field ) {
			return $field['name'];
		}, $actual['data']['__type']['inputFields'] );

		codecept_debug( $field_names );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertContains( 'testInputField', $field_names );

	}

}
