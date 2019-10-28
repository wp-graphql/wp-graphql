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

}
