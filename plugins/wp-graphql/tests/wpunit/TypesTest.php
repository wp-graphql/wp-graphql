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

		// Invoke the schema and type registry actions.
		\WPGraphQL::get_schema();
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
			static function () {
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
					static function ( $interfaces, $config ) {
						if ( isset( $config['name'] ) && 'Post' === $config['name'] ) {
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
								'description' => __( 'This will be narrowed with register_graphql_field()', 'wp-graphql' ),
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
							'childFieldFiltered'   => [
								'type'        => 'ContentNode',
								'description' => __( 'This will be narrowed with the filter directly', 'wp-graphql' ),
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
		$this->factory()->post->create(
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
	 * Test that interface field override works with wrapped list/non-null interface types.
	 *
	 * @throws \Exception
	 */
	public function testCompatibleInterfaceFieldOverrideWithWrappedListInterfaceType() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_interface_type(
					'TestChoiceInterface',
					[
						'fields' => [
							'text' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_object_type(
					'TestRadioChoice',
					[
						'interfaces' => [ 'TestChoiceInterface' ],
						'fields'     => [
							'value' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_interface_type(
					'TestNodeWithChoices',
					[
						'interfaces' => [ 'Node' ],
						'fields'     => [
							'choices' => [
								'type'        => [
									'list_of' => [
										'non_null' => 'TestChoiceInterface',
									],
								],
								'description' => '[Interface] Choices from the interface',
								'resolve'     => static function () {
									return [
										[
											'text'  => 'Interface choice',
											'value' => 'interface-choice',
										],
									];
								},
							],
						],
					]
				);

				register_graphql_interfaces_to_types( 'TestNodeWithChoices', [ 'ContentNode' ] );

				register_graphql_field(
					'Post',
					'choices',
					[
						'type'        => [
							'list_of' => [
								'non_null' => 'TestRadioChoice',
							],
						],
						'description' => '[Override] Post choices narrowed to TestRadioChoice',
						'resolve'     => static function () {
							return [
								[
									'text'  => 'Override choice',
									'value' => 'override-choice',
								],
							];
						},
					]
				);
			}
		);

		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		$query = '
			query {
				posts {
					nodes {
						choices {
							__typename
							... on TestRadioChoice {
								text
								value
							}
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response, 'Should not have errors when overriding wrapped list/non-null interface field with implementing object type' );
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error for wrapped list/non-null compatible override' );
		$this->assertEquals( 'TestRadioChoice', $response['data']['posts']['nodes'][0]['choices'][0]['__typename'] );
		$this->assertEquals( 'Override choice', $response['data']['posts']['nodes'][0]['choices'][0]['text'] );
		$this->assertEquals( 'override-choice', $response['data']['posts']['nodes'][0]['choices'][0]['value'] );

		$query = '
			query {
				__type(name: "Post") {
					fields(includeDeprecated:false) {
						name
						description
						type {
							kind
							name
							ofType {
								kind
								name
								ofType {
									kind
									name
								}
							}
						}
					}
				}
			}
		';

		$response      = $this->graphql( compact( 'query' ) );
		$choices_field = null;

		foreach ( $response['data']['__type']['fields'] as $field ) {
			if ( 'choices' === $field['name'] ) {
				$choices_field = $field;
				break;
			}
		}

		$this->assertNotNull( $choices_field, 'Post.choices field should exist' );
		$this->assertEquals( 'LIST', $choices_field['type']['kind'] );
		$this->assertEquals( 'NON_NULL', $choices_field['type']['ofType']['kind'] );
		$this->assertEquals( 'OBJECT', $choices_field['type']['ofType']['ofType']['kind'] );
		$this->assertEquals( 'TestRadioChoice', $choices_field['type']['ofType']['ofType']['name'] );
		$this->assertStringContainsString( '[Override]', $choices_field['description'] );
	}

	/**
	 * Test that interface field override works with list_of type modifiers.
	 *
	 * This mirrors a real downstream plugin use case where the interface field is a list
	 * and a concrete object type narrows the field while preserving interface compatibility.
	 *
	 * @throws \Exception
	 */
	public function testCompatibleInterfaceFieldOverrideWithListOfInterface() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register a base interface for choices with a resolver that returns a default value.
				register_graphql_interface_type(
					'ChoiceWithText',
					[
						'fields' => [
							'text' => [
								'type'        => 'String',
								'description' => '[Interface] The display text for this choice',
								'resolve'     => static fn ( $source ) => $source['text'] ?? '[Interface Default] No text provided',
							],
						],
					]
				);

				// Register a secondary interface with its own field and resolver.
				register_graphql_interface_type(
					'ChoiceWithValue',
					[
						'fields' => [
							'value' => [
								'type'        => 'String',
								'description' => '[Interface] The value for this choice',
								'resolve'     => static fn () => '[Interface Default]',
							],
						],
					]
				);

				// Register a concrete type that implements both interfaces.
				register_graphql_object_type(
					'TestRadioChoice',
					[
						'interfaces' => [ 'ChoiceWithText', 'ChoiceWithValue' ],
						'fields'     => [
							'value' => [
								'description' => '[Override] Override value field from ChoiceWithValue interface',
								'resolve'     => static fn ( $source ) => '[Override Resolver] Value for ' . ( $source['text'] ?? 'unknown choice' ),
							],
						],
					]
				);

				// Create an interface that defines a choices field with a default resolver.
				register_graphql_interface_type(
					'TestNodeWithChoices',
					[
						'interfaces' => [ 'Node' ],
						'fields'     => [
							'choices' => [
								'type'        => [ 'list_of' => 'ChoiceWithText' ],
								'description' => '[Interface] Default choices from TestNodeWithChoices interface',
								'resolve'     => static fn () => [
									[ 'text' => '[Interface Resolver] Default from TestNodeWithChoices' ],
								],
							],
						],
					]
				);

				// Make the interface available on ContentNode.
				register_graphql_interfaces_to_types( 'TestNodeWithChoices', [ 'ContentNode' ] );

				// Override Post.choices with narrowed type and new description/resolver.
				register_graphql_field(
					'Post',
					'choices',
					[
						'type'        => [ 'list_of' => 'TestRadioChoice' ],
						'description' => '[Override] Post-specific radio choices with narrowed type [TestRadioChoice]',
						'resolve'     => static function () {
							return [
								[
									'text'  => '[Override Resolver] Choice #1 from Post.choices',
									'value' => 'choice1',
								],
								[
									'text' => '[Override Resolver] Choice #2 from Post.choices',
								],
							];
						},
					]
				);
			}
		);

		// Create a post to query.
		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		$query = '
			query {
				posts {
					nodes {
						choices {
							__typename
							text
							value
						}
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have errors when overriding list_of interface with list_of implementing type.
		$this->assertArrayNotHasKey( 'errors', $response, 'Should not have errors when overriding list_of interface with list_of implementing type' );

		// Should not have DUPLICATE_FIELD debug message.
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not have DUPLICATE_FIELD error when overriding list_of interface with compatible type' );

		// Verify the override resolver is used (not the interface resolver).
		$this->assertNotEmpty( $response['data']['posts']['nodes'] );
		$choices = $response['data']['posts']['nodes'][0]['choices'];
		$this->assertCount( 2, $choices );
		$this->assertEquals( 'TestRadioChoice', $choices[0]['__typename'], 'Field type should be narrowed to TestRadioChoice' );
		$this->assertEquals( '[Override Resolver] Choice #1 from Post.choices', $choices[0]['text'], 'Should use override resolver for text field' );
		$this->assertEquals( '[Override Resolver] Value for [Override Resolver] Choice #1 from Post.choices', $choices[0]['value'], 'Should use override resolver for value field (using text as source)' );
		$this->assertEquals( 'TestRadioChoice', $choices[1]['__typename'], 'Field type should be narrowed to TestRadioChoice' );
		$this->assertEquals( '[Override Resolver] Choice #2 from Post.choices', $choices[1]['text'], 'Should use override resolver for text field' );
		$this->assertEquals( '[Override Resolver] Value for [Override Resolver] Choice #2 from Post.choices', $choices[1]['value'], 'Should use override resolver for value field (using text as source)' );

		// Introspection query to verify field type and description.
		$query = '
			query {
				__type(name: "Post") {
					fields(includeDeprecated:false) {
						name
						description
						type {
							kind
							name
							ofType {
								kind
								name
								ofType {
									kind
									name
								}
							}
						}
					}
				}
			}
		';

		$response      = $this->graphql( compact( 'query' ) );
		$fields        = $response['data']['__type']['fields'];
		$choices_field = null;
		foreach ( $fields as $field ) {
			if ( 'choices' === $field['name'] ) {
				$choices_field = $field;
				break;
			}
		}

		$this->assertNotNull( $choices_field, 'Post.choices field should exist' );
		$this->assertEquals( 'LIST', $choices_field['type']['kind'], 'Field type should be a List' );
		$this->assertEquals( 'TestRadioChoice', $choices_field['type']['ofType']['name'], 'List item type should be TestRadioChoice (narrowed from ChoiceWithText)' );
		$this->assertEquals( 'OBJECT', $choices_field['type']['ofType']['kind'], 'List item should be an OBJECT type (not INTERFACE)' );
		$this->assertStringContainsString( '[Override]', $choices_field['description'], 'Description should be from override, not interface' );
		$this->assertStringContainsString( 'TestRadioChoice', $choices_field['description'], 'Description should mention the narrowed type TestRadioChoice' );
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
		$this->factory()->post->create(
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
			static function () {
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
				'resolve' => static function () {
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
		$this->factory()->post->create(
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
		$this->factory()->post->create(
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
		$this->factory()->post->create(
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

	/**
	 * Test that duplicate connection fields with same toType and connectionTypeName are allowed.
	 *
	 * This tests the scenario where a connection field is registered multiple times
	 * with the same toType and connectionTypeName, which should be allowed.
	 *
	 * @throws \Exception
	 */
	public function testDuplicateConnectionFieldWithSameToTypeAndConnectionTypeNameIsAllowed() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register a custom type
				register_graphql_object_type(
					'TestConnectionType',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Register first connection
				register_graphql_connection(
					[
						'fromType'           => 'RootQuery',
						'toType'             => 'TestConnectionType',
						'fromFieldName'      => 'testConnection',
						'connectionTypeName' => 'TestConnection',
						'resolve'            => static function () {
							return null;
						},
					]
				);

				// Register duplicate connection with same toType and connectionTypeName
				// This should be allowed (no DUPLICATE_FIELD error)
				register_graphql_connection(
					[
						'fromType'           => 'RootQuery',
						'toType'             => 'TestConnectionType',
						'fromFieldName'      => 'testConnection',
						'connectionTypeName' => 'TestConnection',
						'resolve'            => static function () {
							return null;
						},
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

		// Should not have DUPLICATE_FIELD error for connection fields with same toType and connectionTypeName
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should allow duplicate connection fields with same toType and connectionTypeName' );
	}

	/**
	 * Test that duplicate connection fields with different toType should error.
	 *
	 * This tests the scenario where a connection field is registered multiple times
	 * with different toType values, which should show a DUPLICATE_FIELD error.
	 *
	 * @throws \Exception
	 */
	public function testDuplicateConnectionFieldWithDifferentToTypeShouldError() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register first connection to Post
				register_graphql_connection(
					[
						'fromType'      => 'RootQuery',
						'toType'        => 'Post',
						'fromFieldName' => 'testConnection',
						'resolve'       => static function () {
							return null;
						},
					]
				);

				// Register duplicate connection with different toType (Page instead of Post)
				// This should show DUPLICATE_FIELD error
				register_graphql_connection(
					[
						'fromType'      => 'RootQuery',
						'toType'        => 'Page',
						'fromFieldName' => 'testConnection',
						'resolve'       => static function () {
							return null;
						},
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

		// Should have DUPLICATE_FIELD error for connection fields with different toType
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should show DUPLICATE_FIELD error when connection fields have different toType' );
	}

	/**
	 * Test that duplicate connection fields with different connectionTypeName should error.
	 *
	 * This tests the scenario where a connection field is registered multiple times
	 * with different connectionTypeName values, which should show a DUPLICATE_FIELD error.
	 *
	 * @throws \Exception
	 */
	public function testDuplicateConnectionFieldWithDifferentConnectionTypeNameShouldError() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register first connection with connectionTypeName
				register_graphql_connection(
					[
						'fromType'           => 'RootQuery',
						'toType'             => 'Post',
						'fromFieldName'      => 'testConnection',
						'connectionTypeName' => 'FirstConnection',
						'resolve'            => static function () {
							return null;
						},
					]
				);

				// Register duplicate connection with different connectionTypeName
				// This should show DUPLICATE_FIELD error
				register_graphql_connection(
					[
						'fromType'           => 'RootQuery',
						'toType'             => 'Post',
						'fromFieldName'      => 'testConnection',
						'connectionTypeName' => 'SecondConnection',
						'resolve'            => static function () {
							return null;
						},
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

		// Should have DUPLICATE_FIELD error for connection fields with different connectionTypeName
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should show DUPLICATE_FIELD error when connection fields have different connectionTypeName' );
	}

	/**
	 * Test that field name starting with number after formatting shows INVALID_FIELD_NAME error.
	 *
	 * This tests the scenario where a field name, after formatting, starts with a number.
	 *
	 * @throws \Exception
	 */
	public function testFieldNameStartingWithNumberAfterFormattingShowsError() {
		// Register a field that, after formatting, would start with a number
		register_graphql_field(
			'RootQuery',
			'123_formatted_field',
			[
				'type' => 'String',
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

		// Should have INVALID_FIELD_NAME error
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'INVALID_FIELD_NAME', $debug_types, 'Should show INVALID_FIELD_NAME error when field name starts with number after formatting' );
	}

	/**
	 * Test that field name with allowFieldUnderscores preserves underscores.
	 *
	 * This tests that when allowFieldUnderscores is true, underscores are preserved in the field name.
	 *
	 * @throws \Exception
	 */
	public function testFieldNameWithAllowFieldUnderscoresPreservesUnderscores() {
		$expected_value = uniqid( 'test', true );

		register_graphql_field(
			'RootQuery',
			'field_with_underscores',
			[
				'type'                  => 'String',
				'resolve'               => static function () use ( $expected_value ) {
					return $expected_value;
				},
				'allowFieldUnderscores' => true,
			]
		);

		$query = '
			query {
				field_with_underscores
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( $expected_value, $response['data']['field_with_underscores'] );
	}

	/**
	 * Test that field name without allowFieldUnderscores formats underscores to camelCase.
	 *
	 * This tests that when allowFieldUnderscores is false (default), underscores are formatted to camelCase.
	 *
	 * @throws \Exception
	 */
	public function testFieldNameWithoutAllowFieldUnderscoresFormatsToCamelCase() {
		$expected_value = uniqid( 'test', true );

		register_graphql_field(
			'RootQuery',
			'field_with_underscores',
			[
				'type'    => 'String',
				'resolve' => static function () use ( $expected_value ) {
					return $expected_value;
				},
			]
		);

		$query = '
			query {
				fieldWithUnderscores
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( $expected_value, $response['data']['fieldWithUnderscores'] );
	}

	/**
	 * Test that interface field override works when new type is registered but not loaded.
	 *
	 * This tests the scenario where a type is registered but not yet loaded when
	 * checking compatibility for interface field override.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWhenTypeIsRegisteredButNotLoaded() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface with a field
				register_graphql_interface_type(
					'TestInterfaceForNotLoaded',
					[
						'fields' => [
							'notLoadedField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to ContentNode types
				register_graphql_interfaces_to_types( 'TestInterfaceForNotLoaded', [ 'ContentNode' ] );

				// Register a custom type that will implement the interface
				register_graphql_object_type(
					'TestNotLoadedType',
					[
						'interfaces' => [ 'TestInterfaceForNotLoaded' ],
						'fields'     => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Override the field on Post with the new type (which may not be loaded yet)
				register_graphql_field(
					'Post',
					'notLoadedField',
					[
						'type' => 'TestNotLoadedType',
					]
				);
			}
		);

		// Create a post to query
		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		// Schema should build without errors
		\WPGraphQL::get_schema();

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

		// Should not have errors (the override should be allowed even if type wasn't loaded during check)
		$this->assertArrayNotHasKey( 'errors', $response );
	}

	/**
	 * Test that interface field override with incompatible type on different type shows error.
	 *
	 * This tests the scenario where we override an interface field on one type
	 * with an incompatible type (not implementing the interface).
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithIncompatibleTypeOnDifferentType() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface with a field
				register_graphql_interface_type(
					'TestIncompatibleInterface',
					[
						'fields' => [
							'incompatibleField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to ContentNode types
				register_graphql_interfaces_to_types( 'TestIncompatibleInterface', [ 'ContentNode' ] );

				// Register a type that does NOT implement the interface
				register_graphql_object_type(
					'TestIncompatibleType',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Override the field on Post with incompatible type
				register_graphql_field(
					'Post',
					'incompatibleField',
					[
						'type' => 'TestIncompatibleType',
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
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should show DUPLICATE_FIELD error when overriding with incompatible type on different type' );
	}

	/**
	 * Test that field registration with array type modifiers works.
	 *
	 * This tests registering a field with array type modifiers (non_null, list_of).
	 *
	 * @throws \Exception
	 */
	public function testRegisterFieldWithArrayTypeModifiers() {
		register_graphql_field(
			'RootQuery',
			'testNonNullString',
			[
				'type'    => [
					'non_null' => 'String',
				],
				'resolve' => static function () {
					return 'test';
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'testListOfString',
			[
				'type'    => [
					'list_of' => 'String',
				],
				'resolve' => static function () {
					return [ 'test1', 'test2' ];
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'testNonNullListOfString',
			[
				'type'    => [
					'non_null' => [
						'list_of' => 'String',
					],
				],
				'resolve' => static function () {
					return [ 'test1', 'test2' ];
				},
			]
		);

		$query = '
			query {
				testNonNullString
				testListOfString
				testNonNullListOfString
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( 'test', $response['data']['testNonNullString'] );
		$this->assertEquals( [ 'test1', 'test2' ], $response['data']['testListOfString'] );
		$this->assertEquals( [ 'test1', 'test2' ], $response['data']['testNonNullListOfString'] );
	}

	/**
	 * Test that field registration with callable existing field type works.
	 *
	 * This tests the scenario where an existing field has a callable type.
	 *
	 * @throws \Exception
	 */
	public function testRegisterFieldWithCallableExistingFieldType() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface with a field that has a callable type
				register_graphql_interface_type(
					'TestCallableInterface',
					[
						'fields' => [
							'callableField' => [
								'type'        => static function () {
									return \WPGraphQL::get_type_registry()->get_type( 'ContentNode' );
								},
								'description' => 'Field with callable type',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to ContentNode types
				register_graphql_interfaces_to_types( 'TestCallableInterface', [ 'ContentNode' ] );

				// Override the field on Post with type Post
				register_graphql_field(
					'Post',
					'callableField',
					[
						'type' => 'Post',
					]
				);
			}
		);

		// Create a post to query
		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		// Schema should build without errors
		\WPGraphQL::get_schema();

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

		// Should not have DUPLICATE_FIELD error
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should allow override when existing field has callable type' );
	}

	/**
	 * Test that connection field registration with allowFieldUnderscores works.
	 *
	 * This tests that connection fields can use allowFieldUnderscores.
	 *
	 * @throws \Exception
	 */
	public function testConnectionFieldWithAllowFieldUnderscores() {
		register_graphql_connection(
			[
				'fromType'              => 'RootQuery',
				'toType'                => 'Post',
				'fromFieldName'         => 'connection_with_underscores',
				'allowFieldUnderscores' => true,
				'resolve'               => static function () {
					return null;
				},
			]
		);

		$query = '
			query {
				connection_with_underscores {
					nodes {
						id
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have errors and field name should preserve underscores
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertArrayHasKey( 'connection_with_underscores', $response['data'] );
	}


	/**
	 * Test interface field override when existing field type is a string (not callable).
	 *
	 * This tests the scenario where the existing field has a string type instead of a callable,
	 * which requires checking if the string type name is an interface.
	 *
	 * This is possible because:
	 * 1. Fields are added via filters (graphql_{$type_name}_fields)
	 * 2. The duplicate check happens INSIDE a filter callback (before prepare_fields is called)
	 * 3. If a field is added directly in a filter with a string type, it will still be a string
	 *    when the duplicate check runs (because prepare_fields converts strings to callables AFTER filters)
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithStringExistingFieldType() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface first (this must be registered before we check it)
				register_graphql_interface_type(
					'TestStringInterface',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Register a type that implements the interface
				register_graphql_object_type(
					'TestStringType',
					[
						'interfaces' => [ 'TestStringInterface' ],
						'fields'     => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Force the interface to be loaded by referencing it in a query field
				// This ensures it's in $this->types when we check it
				register_graphql_field(
					'RootQuery',
					'testStringInterfaceField',
					[
						'type'    => 'TestStringInterface',
						'resolve' => static function () {
							return [ 'id' => 'test' ];
						},
					]
				);
			}
		);

		// Add a field with string type (not callable) to RootQuery using a filter
		// Priority 5 ensures it runs before register_graphql_field's filter (priority 10)
		// This field will still be a string when the duplicate check happens
		add_filter(
			'graphql_rootquery_fields',
			static function ( array $fields ) {
				// Add a field with string type (not callable) that references the interface
				// This simulates a field that was added directly with a string type
				// and hasn't been processed by prepare_field yet
				$fields['stringField'] = [
					'type'        => 'TestStringInterface', // String type, not callable
					'description' => 'Field with string type',
					'resolve'     => static fn ( $source ) => [ 'id' => 'test' ],
				];

				return $fields;
			},
			5
		);

		// Now override the field on RootQuery with a compatible type
		// This should trigger the string type check path because the existing field
		// is still a string when the duplicate check runs (before prepare_fields converts it)
		register_graphql_field(
			'RootQuery',
			'stringField',
			[
				'type'    => 'TestStringType',
				'resolve' => static fn ( $source ) => [ 'id' => 'test' ],
			]
		);

		// Force schema to load the interface by querying it first
		$query = '
			query {
				testStringInterfaceField {
					id
				}
			}
		';
		$this->graphql( compact( 'query' ) );

		// Schema should build without errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without errors' );

		// The override should be allowed because TestStringType implements TestStringInterface
		$query = '
			query {
				stringField {
					id
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );

		// Should not have DUPLICATE_FIELD errors (override should be allowed)
		// because TestStringType implements TestStringInterface
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should allow override when existing field type is string and new type implements interface' );
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed' );
	}

	/**
	 * Test loaded-interface resolution path for string existing field types.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithStringExistingFieldTypeWhenInterfaceIsLoaded() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_interface_type(
					'LoadedStringInterface',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_object_type(
					'LoadedStringType',
					[
						'interfaces' => [ 'LoadedStringInterface' ],
						'fields'     => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Preload the interface so resolution uses the loaded-types branch.
				\WPGraphQL::get_type_registry()->get_type( 'LoadedStringInterface' );
			}
		);

		add_filter(
			'graphql_rootquery_fields',
			static function ( array $fields ) {
				$fields['loadedStringField'] = [
					'type'        => 'LoadedStringInterface',
					'description' => 'String existing field type referencing loaded interface',
					'resolve'     => static function () {
						return [ 'id' => 'loaded' ];
					},
				];

				return $fields;
			},
			5
		);

		register_graphql_field(
			'RootQuery',
			'loadedStringField',
			[
				'type'    => 'LoadedStringType',
				'resolve' => static function () {
					return [ 'id' => 'loaded' ];
				},
			]
		);

		$query    = 'query { loadedStringField { id } }';
		$response = $this->graphql( compact( 'query' ) );

		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should allow override when existing field type resolves from loaded interface' );
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed for loaded-interface string type resolution' );
	}

	/**
	 * Test loader-based interface resolution path for string existing field types.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithStringExistingFieldTypeWhenInterfaceUsesLoader() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_interface_type(
					'LoaderStringInterface',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_object_type(
					'LoaderStringType',
					[
						'interfaces' => [ 'LoaderStringInterface' ],
						'fields'     => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);
			}
		);

		add_filter(
			'graphql_rootquery_fields',
			static function ( array $fields ) {
				$fields['loaderStringField'] = [
					'type'        => 'LoaderStringInterface',
					'description' => 'String existing field type referencing loader-backed interface',
					'resolve'     => static function () {
						return [ 'id' => 'loader' ];
					},
				];

				return $fields;
			},
			5
		);

		register_graphql_field(
			'RootQuery',
			'loaderStringField',
			[
				'type'    => 'LoaderStringType',
				'resolve' => static function () {
					return [ 'id' => 'loader' ];
				},
			]
		);

		$query    = 'query { loaderStringField { id } }';
		$response = $this->graphql( compact( 'query' ) );

		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should allow override when existing field type resolves from interface loader' );
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed for loader-backed interface string type resolution' );
	}

	/**
	 * Test string existing type that resolves to a non-interface returns duplicate.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithStringExistingFieldTypeThatResolvesToObject() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_object_type(
					'StringExistingObjectType',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_object_type(
					'StringExistingOverrideObjectType',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);
			}
		);

		add_filter(
			'graphql_rootquery_fields',
			static function ( array $fields ) {
				$fields['stringExistingObjectField'] = [
					'type'        => 'StringExistingObjectType',
					'description' => 'String existing field type referencing object type',
					'resolve'     => static function () {
						return [ 'id' => 'object' ];
					},
				];

				return $fields;
			},
			5
		);

		register_graphql_field(
			'RootQuery',
			'stringExistingObjectField',
			[
				'type'    => 'StringExistingOverrideObjectType',
				'resolve' => static function () {
					return [ 'id' => 'override' ];
				},
			]
		);

		$query    = 'query { stringExistingObjectField { id } }';
		$response = $this->graphql( compact( 'query' ) );

		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not treat non-interface string existing type as interface override' );
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed when existing string type resolves to non-interface object' );
	}

	/**
	 * Test empty array existing type path returns duplicate field.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithEmptyArrayExistingFieldType() {
		add_filter(
			'graphql_rootquery_fields',
			static function ( array $fields ) {
				$fields['emptyArrayTypeField'] = [
					'type'        => [],
					'description' => 'Invalid empty array existing type',
					'resolve'     => static function () {
						return null;
					},
				];

				return $fields;
			},
			5
		);

		register_graphql_field(
			'RootQuery',
			'emptyArrayTypeField',
			[
				'type'    => 'String',
				'resolve' => static function () {
					return 'value';
				},
			]
		);

		$query    = 'query { emptyArrayTypeField }';
		$response = $this->graphql( compact( 'query' ) );

		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should not treat empty array existing type as compatible interface override' );
		$this->assertArrayNotHasKey( 'errors', $response, 'Query should succeed when existing field type array resolves to empty type name' );
	}

	/**
	 * Test interface field override with different type when type IS loaded.
	 *
	 * This tests the scenario where we override an interface field on one type
	 * with a different type that is already loaded (not just registered).
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithDifferentTypeWhenLoaded() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface
				register_graphql_interface_type(
					'TestLoadedInterface',
					[
						'fields' => [
							'loadedField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to ContentNode types
				register_graphql_interfaces_to_types( 'TestLoadedInterface', [ 'ContentNode' ] );

				// Register a type that implements the interface (this will be loaded before we override)
				register_graphql_object_type(
					'TestLoadedType',
					[
						'interfaces' => [ 'TestLoadedInterface' ],
						'fields'     => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Force the type to be loaded by querying it
				register_graphql_field(
					'RootQuery',
					'testLoadedType',
					[
						'type'    => 'TestLoadedType',
						'resolve' => static function () {
							return [ 'id' => 'test' ];
						},
					]
				);
			}
		);

		// Force schema to load the TestLoadedType by querying it first
		$query = '
			query {
				testLoadedType {
					id
				}
			}
		';
		$this->graphql( compact( 'query' ) );

		// Now override the field on Post with the loaded type
		add_action(
			'graphql_register_types_late',
			static function () {
				register_graphql_field(
					'Post',
					'loadedField',
					[
						'type' => 'TestLoadedType',
					]
				);
			}
		);

		// Create a post to query
		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		// Schema should build without errors
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build without errors' );

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

		// Should not have DUPLICATE_FIELD errors (override should be allowed)
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertNotContains( 'DUPLICATE_FIELD', $debug_types, 'Should allow override when new type is loaded and implements interface' );
	}

	/**
	 * Test that overriding an interface field with an unregistered type still errors.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithUnregisteredNewType() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_interface_type(
					'TestUnregisteredTypeInterface',
					[
						'fields' => [
							'unregisteredTypeField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				register_graphql_interfaces_to_types( 'TestUnregisteredTypeInterface', [ 'ContentNode' ] );

				register_graphql_field(
					'Post',
					'unregisteredTypeField',
					[
						'type' => 'DefinitelyMissingType',
					]
				);
			}
		);

		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		$query    = 'query { posts { nodes { id } } }';
		$response = $this->graphql( compact( 'query' ) );

		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should show DUPLICATE_FIELD when override type is not registered' );
	}

	/**
	 * Test that overriding an interface field with a non-object registered type errors.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideWithRegisteredNonObjectType() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_interface_type(
					'TestNonObjectOverrideInterface',
					[
						'fields' => [
							'nonObjectOverrideField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				register_graphql_interfaces_to_types( 'TestNonObjectOverrideInterface', [ 'ContentNode' ] );

				register_graphql_enum_type(
					'TestNonObjectOverrideEnum',
					[
						'values' => [
							'ONE' => [
								'value' => 'one',
							],
						],
					]
				);

				register_graphql_field(
					'Post',
					'nonObjectOverrideField',
					[
						'type' => 'TestNonObjectOverrideEnum',
					]
				);
			}
		);

		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		$query    = 'query { posts { nodes { id } } }';
		$response = $this->graphql( compact( 'query' ) );

		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should show DUPLICATE_FIELD when override type is registered but not an object type' );
	}

	/**
	 * Test incompatible interface field override still reports duplicate field.
	 *
	 * This validates the incompatible override path. It does not attempt to force
	 * internal type resolution exceptions.
	 *
	 * @throws \Exception
	 */
	public function testInterfaceFieldOverrideErrorHandling() {
		add_action(
			'graphql_register_types',
			static function () {
				// Register an interface
				register_graphql_interface_type(
					'TestErrorInterface',
					[
						'fields' => [
							'errorField' => [
								'type'        => 'ContentNode',
								'description' => 'Field from interface',
								'resolveType' => static fn ( $source ) => isset( $source->post_type ) ? graphql_format_type_name( $source->post_type ) : null,
								'resolve'     => static fn ( $source ) => $source,
							],
						],
					]
				);

				// Register the interface to ContentNode types
				register_graphql_interfaces_to_types( 'TestErrorInterface', [ 'ContentNode' ] );

				// Register a type that does NOT implement the interface
				register_graphql_object_type(
					'TestErrorType',
					[
						'fields' => [
							'id' => [
								'type' => 'String',
							],
						],
					]
				);

				// Try to override the field with incompatible type.
				register_graphql_field(
					'Post',
					'errorField',
					[
						'type' => 'TestErrorType',
					]
				);
			}
		);

		// Create a post to query
		$this->factory()->post->create(
			[
				'post_title' => 'Test Post',
			]
		);

		// Schema should still build successfully and report debug output.
		$schema = \WPGraphQL::get_schema();
		$this->assertNotNull( $schema, 'Schema should build successfully for incompatible override test' );

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

		// Should have DUPLICATE_FIELD error since the override is incompatible.
		$debug_types = wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
		$this->assertContains( 'DUPLICATE_FIELD', $debug_types, 'Should show DUPLICATE_FIELD error when override is incompatible' );
	}
}
