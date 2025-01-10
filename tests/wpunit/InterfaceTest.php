<?php

class InterfaceTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	// Validate schema.
	public function testSchemaIsValid() {
		try {
			$request = new \WPGraphQL\Request();
			$schema  = WPGraphQL::get_schema();
			$schema->assertValid();

			// Assert true upon success.
			$this->assertTrue( true );
		} catch ( \GraphQL\Error\InvariantViolation $e ) {
			// use --debug flag to view.
			codecept_debug( $e->getMessage() );

			// Fail upon throwing
			$this->assertTrue( false );
		}
	}

	/**
	 * This tests that an interface can be registered, and that Types implementing them will inherit
	 * the interface fields, but that Types can override resolvers
	 *
	 * @throws \Exception
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
		register_graphql_interface_type(
			'TestInterface',
			[
				'fields' => [
					// This field is registered in the interface, but not on the Type. We assert that
					// we can still query for it against the type. This tests that Types can
					// share fields and a default resolver can be implemented at the Interface level
					'interfaceOnlyField' => [
						'type'    => 'String',
						'resolve' => static function () use ( $test ) {
							return $test['interfaceOnlyField'];
						},
					],
					'testString'         => [
						'type' => 'String',
					],
				],
			]
		);

		/**
		 * Register
		 */
		register_graphql_object_type(
			'MyTestType',
			[
				'interfaces' => [ 'Node', 'TestInterface' ],
				'fields'     => [
					// Here we define JUST a resolve function for the ID field. The Type is inherited
					// from the Node interface that we've implemented. This tests to ensure that
					// fields can be inherited by interfaces, but that Types can override the
					// resolver as needed.
					'id'         => [
						'resolve' => static function () use ( $test ) {
							return $test['id'];
						},
					],
					'testInt'    => [
						'type'    => 'Int',
						'resolve' => static function () use ( $test ) {
							return $test['testInt'];
						},
					],
					'testString' => [
						'resolve' => static function () use ( $test ) {
							return $test['testString'];
						},
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'tester',
			[
				'type'    => 'MyTestType',
				'resolve' => static function () use ( $test ) {
					return $test;
				},
			]
		);

		$query = 'query {
			tester {
				id
				testInt
				testString
				interfaceOnlyField
			}
		}';

		$actual   = $this->graphql( [ 'query' => $query ] );
		$expected = [
			$this->expectedField( 'tester.id', $test['id'] ),
			$this->expectedField( 'tester.testInt', $test['testInt'] ),
			$this->expectedField( 'tester.testString', $test['testString'] ),
			$this->expectedField( 'tester.interfaceOnlyField', $test['interfaceOnlyField'] ),
		];

		$this->assertQuerySuccessful( $actual, $expected );
	}

	public function testInterfaceCanImplementInterface() {

		register_graphql_interface_type(
			'TestInterfaceOne',
			[
				'fields' => [
					'one' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interface_type(
			'TestInterfaceTwo',
			[
				'interfaces' => [ 'TestInterfaceOne' ],
				'fields'     => [
					'two' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interface_type(
			'TestInterfaceThree',
			[
				'interfaces' => [ 'TestInterfaceTwo' ],
				'fields'     => [
					'three' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_object_type(
			'TestTypeWithInterfaces',
			[
				'interfaces' => [ 'TestInterfaceThree' ],
				'fields'     => [
					'four' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'testTypeWithInterfaces',
			[
				'type'    => 'TestTypeWithInterfaces',
				'resolve' => static function () {
					return [
						'one'   => 'one value',
						'two'   => 'two value',
						'three' => 'three value',
						'four'  => 'four value',
					];
				},
			]
		);

		// Test that the schema is valid with
		// the Interfaces registered to implement each other
		$this->testSchemaIsValid();

		$query = 'fragment One on TestInterfaceOne {
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
		
		query {
			testTypeWithInterfaces {
				...One
				...Two
				...Three
				four
			}
		}';

		$actual   = $this->graphql( [ 'query' => $query ] );
		$expected = [
			$this->expectedObject(
				'testTypeWithInterfaces',
				[
					$this->expectedField( 'one', 'one value' ),
					$this->expectedField( 'two', 'two value' ),
					$this->expectedField( 'three', 'three value' ),
					$this->expectedField( 'four', 'four value' ),
				]
			),
		];
		$this->assertQuerySuccessful( $actual, $expected );

		$query = 'query GetType($name:String!){
			__type(name: $name) {
				name
				interfaces {
					name
				}
				fields {
					name
				}
			}
		}';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'TestInterfaceTwo',
				],
			]
		);

		$expected = [
			$this->expectedField( '__type.name', 'TestInterfaceTwo' ),
			$this->expectedField( '__type.interfaces.#.name', 'TestInterfaceOne' ),
		];
		$this->assertQuerySuccessful( $actual, $expected );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'TestInterfaceThree',
				],
			]
		);

		$expected = [
			$this->expectedField( '__type.name', 'TestInterfaceThree' ),
			$this->expectedField( '__type.interfaces.#.name', 'TestInterfaceOne' ),
			$this->expectedField( '__type.interfaces.#.name', 'TestInterfaceTwo' ),
			$this->expectedField( '__type.fields.#.name', 'one' ),
			$this->expectedField( '__type.fields.#.name', 'two' ),
		];
		$this->assertQuerySuccessful( $actual, $expected );
	}

	/**
	 * This test registers InterfaceTwo, which implements InterfaceOne, then registers an ObjectType which
	 * implements InterfaceTwo, then asserts that the object type implements both InterfaceOne and InterfaceTwo in the Schema
	 *
	 * @throws \Exception
	 */
	public function testObjectImplementingInterfaceWhichImplementsAnotherInterfaceHasBothInterfacesImplemented() {

		register_graphql_interface_type(
			'TestInterfaceOne',
			[
				'fields' => [
					'one' => [
						'type'        => 'String',
						'description' => 'one',
					],
				],
			]
		);

		register_graphql_interface_type(
			'TestInterfaceTwo',
			[
				'interfaces' => [ 'TestInterfaceOne' ],
				'fields'     => [
					'two' => [
						'type'        => 'String',
						'description' => 'two',
					],
				],
			]
		);

		register_graphql_object_type(
			'TestTypeWithInterfaces',
			[
				'interfaces' => [ 'TestInterfaceTwo' ],
				'fields'     => [
					'three' => [
						'type'        => 'String',
						'description' => 'three',
					],
				],
			]
		);

		$query = 'query GetType($name:String!){
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
		}';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'TestTypeWithInterfaces',
				],
			]
		);

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

		register_graphql_object_type(
			'TestNodType',
			[
				'interfaces' => [ 'Node' ],
				'fields'     => [
					'test' => [
						'type'        => 'String',
						'description' => 'test',
					],
				],
			]
		);

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

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'TestNodType',
				],
			]
		);

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
		register_graphql_interface_type(
			'InterfaceA',
			[
				'interfaces'  => [ 'Node', 'InterfaceA', 'interfaceA', 'interfacea' ],
				'fields'      => [ 'fieldA' => [ 'type' => 'String' ] ],
				'resolveType' => static function () {
					return 'Post';
				},
			]
		);

		register_graphql_interfaces_to_types( [ 'InterfaceA' ], [ 'Post' ] );

		$actual = graphql(
			[
				'query' => '{ posts { nodes { id, title } } }',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testArgsOnInterfaceFieldAreAppliedToObjectField() {

		register_graphql_interface_type(
			'InterfaceWithArgs',
			[
				'fields' => [
					'fieldWithArgs' => [
						'type'    => 'String',
						'args'    => [
							'interfaceArg' => [
								'type'         => 'String',
								'defaultValue' => 'parent',
							],
						],
						'resolve' => static function ( $source, $args ) {
							return implode( ' ', array_keys( $args ) );
						},
					],
				],
			]
		);

		register_graphql_interface_type(
			'AnotherInterfaceWithArgs',
			[
				'interfaces' => [ 'InterfaceWithArgs' ],
				'fields'     => [
					'fieldWithArgs' => [
						'type'    => 'String',
						'args'    => [
							'interfaceArg' => [
								'type'         => 'String',
								'defaultValue' => 'another parent',
							],
						],
						'resolve' => static function ( $source, $args ) {
							return implode( ' ', array_keys( $args ) );
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'ObjectTypeImplementingInterfaceWithArgs',
			[
				'interfaces' => [ 'InterfaceWithArgs' ],
				'fields'     => [
					'fieldWithArgs' => [
						'args' => [
							'objectArg' => [
								'type'         => 'String',
								'defaultValue' => 'child',
							],
						],
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_object_type(
			'AnotherObjectTypeImplementingInterfaceWithArgs',
			[
				'interfaces' => [ 'InterfaceWithArgs' ],
				'fields'     => [
					'fieldWithArgs' => [
						'resolve' => static function ( $_, $args ) {
							return implode( ',', array_keys( $args ) );
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'ObjectTypeImplementingAnotherInterfaceWithArgs',
			[
				'interfaces' => [ 'InterfaceWithArgs' , 'AnotherInterfaceWithArgs' ],
				'fields'     => [
					'fieldWithArgs' => [
						'args' => [
							'objectArg' => [
								'type'         => 'String',
								'defaultValue' => 'child',
							],
						],
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_fields(
			'RootQuery',
			[
				'interfaceArgsTest'  => [
					'type'    => 'ObjectTypeImplementingInterfaceWithArgs',
					'resolve' => static function () {
						return true;
					},
				],
				'interfaceArgsTest2' => [
					'type'    => 'AnotherObjectTypeImplementingInterfaceWithArgs',
					'resolve' => static function () {
						return true;
					},
				],
				'interfaceArgsTest3' => [
					'type'    => 'ObjectTypeImplementingAnotherInterfaceWithArgs',
					'resolve' => static function () {
						return true;
					},
				],
			]
		);

		$query = 'query {
			interfaceArgsTest {
				fieldWithArgs
			}
			interfaceArgsTest2 {
				fieldWithArgs
			}
			interfaceArgsTest3 {
				fieldWithArgs
			}
		}';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEmpty( $actual['extensions']['debug'], 'The interface should be implemented with no debug messages.' );

		$expected = [
			$this->expectedField( 'interfaceArgsTest.fieldWithArgs', 'interfaceArg objectArg' ),
			$this->expectedField( 'interfaceArgsTest2.fieldWithArgs', 'interfaceArg' ),
			$this->expectedField( 'interfaceArgsTest3.fieldWithArgs', 'interfaceArg objectArg' ),
		];
		$this->assertQuerySuccessful( $actual, $expected, 'The query should be valid as the Args from the Interface fields should be merged with the args from the object field' );
	}

	public function testInvalidArgsOnInheritedObjectFieldsAreCaptured() {
		register_graphql_interface_type(
			'InterfaceWithArgs',
			[
				'fields' => [
					'fieldWithArgs' => [
						'type'    => 'String',
						'args'    => [
							'interfaceArg' => [ 'type' => 'String' ],
						],
						'resolve' => static function ( $source, $args ) {
							return $args['arg'];
						},
					],
				],

			]
		);

		register_graphql_object_type(
			'BadObjectTypeImplementingInterfaceWithArgs',
			[
				'interfaces' => [ 'InterfaceWithArgs' ],
				'fields'     => [
					'fieldWithArgs' => [
						'args'    => [
							'interfaceArg' => [ 'type' => 'Number' ],
						],
						'type'    => 'String',
						'resolve' => static function () {
							return 'object value';
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'BadObjectTypeImplementingInterfaceWithArgs2',
			[
				'interfaces' => [ 'InterfaceWithArgs' ],
				'fields'     => [
					'fieldWithArgs' => [
						'args'    => [
							'interfaceArg' => [
								'type' => [ 'list_of' => 'Number' ],
							],
						],
						'type'    => 'String',
						'resolve' => static function () {
							return 'object value';
						},
					],
				],
			]
		);

		register_graphql_fields(
			'RootQuery',
			[
				'interfaceArgsTest'  => [
					'type'    => 'BadObjectTypeImplementingInterfaceWithArgs',
					'resolve' => static function () {
						return true;
					},
				],
				'interfaceArgsTest2' => [
					'type'    => 'BadObjectTypeImplementingInterfaceWithArgs2',
					'resolve' => static function () {
						return true;
					},
				],
			]
		);

		$query = 'query {
			interfaceArgsTest {
				fieldWithArgs(interfaceArg: 2)
			}
			interfaceArgsTest2 {
				fieldWithArgs(interfaceArg: [2, 4, 5])
			}
		}';

		$actual = $this->graphql( [ 'query' => $query ] );
		$this->assertResponseIsValid( $actual, 'The query should be valid as the Args from the Interface fields should be merged with the args from the object field' );
		$this->assertNotEmpty( $actual['errors'], 'Invalid field arguments should be flagged' );
		$this->assertEquals( 'String cannot represent a non string value: 2', $actual['errors'][0]['message'] );
		$this->assertEquals( 'String cannot represent a non string value: [2, 4, 5]', $actual['errors'][1]['message'] );

		$this->assertNotEmpty( $actual['extensions']['debug'], 'The interface should be implemented with debug messages.' );
		$this->assertStringStartsWith( 'Interface field argument "BadObjectTypeImplementingInterfaceWithArgs.fieldWithArgs(interfaceArg:)" expected to be of type "String" but got "Number".', $actual['extensions']['debug'][0]['message'] );
		$this->assertStringStartsWith( 'Interface field argument "BadObjectTypeImplementingInterfaceWithArgs2.fieldWithArgs(interfaceArg:)" expected to be of type "String" but got "[Number]".', $actual['extensions']['debug'][1]['message'] );
	}

	public function testInterfaceWithNonNullableArg() {
		register_graphql_interface_type(
			'InterfaceWithNonNullableArg',
			[
				'fields' => [
					'fieldWithNonNullableArg' => [
						'type'    => 'String',
						'args'    => [
							'nonNullableArg'      => [
								'type'         => [ 'non_null' => 'String' ],
								'defaultValue' => 'nonNullableArg',
							],
							'listOfArg'           => [
								'type'         => [ 'list_of' => 'String' ],
								'defaultValue' => [ 'listOfArg', 'listOfArg 2' ],
							],
							'nonNullListOfString' => [
								'type'         => [ 'non_null' => [ 'list_of' => 'String' ] ],
								'defaultValue' => [ 'nonNullListOfString', 'nonNullListOfString 2' ],
							],
							'listOfNonNullString' => [
								'type'         => [ 'list_of' => [ 'non_null' => 'String' ] ],
								'defaultValue' => [ 'listOfNonNullString', 'listOfNonNullString 2' ],
							],
						],
						'resolve' => static function ( $source, $args ) {
							return null;
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'TestTypeWithNonNullableArg',
			[
				'interfaces' => [ 'InterfaceWithNonNullableArg' ],
				'fields'     => [
					'testField' => [
						'type'    => 'String',
						'resolve' => static function () {
							return 'object value';
						},
					],
				],
			]
		);

		register_graphql_fields(
			'RootQuery',
			[
				'testField' => [
					'type'    => 'TestTypeWithNonNullableArg',
					'resolve' => static function () {
						return true;
					},
				],
			]
		);

		$query = 'query {
			testField {
				fieldWithNonNullableArg(
					nonNullableArg: "test" 
					listOfArg: ["test"] 
					nonNullListOfString: ["test"]
					listOfNonNullString: ["test"]
				)
				testField
			}
		}';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEmpty( $actual['extensions']['debug'], 'The interface should be implemented with no debug messages.' );
		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'testField.fieldWithNonNullableArg', self::IS_NULL ) ],
			'The query should be valid as the list of and non null arguments defined on the interface are valid when querying the field that returns the object type'
		);
	}

	public function testDebugMessageNotShownIfInterfaceFieldHasArgs() {
		register_graphql_interface_type(
			'InterfaceFieldWithArgs',
			[
				'fields'      => [
					'fieldWithArgs' => [
						'type'    => 'String',
						'args'    => [
							'interfaceArg' => [
								'type' => 'Boolean',
							],
						],
						'resolve' => static function () {
							return null;
						},
					],
				],
				'resolveType' => static function () {
					return 'TestType';
				},
			]
		);

		// Register an interface that implements the other one, but does not define
		// any arguments for the field.
		register_graphql_interface_type(
			'AnotherInterface',
			[
				'interfaces'  => [ 'InterfaceFieldWithArgs' ],
				'fields'      => [
					// this field does not define any arguments,
					// so it should inherit the arguments from the interface
					'fieldWithArgs' => [
						'type'    => 'String',
						'resolve' => static function () {
							return null;
						},
					],
				],
				'resolveType' => static function () {
					return 'TestType';
				},
			]
		);

		register_graphql_object_type(
			'TestType',
			[
				'interfaces' => [ 'AnotherInterface' ],
				'fields'     => [
					'testField' => [
						'type'    => 'String',
						'resolve' => static function () {
							return 'object value';
						},
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'testField',
			[
				'type'    => 'AnotherInterface',
				'resolve' => static function () {
					return true;
				},
			]
		);

		$query = 'query {
			testField {
				fieldWithArgs(interfaceArg: true )
			}
		}';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEmpty( $actual['extensions']['debug'], 'The interface should be implemented with no debug messages.' );
		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'testField.fieldWithNoArgs', self::IS_NULL ) ],
			'The query should be valid as the interface field has no arguments'
		);
	}
}
