<?php

class InterfaceTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * This tests that an interface can be registered, and that Types implementing them will inherit
	 * the interface fields, but that Types can override resolvers
	 *
	 * @throws Exception
	 */
	public function testObjectTypeInheritsInterfaceFields() {

		$test = [
			'id'                 => 'TestId',
			'testInt'            => 3,
			'testString'         => 'Test',
			'interfaceOnlyField' => 'InterfaceValue',
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
					'type'    => 'String',
					'resolve' => function () use ( $test ) {
						return $test['interfaceOnlyField'];
					},
				],
				'testString'         => [
					'type' => 'String',
				],
			],
		] );

		/**
		 * Register
		 */
		register_graphql_object_type( 'MyTestType', [
			'interfaces' => [ 'Node', 'TestInterface' ],
			'fields'     => [
				// Here we define JUST a resolve function for the ID field. The Type is inherited
				// from the Node interface that we've implemented. This tests to ensure that
				// fields can be inherited by interfaces, but that Types can override the
				// resolver as needed.
				'id'         => [
					'resolve' => function () use ( $test ) {
						return $test['id'];
					},
				],
				'testInt'    => [
					'type'    => 'Int',
					'resolve' => function () use ( $test ) {
						return $test['testInt'];
					},
				],
				'testString' => [
					'resolve' => function () use ( $test ) {
						return $test['testString'];
					},
				],
			],
		] );

		register_graphql_field( 'RootQuery', 'tester', [
			'type'    => 'MyTestType',
			'resolve' => function () use ( $test ) {
				return $test;
			},
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

		$actual = graphql( [
			'query' => $query,
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $test['id'], $actual['data']['tester']['id'] );
		$this->assertEquals( $test['testInt'], $actual['data']['tester']['testInt'] );
		$this->assertEquals( $test['testString'], $actual['data']['tester']['testString'] );
		$this->assertEquals( $test['interfaceOnlyField'], $actual['data']['tester']['interfaceOnlyField'] );

	}

	// Validate schema.
	public function testSchemaIsValid() {
		try {
			$request = new \WPGraphQL\Request();
			$request->schema->assertValid();

			// Assert true upon success.
			$this->assertTrue( true );
		} catch ( \GraphQL\Error\InvariantViolation $e ) {
			// use --debug flag to view.
			codecept_debug( $e->getMessage() );

			// Fail upon throwing
			$this->assertTrue( false );
		}
	}

	public function testInterfaceCanImplementInterface() {

		register_graphql_interface_type( 'TestInterfaceOne', [
			'fields' => [
				'one' => [
					'type' => 'String',
				],
			],
		]);

		register_graphql_interface_type( 'TestInterfaceTwo', [
			'interfaces' => [ 'TestInterfaceOne' ],
			'fields'     => [
				'two' => [
					'type' => 'String',
				],
			],
		]);

		register_graphql_interface_type( 'TestInterfaceThree', [
			'interfaces' => [ 'TestInterfaceTwo' ],
			'fields'     => [
				'three' => [
					'type' => 'String',
				],
			],
		]);

		register_graphql_object_type( 'TestTypeWithInterfaces', [
			'interfaces' => [ 'TestInterfaceThree' ],
			'fields'     => [
				'four' => [
					'type' => 'String',
				],
			],
		]);

		register_graphql_field( 'RootQuery', 'testTypeWithInterfaces', [
			'type'    => 'TestTypeWithInterfaces',
			'resolve' => function () {
				return [
					'one'   => 'one value',
					'two'   => 'two value',
					'three' => 'three value',
					'four'  => 'four value',
				];
			},
		] );

		// Test that the schema is valid with
		// the Interfaces registered to implement each other
		$this->testSchemaIsValid();

		$query = '
		{
		  testTypeWithInterfaces {
		    ...One
		    ...Two
		    ...Three
		    four
		  }
		}
		
		fragment One on TestInterfaceOne {
		  one
		}
		
		fragment Two on TestInterfaceTwo {
		  one
		  two
		}
		
		fragment Three on TestInterfaceThree {
		  one
		  two
		  three
		}
		';

		$actual = graphql([
			'query' => $query,
		]);

		codecept_debug( $actual );

		$expected = [
			'testTypeWithInterfaces' => [
				'one'   => 'one value',
				'two'   => 'two value',
				'three' => 'three value',
				'four'  => 'four value',
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data'] );

		$query = '
		query GetType($name:String!){
		  __type(name: $name) {
		    name
		    interfaces {
		      name
		    }
		    fields {
		      name
		    }
		  }
		}
		';

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'name' => 'TestInterfaceTwo',
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'TestInterfaceTwo', $actual['data']['__type']['name'] );

		$interfaces = wp_list_pluck( $actual['data']['__type']['interfaces'], 'name' );

		codecept_debug( $interfaces );

		$this->assertTrue( in_array( 'TestInterfaceOne', $interfaces ) );

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'name' => 'TestInterfaceThree',
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'TestInterfaceThree', $actual['data']['__type']['name'] );

		$interfaces = wp_list_pluck( $actual['data']['__type']['interfaces'], 'name' );

		codecept_debug( $interfaces );

		$this->assertTrue( in_array( 'TestInterfaceOne', $interfaces ) );
		$this->assertTrue( in_array( 'TestInterfaceTwo', $interfaces ) );

		$fields = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		codecept_debug( $fields );

		$this->assertTrue( in_array( 'one', $fields ) );
		$this->assertTrue( in_array( 'two', $fields ) );

	}

	/**
	 * This test registers InterfaceTwo, which implements InterfaceOne, then registers an ObjectType which
	 * implements InterfaceTwo, then asserts that the object type implements both InterfaceOne and InterfaceTwo in the Schema
	 *
	 * @throws Exception
	 */
	public function testObjectImplementingInterfaceWhichImplementsAnotherInterfaceHasBothInterfacesImplemented() {

		register_graphql_interface_type( 'TestInterfaceOne', [
			'fields' => [
				'one' => [
					'type'        => 'String',
					'description' => 'one',
				],
			],
		]);

		register_graphql_interface_type( 'TestInterfaceTwo', [
			'interfaces' => [ 'TestInterfaceOne' ],
			'fields'     => [
				'two' => [
					'type'        => 'String',
					'description' => 'two',
				],
			],
		]);

		register_graphql_object_type( 'TestTypeWithInterfaces', [
			'interfaces' => [ 'TestInterfaceTwo' ],
			'fields'     => [
				'three' => [
					'type'        => 'String',
					'description' => 'three',
				],
			],
		]);

		$query = '
		query GetType($name:String!){
		  __type(name: $name) {
		    kind
		    name
		    interfaces {
		      name
		    }
		    fields {
		      name
		    }
		  }
		}
		';

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'name' => 'TestTypeWithInterfaces',
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'TestTypeWithInterfaces', $actual['data']['__type']['name'] );

		$interfaces = wp_list_pluck( $actual['data']['__type']['interfaces'], 'name' );

		codecept_debug( $interfaces );

		$this->assertTrue( in_array( 'TestInterfaceOne', $interfaces ) );
		$this->assertTrue( in_array( 'TestInterfaceTwo', $interfaces ) );

		$fields = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		codecept_debug( $fields );

		$this->assertTrue( in_array( 'one', $fields ) );
		$this->assertTrue( in_array( 'two', $fields ) );
		$this->assertTrue( in_array( 'three', $fields ) );
	}

	public function testObjectTypeThatImplementsNodeInterfaceHasIdField() {

		register_graphql_object_type( 'TestNodType', [
			'interfaces' => [ 'Node' ],
			'fields'     => [
				'test' => [
					'type'        => 'String',
					'description' => 'test',
				],
			],
		]);

		$query = '
		query GetType($name:String!){
		  __type(name: $name) {
		    kind
		    name
		    interfaces {
		      name
		    }
		    fields {
		      name
		    }
		  }
		}
		';

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'name' => 'TestNodType',
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'TestNodType', $actual['data']['__type']['name'] );

		$interfaces = wp_list_pluck( $actual['data']['__type']['interfaces'], 'name' );

		codecept_debug( $interfaces );

		$this->assertTrue( in_array( 'Node', $interfaces ) );

		$fields = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		codecept_debug( $fields );

		$this->assertTrue( in_array( 'id', $fields ) );
		$this->assertTrue( in_array( 'test', $fields ) );
	}

	public function testInterfaceImplementingItselfDoesNotCauseInfiniteRecursion() {

		// here we implement the interface on the interface itself (multiple times with different cases).
		// this will cause infinite recursion and a failed test.
		// the fix should prevent the interface from being implemented on itself,
		// even if the code attempts to do it.
		register_graphql_interface_type( 'InterfaceA', [
			'interfaces' => [ 'Node', 'InterfaceA', 'interfaceA', 'interfacea' ],
			'fields' => [ 'fieldA' => [ 'type' => 'String' ] ],
			'resolveType' => function() {
				return 'Post';
			}
		]);

		register_graphql_interfaces_to_types( [ 'InterfaceA' ], [ 'Post' ] );

		$actual = graphql([
			'query' => '{ posts { nodes { id, title } } }'
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

	}

}
