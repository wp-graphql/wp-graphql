<?php

class TypesTest extends \Codeception\TestCase\WPTestCase
{

    public function setUp()
    {
        // before
        parent::setUp();

        WPGraphQL::__clear_schema();

        // your set up methods here
    }

    public function tearDown()
    {
        // your tear down methods here

        // then
        parent::tearDown();
    }

	/**
	 * This registers a field that's already been registered, and asserts that
	 * an exception is being thrown.
	 *
	 * @throws Exception
	 */
	public function testRegisterDuplicateFieldShouldThrowException() {

		register_graphql_type( 'ExampleType', [
			'fields' => [
				'example' => [
					'type' => 'String'
				]
			]
		]);

		register_graphql_field( 'RootQuery', 'example', [
			'type' => 'ExampleType'
		] );

		register_graphql_field( 'ExampleType', 'example', [
			'description' => 'Duplicate field, should throw exception'
		] );

		$this->expectException( \GraphQL\Error\InvariantViolation::class );

		$actual = graphql([
			'query' => '
			{
			 example {
			   example
			 }
			}
			'
		]);

    }

	/**
	 * This registers a field without a type defined, and asserts that
	 * an exception is being thrown.
	 *
	 * @throws Exception
	 */
	public function testRegisterFieldWithoutTypeShouldThrowException() {

		register_graphql_field( 'RootQuery', 'newFieldWithoutTypeDefined', [
			'description' => 'Field without type, should throw exception'
		] );

		$this->expectException( \GraphQL\Error\InvariantViolation::class );

		graphql([
			'query' => '{posts { nodes { id } } }'
		]);

	}

	/**
	 * This tries to deregister a non-existend field, and asserts that
	 * an exception is being thrown.
	 *
	 * @throws Exception
	 */
	public function testDeRegisterNonExistentFieldShouldThrowException() {

		deregister_graphql_field( 'RootQuery', 'nonExistentFieldThatShouldCauseException' );

		$this->expectException( \GraphQL\Error\InvariantViolation::class );

		graphql([
			'query' => '{posts { nodes { id } } }'
		]);

	}

	public function testMapInput() {

		/**
		 * Testing with invalid input
		 */
		$actual = \WPGraphQL\Types::map_input( 'string', 'another string' );
		$this->assertEquals( [], $actual );

		/**
		 * Setup some args
		 */
		$map = [
			'stringInput' => 'string_input',
			'intInput' => 'int_input',
			'boolInput' => 'bool_input',
			'inputObject' => 'input_object',
		];

		$input_args = [
			'stringInput' => 'value 2',
			'intInput' => 2,
			'boolInput' => false,
		];

		$args = [
			'stringInput' => 'value',
			'intInput' => 1,
			'boolInput' => true,
			'inputObject' => \WPGraphQL\Types::map_input( $input_args, $map ),
		];

		$expected = [
			'string_input' => 'value',
			'int_input' => 1,
			'bool_input' => true,
			'input_object' => [
				'string_input' => 'value 2',
				'int_input' => 2,
				'bool_input' => false,
			],
		];

		$actual = \WPGraphQL\Types::map_input( $args, $map );

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Ensure get_types returns types expected to be in the Schema
	 * @throws Exception
	 */
	public function testTypeRegistryGetTypes() {

		/**
		 * Register a custom type to make sure new types registered
		 * show in the get_types() method
		 */
		register_graphql_type( 'MyCustomType', [
			'fields' => [
				'test' => [
					'type' => 'String'
				]
			]
		] );

		add_action( 'graphql_register_types', function( \WPGraphQL\Registry\TypeRegistry $type_registry ) {
			$types = $type_registry->get_types();
			codecept_debug( array_keys( $types ) );
			$this->assertArrayHasKey( 'mycustomtype', $types );
			$this->assertArrayHasKey( 'string', $types );
			$this->assertArrayHasKey( 'post', $types );
		} );

		/**
		 * Execute a GraphQL Request to instantiate the Schema
		 */
		$actual = graphql( ['query' => '{posts{nodes{id}}}'] );

	}

}
