<?php

class InterfaceTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		WPGraphQL::clear_schema();
		parent::setUp();

	}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * This tests that an interface can be registered, and that Types implementing them will inherit
	 * the interface fields, but that Types can override resolvers
	 *
	 *
	 * @throws Exception
	 */
	public function testObjectTypeInheritsInterfaceFields() {

		$test = [
			'id' => 'TestId',
			'testInt' => 3,
			'testString' => 'Test',
			'interfaceOnlyField' => 'InterfaceValue'
		];

		/**
		 * Register an Interface
		 */
		register_graphql_interface_type( 'TestInterface', [
			'fields' => [
				// This field is registered in the interface, but not on the Type. We assert that
				// we can still query for it against the type. This tests that Types can
				// share fields and a default resolver can be implemented at the Interface level
				'interfaceOnlyField' => [
					'type' => 'String',
					'resolve' => function() use ( $test ) {
						return $test['interfaceOnlyField'];
					}
				],
				'testString' => [
					'type' => 'String',
				]
			],
		]);

		/**
		 * Register
		 */
		register_graphql_object_type( 'MyTestType', [
			'interfaces' => [ 'Node', 'TestInterface' ],
			'fields' => [
				// Here we define JUST a resolve function for the ID field. The Type is inherited
				// from the Node interface that we've implemented. This tests to ensure that
				// fields can be inherited by interfaces, but that Types can override the
				// resolver as needed.
				'id' => [
					'resolve' => function() use ( $test ) {
						return $test['id'];
					}
				],
				'testInt' => [
					'type' => 'Int',
					'resolve' => function() use ( $test ) {
						return $test['testInt'];
					}
				],
				'testString' => [
					'resolve' => function() use ( $test ) {
						return $test['testString'];
					}
				]
			],
		]);

		register_graphql_field( 'RootQuery', 'tester', [
			'type' => 'MyTestType',
			'resolve' => function() {
				return true;
			}
		] );


		$query = '
		{
		  tester {
            id
            testInt
            testString
            interfaceOnlyField
           }
		}
		';

		$actual = graphql([
			'query' => $query,
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $test['id'], $actual['data']['tester']['id'] );
		$this->assertEquals( $test['testInt'], $actual['data']['tester']['testInt'] );
		$this->assertEquals( $test['testString'], $actual['data']['tester']['testString'] );
		$this->assertEquals( $test['interfaceOnlyField'], $actual['data']['tester']['interfaceOnlyField'] );

	}

}
