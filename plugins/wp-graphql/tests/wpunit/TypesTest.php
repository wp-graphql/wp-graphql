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
	 * @throws \Exception
	 */
	public function testRegisterDuplicateFieldShouldShowDebugMessage() {

		register_graphql_type(
			'ExampleType',
			[
				'fields' => [
					'example' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'example',
			[
				'type' => 'ExampleType',
			]
		);

		register_graphql_field(
			'ExampleType',
			'example',
			[
				'description' => 'Duplicate field, should throw exception',
			]
		);

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
		$this->assertQuerySuccessful(
			$response,
			[
				$this->expectedField( 'example.example', self::IS_NULL ),
			]
		);
		$this->assertNotEmpty( $this->lodashGet( $response, 'extensions.debug' ) );
	}

	/**
	 * This registers a field without a type defined, and asserts that
	 * an exception is being thrown.
	 *
	 * @throws \Exception
	 */
	public function testRegisterFieldWithoutTypeShouldShowDebugMessage() {

		register_graphql_field(
			'RootQuery',
			'newFieldWithoutTypeDefined',
			[
				'description' => 'Field without type, should throw exception',
			]
		);

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
		$this->assertQuerySuccessful(
			$response,
			[
				$this->expectedField( 'posts.nodes', self::NOT_NULL ),
			]
		);

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
	 * @throws \Exception
	 */
	public function testTypeRegistryGetTypes() {

		/**
		 * Register a custom type to make sure new types registered
		 * show in the get_types() method
		 */
		register_graphql_type(
			'MyCustomType',
			[
				'fields'      => [
					'test' => [
						'type' => 'String',
					],
				],
				'description' => 'My Custom Type',
			]
		);

		add_action(
			'graphql_register_types',
			function ( \WPGraphQL\Registry\TypeRegistry $type_registry ) {
				$type = $type_registry->get_type( 'mycustomtype' );
				$this->assertEquals( 'MyCustomType', $type->name );
				$this->assertEquals( 'My Custom Type', $type->description );
			}
		);

		// Invoke the shema and type registry actions.
		$schema = \WPGraphQL::get_schema();
	}

	/**
	 * Test filtering listOf and nonNull fields onto a Type
	 *
	 * @throws \Exception
	 */
	public function testListOf() {

		/**
		 * Filter fields onto the User object
		 */
		add_filter(
			'graphql_user_fields',
			static function ( $fields, $object, \WPGraphQL\Registry\TypeRegistry $type_registry ) {

				$fields['testNonNullString'] = [
					'type'    => $type_registry->non_null( $type_registry->get_type( 'String' ) ),
					'resolve' => static function () {
						return 'string';
					},
				];

				$fields['testNonNullStringTwo'] = [
					'type'    => $type_registry->non_null( 'String' ),
					'resolve' => static function () {
						return 'string';
					},
				];

				$fields['testListOfString'] = [
					'type'    => $type_registry->list_of( $type_registry->get_type( 'String' ) ),
					'resolve' => static function () {
						return [ 'string' ];
					},
				];

				$fields['testListOfStringTwo'] = [
					'type'    => $type_registry->list_of( 'String' ),
					'resolve' => static function () {
						return [ 'string' ];
					},
				];

				$fields['testListOfNonNullString'] = [
					'type'    => $type_registry->list_of( $type_registry->non_null( 'String' ) ),
					'resolve' => static function () {
						return [ 'string' ];
					},
				];

				$fields['testNonNullListOfString'] = [
					'type'    => $type_registry->non_null( $type_registry->list_of( 'String' ) ),
					'resolve' => static function () {
						return [ 'string' ];
					},
				];

				return $fields;
			},
			10,
			3
		);

		$user_id = $this->factory()->user->create(
			[
				'user_login' => 'test' . uniqid(),
				'user_email' => 'test' . uniqid() . '@example.com',
				'role'       => 'administrator',
			]
		);

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
	 * @throws \Exception
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1882
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1883
	 */
	public function testRegisterCustomConnection() {

		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_type(
					'TestCustomType',
					[
						'fields' => [
							'test' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_connection(
					[
						'fromType'      => 'RootQuery',
						'toType'        => 'TestCustomType',
						'fromFieldName' => 'customTestConnection',
						'resolve'       => static function () {
							return null;
						},
					]
				);
			}
		);

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
		$this->assertQuerySuccessful(
			$response,
			[
				$this->expectedField( 'customTestConnection.nodes', self::IS_NULL ),
			]
		);
	}


	// A regular query shouldn't have a duplicate type debug message
	public function testQueryDoesntHaveDuplicateTypesDebugMessage() {
		$actual = graphql(
			[
				'query' => '{posts{nodes{id}}}',
			]
		);

		// There should be no debug messages by default
		$this->assertTrue( isset( $actual['extensions']['debug'] ) );
		$this->assertEmpty( $actual['extensions']['debug'] );
	}

	public function testRegisterDuplicateTypesOutputsDebugMessage() {

		// register duplicate types
		register_graphql_object_type(
			'NewType',
			[
				'fields' => [
					'one' => [
						'type' => 'String',
					],
				],
			]
		);
		register_graphql_object_type(
			'NewType',
			[
				'fields' => [
					'two' => [
						'type' => 'String',
					],
				],
			]
		);

		$actual = graphql(
			[
				'query' => '{posts{nodes{id}}}',
			]
		);

		codecept_debug( $actual );

		// There should be no debug messages by default
		$this->assertTrue( isset( $actual['extensions']['debug'] ), 'query has debug in the extensions payload' );
		$this->assertNotEmpty( $actual['extensions']['debug'], 'query has a debug message' );
		$this->assertNotFalse( strpos( $actual['extensions']['debug'][0]['message'], 'duplicate' ), 'debug message contains the word duplicate' );

		// clear the schema
		// $this->clearSchema();
		//
		// register duplicate types
		// register_graphql_object_type( 'NewType', [
		// 'fields' => [
		// 'one' => [
		// 'type' => 'String'
		// ]
		// ]
		// ]);
		// register_graphql_object_type( 'NewType', [
		// 'fields' => [
		// 'two' => [
		// 'type' => 'String'
		// ]
		// ]
		// ]);
		//
		// query again
		// $actual = graphql([
		// 'query' => '{posts{nodes{id}}}'
		// ]);
		//
		// codecept_debug( $actual );
		//
		// There should be a debug message now!
		// $this->assertTrue( isset( $actual['extensions']['debug'] ) );
		// $this->assertNotEmpty( $actual['extensions']['debug'] );
	}

	/**
	 * Test that incompatible interface field overrides still throw DUPLICATE_FIELD error.
	 *
	 * This test verifies that when trying to override an interface field with a type
	 * that does NOT implement the interface, it should still throw a DUPLICATE_FIELD error.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3096
	 *
	 * @throws \Exception
	 */
	public function testIncompatibleInterfaceFieldOverrideStillErrors() {
		add_action(
			'graphql_register_types',
			function () {
				// Register an interface with a field
				register_graphql_interface_type(
					'TestAnotherSeoInterface',
					[
						'fields' => [
							'seo' => [
								'type'        => 'TestAnotherSeoInterface',
								'description' => 'SEO interface field',
							],
						],
					]
				);

				// Register an object type that does NOT implement the interface
				register_graphql_object_type(
					'TestIncompatibleSeo',
					[
						'fields' => [
							'title' => [
								'type' => 'String',
							],
						],
					]
				);

				// Create an interface that adds 'seo' field to ContentNodes
				register_graphql_interface_type(
					'TestNodeWithAnotherSeo',
					[
						'interfaces' => [ 'ContentNode' ],
						'fields'     => [
							'seo' => [
								'type'        => 'TestAnotherSeoInterface',
								'description' => 'SEO interface field',
							],
						],
					]
				);

				// Make Post implement the interface
				add_filter(
					'graphql_type_interfaces',
					function ( $interfaces, $config ) {
						if ( isset( $config['name'] ) && $config['name'] === 'Post' ) {
							$interfaces[] = 'TestNodeWithAnotherSeo';
						}
						return $interfaces;
					},
					10,
					2
				);

				// Try to override Post.seo with TestIncompatibleSeo type
				// This should FAIL since TestIncompatibleSeo does NOT implement TestAnotherSeoInterface
				register_graphql_field(
					'Post',
					'seo',
					[
						'type'        => 'TestIncompatibleSeo',
						'description' => 'Incompatible SEO data',
					]
				);
			}
		);

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

		// Should have DUPLICATE_FIELD error since the override is incompatible
		$this->assertNotEmpty( $response['extensions']['debug'] ?? [] );
		$debug_types = wp_list_pluck( $response['extensions']['debug'], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should have DUPLICATE_FIELD error when overriding with incompatible type' );
	}

	/**
	 * Test that overriding an interface field on the same type doesn't cause recursion.
	 *
	 * This test verifies the fix for infinite recursion when overriding an interface field
	 * on the same type that's currently being loaded (e.g., overriding Post.field with type Post).
	 *
	 * This test matches the exact scenario from issue #3540.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3540
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideOnSameTypeNoRecursion() {
		add_action(
			'graphql_register_types',
			static function () {
				// The graphql interface whose fields we'll narrow.
				register_graphql_interface_type(
					'NodeWithMyCustomContentNode',
					[
						'fields' => [
							'childFieldOverloaded' => [
								'type'        => 'ContentNode',
								'description' => __( 'This will be narrowed with graphql_register_field()', 'my-text-domain' ),
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
							'childFieldFiltered'   => [
								'type'        => 'ContentNode',
								'description' => __( 'This will be narrowed with the filter directly', 'my-text-domain' ),
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to all ContentNodes types.
				register_graphql_interfaces_to_types( 'NodeWithMyCustomContentNode', [ 'ContentNode' ] );

				// Narrow down the type for Post Objects using `register_graphql_field()`
				// This previously caused infinite recursion, but should now work
				register_graphql_field(
					'Post',
					'childFieldOverloaded',
					[
						'type' => 'Post',
					]
				);

				// Use the `graphql_$type_name_fields` filter instead
				add_filter(
					'graphql_post_fields',
					static function ( array $fields ) {
						if ( isset( $fields['childFieldFiltered'] ) ) {
							$fields['childFieldFiltered']['type'] = 'Post';
						}

						return $fields;
					},
					10,
					2
				);
			}
		);

		// Create a post to query
		$post_id = $this->factory()->post->create(
			[
				'post_title' => 'Test Post for Recursion',
			]
		);

		// Schema should build without recursion errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without recursion' );

		// Query should work - test both fields
		$query = '
			query {
				posts {
					nodes {
						id
						childFieldOverloaded {
							... on Post {
								title
							}
						}
						childFieldFiltered {
							... on Post {
								title
							}
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have recursion errors or DUPLICATE_FIELD errors
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed without recursion errors' );
		$this->assertArrayHasKey( 'data', $response );

		// Verify no DUPLICATE_FIELD errors
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error when overriding with same type' );
	}

	/**
	 * Test that overriding interface fields works with multiple interfaces.
	 *
	 * This tests the scenario where a type implements multiple interfaces and
	 * overrides fields from both interfaces.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithMultipleInterfaces() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register first interface
				register_graphql_interface_type(
					'TestInterfaceOne',
					[
						'fields' => [
							'fieldOne' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface one',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register second interface
				register_graphql_interface_type(
					'TestInterfaceTwo',
					[
						'fields' => [
							'fieldTwo' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface two',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register both interfaces to ContentNode types
				register_graphql_interfaces_to_types( 'TestInterfaceOne', [ 'ContentNode' ] );
				register_graphql_interfaces_to_types( 'TestInterfaceTwo', [ 'ContentNode' ] );

				// Override both fields on Post with type Post
				register_graphql_field(
					'Post',
					'fieldOne',
					[
						'type' => 'Post',
					]
				);

				register_graphql_field(
					'Post',
					'fieldTwo',
					[
						'type' => 'Post',
					]
				);
			}
		);

		// Create a post to query
		$post_id = $this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		// Schema should build without recursion errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without recursion' );

		// Query should work
		$query = '
			query {
				posts {
					nodes {
						id
						fieldOne {
							... on Post {
								title
							}
						}
						fieldTwo {
							... on Post {
								title
							}
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have recursion errors or DUPLICATE_FIELD errors
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed without recursion errors' );
		$this->assertArrayHasKey( 'data', $response );

		// Verify no DUPLICATE_FIELD errors
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error when overriding multiple interface fields' );
	}

	/**
	 * Test that non-interface field duplicates still throw DUPLICATE_FIELD error.
	 *
	 * This test verifies that when trying to override a field that is NOT from an interface,
	 * it should still throw a DUPLICATE_FIELD error (existing behavior should be preserved).
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3096
	 *
	 * @throws \Exception
	 */
	public function testNonInterfaceFieldDuplicateStillErrors() {
		add_action(
			'graphql_register_types',
			function () {
				// Register a type with a direct field (not from interface)
				register_graphql_object_type(
					'TestTypeWithDirectField',
					[
						'fields' => [
							'customField' => [
								'type'        => 'String',
								'description' => 'Direct field on type',
							],
						],
					]
				);

				// Try to register the same field again - should error
				register_graphql_field(
					'TestTypeWithDirectField',
					'customField',
					[
						'type'        => 'String',
						'description' => 'Duplicate field',
					]
				);
			}
		);

		// Register the type on RootQuery so we can query it and trigger schema building
		register_graphql_field(
			'RootQuery',
			'testTypeWithDirectField',
			[
				'type'    => 'TestTypeWithDirectField',
				'resolve' => function () {
					return [ 'customField' => 'test' ];
				},
			]
		);

		$query = '
			query {
				testTypeWithDirectField {
					customField
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should have DUPLICATE_FIELD error since it's not an interface override scenario
		$this->assertNotEmpty( $response['extensions']['debug'] ?? [] );
		$debug_types = wp_list_pluck( $response['extensions']['debug'], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should have DUPLICATE_FIELD error for non-interface field duplicates' );
	}

	/**
	 * Test that interface field override works with Page type (not just Post).
	 *
	 * This verifies the fix works across different ContentNode types.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideOnPageType() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface with a field that returns ContentNode
				register_graphql_interface_type(
					'TestNodeWithPageField',
					[
						'fields' => [
							'pageField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to all ContentNode types
				register_graphql_interfaces_to_types( 'TestNodeWithPageField', [ 'ContentNode' ] );

				// Override the field on Page with type Page (same type)
				register_graphql_field(
					'Page',
					'pageField',
					[
						'type' => 'Page',
					]
				);
			}
		);

		// Create a page to query
		$page_id = $this->factory()->post->create(
			[
				'post_type'  => 'page',
				'post_title' => 'Test Page',
			]
		);

		// Schema should build without recursion errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without recursion' );

		// Query should work
		$query = '
			query {
				pages {
					nodes {
						id
						pageField {
							... on Page {
								title
							}
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have recursion errors or DUPLICATE_FIELD errors
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed without recursion errors' );
		$this->assertArrayHasKey( 'data', $response );

		// Verify no DUPLICATE_FIELD errors
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error when overriding Page field with same type' );

		// Verify the field actually returns the narrowed type
		$this->assertNotEmpty( $response['data']['pages']['nodes'] );
		$this->assertArrayHasKey( 'pageField', $response['data']['pages']['nodes'][0] );
	}

	/**
	 * Test that interface field override works with custom post types.
	 *
	 * This tests a realistic scenario where a plugin registers a custom post type
	 * and wants to narrow an interface field to that specific type.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithCustomPostType() {
		// Register a custom post type
		register_post_type(
			'test_cpt_override',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestCpt',
				'graphql_plural_name' => 'TestCpts',
				'public'              => true,
			]
		);

		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface with a field that returns ContentNode
				register_graphql_interface_type(
					'TestNodeWithCptField',
					[
						'fields' => [
							'cptField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to all ContentNode types
				register_graphql_interfaces_to_types( 'TestNodeWithCptField', [ 'ContentNode' ] );

				// Override the field on TestCpt with type TestCpt (same type)
				register_graphql_field(
					'TestCpt',
					'cptField',
					[
						'type' => 'TestCpt',
					]
				);
			}
		);

		// Create a custom post type entry
		$cpt_id = $this->factory()->post->create(
			[
				'post_type'  => 'test_cpt_override',
				'post_title' => 'Test CPT',
			]
		);

		// Schema should build without recursion errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without recursion' );

		// Query should work
		$query = '
			query {
				testCpts {
					nodes {
						id
						cptField {
							... on TestCpt {
								title
							}
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have recursion errors or DUPLICATE_FIELD errors
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed without recursion errors' );
		$this->assertArrayHasKey( 'data', $response );

		// Verify no DUPLICATE_FIELD errors
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error when overriding custom post type field with same type' );

		// Cleanup
		unregister_post_type( 'test_cpt_override' );
		$this->clearSchema();
	}

	/**
	 * Test that the filter approach works independently (without register_graphql_field).
	 *
	 * This verifies that using the graphql_$type_name_fields filter alone works correctly.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithFilterOnly() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface with a field that returns ContentNode
				register_graphql_interface_type(
					'TestNodeWithFilterField',
					[
						'fields' => [
							'filterField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to all ContentNode types
				register_graphql_interfaces_to_types( 'TestNodeWithFilterField', [ 'ContentNode' ] );
			}
		);

		// Use only the filter approach (no register_graphql_field)
		add_filter(
			'graphql_post_fields',
			static function ( array $fields ) {
				if ( isset( $fields['filterField'] ) ) {
					$fields['filterField']['type'] = 'Post';
				}

				return $fields;
			},
			10,
			2
		);

		// Create a post to query
		$post_id = $this->factory()->post->create(
			[
				'post_title' => 'Test Post for Filter',
			]
		);

		// Schema should build without recursion errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without recursion' );

		// Query should work
		$query = '
			query {
				posts {
					nodes {
						id
						filterField {
							... on Post {
								title
							}
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have recursion errors or DUPLICATE_FIELD errors
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed without recursion errors' );
		$this->assertArrayHasKey( 'data', $response );

		// Verify no DUPLICATE_FIELD errors
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error when overriding with filter only' );
	}

}
