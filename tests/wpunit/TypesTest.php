<?php

class TypesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		// before
		parent::setUp();
		$this->clearSchema();
		// your set up methods here
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		parent::tearDown();
	}

	/**
	 * This registers a field that's already been registered, and asserts that
	 * an exception is being thrown.
	 *
	 * @throws Exception
	 */
	public function testRegisterDuplicateFieldShouldShowDebugMessage() {

		register_graphql_type( 'ExampleType', [
			'fields' => [
				'example' => [
					'type' => 'String',
				],
			],
		] );

		register_graphql_field( 'RootQuery', 'example', [
			'type' => 'ExampleType',
		] );

		register_graphql_field( 'ExampleType', 'example', [
			'description' => 'Duplicate field, should throw exception',
		] );

		$query = '
			query {
		 		example {
		   			example
		 		}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		codecept_debug( $response );

		$this->assertEmpty( $this->lodashGet( $response, 'errors' ) );
		$this->assertQuerySuccessful( $response, [
			$this->expectedField( 'example.example', self::IS_NULL ),
		] );
		$this->assertNotEmpty( $this->lodashGet( $response, 'extensions.debug' ) );
	}

	/**
	 * This registers a field without a type defined, and asserts that
	 * an exception is being thrown.
	 *
	 * @throws Exception
	 */
	public function testRegisterFieldWithoutTypeShouldShowDebugMessage() {

		register_graphql_field( 'RootQuery', 'newFieldWithoutTypeDefined', [
			'description' => 'Field without type, should throw exception',
		] );

		$query = '
			query {
				posts {
					nodes {
						id
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertQuerySuccessful( $response, [
			$this->expectedField( 'posts.nodes', self::NOT_NULL ),
		] );

		$messages = wp_list_pluck( $response['extensions']['debug'], 'message' );
		$this->assertTrue( in_array( 'The registered field \'newFieldWithoutTypeDefined\' does not have a Type defined. Make sure to define a type for all fields.', $messages, true ) );

	}

	public function testMapInput() {

		/**
		 * Testing with invalid input
		 */
		$actual = \WPGraphQL\Utils\Utils::map_input( 'string', 'another string' );
		$this->assertEquals( [], $actual );

		/**
		 * Setup some args
		 */
		$map = [
			'stringInput' => 'string_input',
			'intInput'    => 'int_input',
			'boolInput'   => 'bool_input',
			'inputObject' => 'input_object',
		];

		$input_args = [
			'stringInput' => 'value 2',
			'intInput'    => 2,
			'boolInput'   => false,
		];

		$args = [
			'stringInput' => 'value',
			'intInput'    => 1,
			'boolInput'   => true,
			'inputObject' => \WPGraphQL\Utils\Utils::map_input( $input_args, $map ),
		];

		$expected = [
			'string_input' => 'value',
			'int_input'    => 1,
			'bool_input'   => true,
			'input_object' => [
				'string_input' => 'value 2',
				'int_input'    => 2,
				'bool_input'   => false,
			],
		];

		$actual = \WPGraphQL\Utils\Utils::map_input( $args, $map );

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Tests getting a WordPress databaseId from a GraphQL ID type.
	 */
	public function testGetDatabaseIdFromId() {
		$id       = 24;
		$relay_id = \GraphQLRelay\Relay::toGlobalId( 'my-type', (string) $id );

		// test int databaseId
		$actual = \WPGraphQL\Utils\Utils::get_database_id_from_id( $id );
		$this->assertEquals( $id, $actual );

		// test string databaseId
		$actual = \WPGraphQL\Utils\Utils::get_database_id_from_id( (string) $id );
		$this->assertEquals( $id, $actual );

		// test global databaseId
		$actual = \WPGraphQL\Utils\Utils::get_database_id_from_id( $relay_id );
		$this->assertEquals( $id, $actual );

		// test bad string
		$actual = \WPGraphQL\Utils\Utils::get_database_id_from_id( '21notreal12' );
		$this->assertFalse( $actual, 'A bad string should return false.' );

		// test empty databaseId in relay.
		$empty_relay_id = \GraphQLRelay\Relay::toGlobalId( 'my-type', '' );
		$actual         = \WPGraphQL\Utils\Utils::get_database_id_from_id( $empty_relay_id );
		$this->assertFalse( $actual, 'An empty databaseId in a global ID should return false.' );
	}

	/**
	 * Ensure get_types returns types expected to be in the Schema
	 *
	 * @throws Exception
	 */
	public function testTypeRegistryGetTypes() {

		/**
		 * Register a custom type to make sure new types registered
		 * show in the get_types() method
		 */
		register_graphql_type( 'MyCustomType', [
			'fields'      => [
				'test' => [
					'type' => 'String',
				],
			],
			'description' => 'My Custom Type',
		] );

		add_action( 'graphql_register_types', function ( \WPGraphQL\Registry\TypeRegistry $type_registry ) {
			$type = $type_registry->get_type( 'mycustomtype' );
			$this->assertEquals( 'MyCustomType', $type->name );
			$this->assertEquals( 'My Custom Type', $type->description );
		} );

		// Invoke the shema and type registry actions.
		$schema = \WPGraphQL::get_schema();
	}

	/**
	 * Test filtering listOf and nonNull fields onto a Type
	 *
	 * @throws Exception
	 */
	public function testListOf() {

		/**
		 * Filter fields onto the User object
		 */
		add_filter( 'graphql_user_fields', function ( $fields, $object, \WPGraphQL\Registry\TypeRegistry $type_registry ) {

			$fields['testNonNullString'] = [
				'type'    => $type_registry->non_null( $type_registry->get_type( 'String' ) ),
				'resolve' => function () {
					return 'string';
				},
			];

			$fields['testNonNullStringTwo'] = [
				'type'    => $type_registry->non_null( 'String' ),
				'resolve' => function () {
					return 'string';
				},
			];

			$fields['testListOfString'] = [
				'type'    => $type_registry->list_of( $type_registry->get_type( 'String' ) ),
				'resolve' => function () {
					return [ 'string' ];
				},
			];

			$fields['testListOfStringTwo'] = [
				'type'    => $type_registry->list_of( 'String' ),
				'resolve' => function () {
					return [ 'string' ];
				},
			];

			$fields['testListOfNonNullString'] = [
				'type'    => $type_registry->list_of( $type_registry->non_null( 'String' ) ),
				'resolve' => function () {
					return [ 'string' ];
				},
			];

			$fields['testNonNullListOfString'] = [
				'type'    => $type_registry->non_null( $type_registry->list_of( 'String' ) ),
				'resolve' => function () {
					return [ 'string' ];
				},
			];

			return $fields;

		}, 10, 3 );

		$user_id = $this->factory()->user->create( [
			'user_login' => 'test' . uniqid(),
			'user_email' => 'test' . uniqid() . '@example.com',
			'role'       => 'administrator',
		] );

		/**
		 * Allow for the user to be queried
		 */
		wp_set_current_user( $user_id );
		$user_id = $this->toRelayId( 'user', $user_id );

		$query = '
			query GET_USER( $id: ID! ) {
				user( id: $id ) {
					id
					testNonNullString
					testListOfStringTwo
					testListOfString
					testNonNullStringTwo
					testListOfNonNullString
					testNonNullListOfString
				}
			}
		';

		$variables = [ 'id' => $user_id ];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedField( 'user.testNonNullString', 'string' ),
			$this->expectedField( 'user.testNonNullStringTwo', 'string' ),
			$this->expectedField( 'user.testListOfStringTwo', [ 'string' ] ),
			$this->expectedField( 'user.testListOfNonNullString', [ 'string' ] ),
			$this->expectedField( 'user.testNonNullListOfString', [ 'string' ] ),
			$this->expectedField( 'user.testListOfString', [ 'string' ] ),
		];

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertQuerySuccessful( $response, $expected );
	}

	/**
	 * This test ensures that connections registered at `graphql_register_types` action are
	 * respected in the Schema.
	 *
	 * @throws Exception
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1882
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1883
	 */
	public function testRegisterCustomConnection() {

		add_action( 'graphql_register_types', function () {
			register_graphql_type( 'TestCustomType', [
				'fields' => [
					'test' => [
						'type' => 'String',
					],
				],
			]);

			register_graphql_connection([
				'fromType'      => 'RootQuery',
				'toType'        => 'TestCustomType',
				'fromFieldName' => 'customTestConnection',
				'resolve'       => function () {
					return null;
				},
			]);
		});

		$query = '
			query {
				customTestConnection {
					nodes {
						test
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertQuerySuccessful( $response, [
			$this->expectedField( 'customTestConnection.nodes', self::IS_NULL ),
		] );
	}


	// A regular query shouldn't have a duplicate type debug message
	public function testQueryDoesntHaveDuplicateTypesDebugMessage() {
		$actual = graphql([
			'query' => '{posts{nodes{id}}}',
		]);

		// There should be no debug messages by default
		$this->assertTrue( isset( $actual['extensions']['debug'] ) );
		$this->assertEmpty( $actual['extensions']['debug'] );
	}

	public function testRegisterDuplicateTypesOutputsDebugMessage() {

		// register duplicate types
		register_graphql_object_type( 'NewType', [
			'fields' => [
				'one' => [
					'type' => 'String',
				],
			],
		]);
		register_graphql_object_type( 'NewType', [
			'fields' => [
				'two' => [
					'type' => 'String',
				],
			],
		]);

		$actual = graphql([
			'query' => '{posts{nodes{id}}}',
		]);

		codecept_debug( $actual );

		// There should be no debug messages by default
		$this->assertTrue( isset( $actual['extensions']['debug'] ), 'query has debug in the extensions payload' );
		$this->assertNotEmpty( $actual['extensions']['debug'], 'query has a debug message' );
		$this->assertNotFalse( strpos( $actual['extensions']['debug'][0]['message'], 'duplicate' ), 'debug message contains the word duplicate' );

		//      // clear the schema
		//      $this->clearSchema();
		//
		//      // register duplicate types
		//      register_graphql_object_type( 'NewType', [
		//          'fields' => [
		//              'one' => [
		//                  'type' => 'String'
		//              ]
		//          ]
		//      ]);
		//      register_graphql_object_type( 'NewType', [
		//          'fields' => [
		//              'two' => [
		//                  'type' => 'String'
		//              ]
		//          ]
		//      ]);
		//
		//      // query again
		//      $actual = graphql([
		//          'query' => '{posts{nodes{id}}}'
		//      ]);
		//
		//      codecept_debug( $actual );
		//
		//      // There should be a debug message now!
		//      $this->assertTrue( isset( $actual['extensions']['debug'] ) );
		//      $this->assertNotEmpty( $actual['extensions']['debug'] );

	}
}
