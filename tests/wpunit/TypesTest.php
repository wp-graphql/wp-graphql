<?php

class TypesTest extends \Codeception\TestCase\WPTestCase
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

	/**
	 * This registers a duplicate field to the Schema (posts already exists on RootQuery)
	 */
    public function register_duplicate_field() {
	    register_graphql_field( 'RootQuery', 'posts', [
		    'description' => 'Duplicate field, should throw exception'
	    ] );
    }

	/**
	 * This registers a field with no Type defined
	 */
    public function register_field_without_type() {
	    register_graphql_field( 'RootQuery', 'newFieldWithoutTypeDefined', [
		    'description' => 'Duplicate field, should throw exception'
	    ] );
    }

    public function register_duplicate_type() {
    	register_graphql_object_type( 'Post', [
    		'description' => 'This is a duplicate Type and should throw exception',
	    ]);
    }

	/**
	 * This tries to deregister a non-existent field
	 */
    public function deregister_non_existent_field() {
    	deregister_graphql_field( 'RootQuery', 'nonExistentFieldThatShouldCauseException' );
    }

	/**
	 * This registers a field that's already been registered, and asserts that
	 * an exception is being thrown.
	 */
	public function testRegisterDuplicateFieldShouldThrowException() {

    	tests_add_filter( 'graphql_register_types', [ $this, 'register_duplicate_field' ] );
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
	    do_graphql_request( '{posts{edges{node{id}}}}' );
	    remove_filter( 'graphql_register_types', [ $this, 'register_duplicate_field' ] );

    }

	/**
	 * This registers a field without a type defined, and asserts that
	 * an exception is being thrown.
	 */
	public function testRegisterFieldWithoutTypeShouldThrowException() {

		tests_add_filter( 'graphql_register_types', [ $this, 'register_field_without_type' ] );
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
		do_graphql_request( '{posts{edges{node{id}}}}' );
		remove_filter( 'graphql_register_types', [ $this, 'register_field_without_type' ] );

	}

	/**
	 * This registers a duplicate Type and should throw an exception.
	 */
	public function testRegisterDuplicateTypeShouldThrowException() {

		tests_add_filter( 'graphql_register_types', [ $this, 'register_duplicate_type' ] );
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
		do_graphql_request( '{posts{edges{node{id}}}}' );
		remove_filter( 'graphql_register_types', [ $this, 'register_duplicate_type' ] );

	}

	/**
	 * This tries to deregister a non-existend field, and asserts that
	 * an exception is being thrown.
	 */
	public function testDeRegisterNonExistentFieldShouldThrowException() {

		tests_add_filter( 'graphql_register_types', [ $this, 'deregister_non_existent_field' ] );
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
		do_graphql_request( '{posts{edges{node{id}}}}' );
		remove_filter( 'graphql_register_types', [ $this, 'deregister_non_existent_field' ] );

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

}
