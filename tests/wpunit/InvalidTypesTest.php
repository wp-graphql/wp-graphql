<?php

class InvalidTypesTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		// before
		WPGraphQL::clear_schema();
		parent::setUp();

		// your set up methods here
	}

	public function tearDown() {
		// your tear down methods here

		// then
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * Test that registering a field with an invalid name converts it to a proper name
	 *
	 * @throws Exception
	 */
	public function testRegisterFieldWithInvalidNameThrowsException() {

		register_graphql_field( 'RootQuery', 'my Field Name', [
			'type' => 'String',
			'resolve' => function() {
				return 'test';
			}
		] );

		$actual = graphql([
			'query' => '{myFieldName}'
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'test', $actual['data']['myFieldName']);
	}

	/**
	 * Test that registering a field with an invalid name throws an InvariantViolation
	 *
	 * @throws Exception
	 */
	public function testRegisterTypeWithInvalidNameThrowsException() {

		$config = [
			'fields' => [
				'test' => [
				'type' => 'String',
				],
			]
		];

		register_graphql_object_type( 'My Type Name', $config );
		register_graphql_object_type( 'My_Other_Type_Name', $config );

		$actual = graphql([
			'query' => '
			{
			 MyTypeName: __type(name: "MyTypeName") {
			    name
			    kind
			  }
			 MyOtherTypeName:  __type(name: "MyOtherTypeName") {
			    name
			    kind
			  }
			}
			'
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'MyTypeName', $actual['data']['MyTypeName']['name'] );
		$this->assertEquals( 'OBJECT', $actual['data']['MyTypeName']['kind'] );
		$this->assertEquals( 'MyOtherTypeName', $actual['data']['MyOtherTypeName']['name'] );
		$this->assertEquals( 'OBJECT', $actual['data']['MyOtherTypeName']['kind'] );


	}

}
