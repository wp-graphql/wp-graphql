<?php

use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;

class ConnectionRegistrationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public $connection_config;

	public function setUp(): void {
		parent::setUp();

		register_graphql_object_type(
			'TestObject',
			[
				'fields' => [
					'id'   => [
						'type' => 'Int',
					],
					'name' => [
						'type' => 'String',
					],
				],
			]
		);

		$this->connection_config = [
			'fromType'      => 'RootQuery',
			'toType'        => 'TestObject',
			'fromFieldName' => 'testConnection',
			'resolve'       => static function ( $source, $args, $context, $info ) {
				$data = [
					[
						'id'   => 1,
						'name' => 'Test 1',
					],
					[
						'id'   => 2,
						'name' => 'Test 2',
					],
					[
						'id'   => 3,
						'name' => 'Test 3',
					],
				];

				// Mock the data being returned from the connection
				return [
					'edges' => array_map(
						static function ( $item ) {
								return [
									'cursor' => base64_encode( 'arrayconnection:' . $item['id'] ),
									'node'   => $item,
								];
						},
						$data
					),
					'nodes' => $data,
				];
			},
		];
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		parent::tearDown();
	}

	public function testRegisteringConnectionsFromTypeRegistrationAddsConnectionsToSchema() {

		register_graphql_object_type(
			'TestTypeWithOneToOneConnection',
			[
				'fields'      => [
					'id' => [
						'type' => 'ID',
					],
				],
				'connections' => [
					'connectedPosts' => [
						'toType' => 'Post',
					],
					'connectedPost'  => [
						'toType'   => 'Post',
						'oneToOne' => true,
					],
				],
			]
		);

		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'TestTypeWithOneToOneConnection',
				'fromFieldName' => 'testTypeConnection',
			]
		);

		$query = '
		{
			testTypeConnection {
				nodes {
					id
					connectedPosts {
						nodes {
							id
						}
					}
					connectedPost {
						node {
							id
						}
					}
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		// Assert that the query above is successful given the registered type and connections
		// But since there's no data for the connection, we can safely assert the response
		// should be null, but with no errors
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'testTypeConnection', self::IS_NULL ),
			]
		);
	}

	/**
	 * See: https://github.com/wp-graphql/wp-graphql/issues/2054
	 */
	public function testRegisteringConnectionWithArgsAllowsArgsToBeUsedInQuery() {

		register_graphql_object_type(
			'Test',
			[
				'fields'      => [
					'test' => [
						'type' => 'String',
					],
				],
				'connections' => [
					'testPostConnection' => [
						'toType'         => 'Post',
						'connectionArgs' => [
							'testInput' => [
								'type' => 'String',
							],
						],
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'test',
			[
				'type' => 'Test',
			]
		);

		$query = '
		query Test($where: TestToPostConnectionWhereArgs) {
			test {
				testPostConnection(where: $where) {
					nodes {
						id
					}
				}
			}
		}
		';

		$variables = [
			'testInput' => 'test',
		];

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'test', self::IS_NULL ),
			]
		);
	}

	public function testRegisterCustomConnectionWithAuth() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_type(
					'TestCustomType',
					[
						'fields' => [
							'test' => [
								'type'    => 'String',
								'auth'    => [
									'errorMessage' => 'Blocked on the field-level!!!',
									'callback'     => static function ( $field, $field_key, $source, $args, $context, $info, $field_resolver ) {
										return ! empty( $source );
									},
								],
								'resolve' => static function ( $source ) {
									return $source;
								},
							],
						],
					]
				);

				register_graphql_connection(
					[
						'fromType'      => 'RootQuery',
						'toType'        => 'TestCustomType',
						'auth'          => [
							'errorMessage' => 'Blocked on the type-level!!!',
							'callback'     => static function ( $field, $field_key, $source, $args, $context, $info, $field_resolver ) {
								return ! empty( $args['first'] );
							},
						],
						'fromFieldName' => 'secretConnection',
						'resolve'       => static function () {
							return [ 'nodes' => [ 'Blah', 'blah', 'blu' ] ];
						},
					]
				);

				register_graphql_connection(
					[
						'fromType'      => 'RootQuery',
						'toType'        => 'TestCustomType',
						'auth'          => [
							'errorMessage' => 'Blocked on the field-level!!!',
							'allowedCaps'  => [ 'administrator' ],
						],
						'fromFieldName' => 'failingAuthConnection',
						'resolve'       => static function () {
							return [ 'nodes' => [ 'test', false, 0 ] ];
						},
					]
				);
			}
		);

		$query = '
			query($first: Int) {
				secretConnection(first: $first) {
					nodes {
						test
					}
				}
			}
		';

		/**
		 * Expect query to fail on type level due to missing "first" arg.
		 */
		$response = $this->graphql( compact( 'query' ) );
		$expected = [
			$this->expectedErrorPath( 'secretConnection' ),
			$this->expectedErrorMessage( 'Blocked on the type-level!!!', self::MESSAGE_EQUALS ),
			$this->expectedField( 'secretConnection', self::IS_NULL ),
		];

		$this->assertQueryError( $response, $expected );

		/**
		 * Expect query to succeed.
		 */
		$variables = [ 'first' => 1 ];
		$response  = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			$this->expectedNode( 'secretConnection.nodes', [ 'test' => 'Blah' ] ),
			$this->expectedNode( 'secretConnection.nodes', [ 'test' => 'blah' ] ),
			$this->expectedNode( 'secretConnection.nodes', [ 'test' => 'blu' ] ),
		];

		$this->assertQuerySuccessful( $response, $expected );

		/**
		 * Expect query to fail on both type/field-level.
		 */
		$query = '
			query {
				failingAuthConnection {
					nodes {
						test
					}
				}
			}
		';

		$response = $this->graphql( compact( 'query' ) );
		$expected = [
			$this->expectedErrorPath( 'failingAuthConnection' ),
			$this->expectedErrorMessage( 'Blocked on the field-level!!!', self::MESSAGE_EQUALS ),
			$this->expectedField( 'failingAuthConnection', self::IS_NULL ),
		];
		$this->assertQueryError( $response, $expected );

		/**
		 * Expect adminstrator to bypass field-level auth due to caps but fail type-level auth for last to nodes because they have falsy root value.
		 */
		\wp_set_current_user( 1 );

		$response = $this->graphql( compact( 'query' ) );
		$expected = [
			$this->expectedField( 'failingAuthConnection.nodes.0.test', 'test' ),
			$this->expectedField( 'failingAuthConnection.nodes.1.test', self::IS_NULL ),
			$this->expectedField( 'failingAuthConnection.nodes.2.test', self::IS_NULL ),
			$this->expectedErrorMessage( 'Blocked on the field-level!!!', self::MESSAGE_EQUALS ),
			$this->expectedErrorPath( 'failingAuthConnection.nodes.1.test' ),
			$this->expectedErrorPath( 'failingAuthConnection.nodes.2.test' ),
		];

		$this->assertQueryError( $response, $expected );
	}

	public function testRegistration(): void {
		register_graphql_connection( $this->connection_config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				testConnection {
					__typename
					edges {
						__typename
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertValidTypes( $actual );
		$this->assertCount( 3, $actual['data']['testConnection']['edges'] );
		$this->assertCount( 3, $actual['data']['testConnection']['nodes'] );
	}

	public function testWithConflictingObjectType() {
		register_graphql_object_type(
			'RootQueryToTestObjectConnection',
			[
				'fields' => [
					'someField' => [
						'type' => 'String',
					],
				],
			]
		);
		register_graphql_connection( $this->connection_config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				testConnection {
					__typename
					edges {
						__typename
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'RootQueryToTestConnectionConnection', $actual['data']['testConnection']['__typename'] );
		$this->assertEquals( 'RootQueryToTestConnectionConnectionEdge', $actual['data']['testConnection']['edges'][0]['__typename'] );
		$this->assertEquals( 'TestObject', $actual['data']['testConnection']['edges'][0]['node']['__typename'] );
		$this->assertEquals( 'TestObject', $actual['data']['testConnection']['nodes'][0]['__typename'] );
		$this->assertCount( 3, $actual['data']['testConnection']['edges'] );
		$this->assertCount( 3, $actual['data']['testConnection']['nodes'] );
	}

	public function testWithConnectionFields(): void {
		$config = array_merge(
			$this->connection_config,
			[
				'connectionFields' => [
					'count' => [
						'type'        => 'Int',
						'description' => 'The number of items in the connection',
						'resolve'     => static function ( $source, $args, $context, $info ) {
							return count( $source['nodes'] );
						},
					],
				],
			]
		);

		register_graphql_connection( $config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				testConnection {
					__typename
					count
					edges {
						__typename
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertValidTypes( $actual );
		$this->assertEquals( 3, $actual['data']['testConnection']['count'] );
	}

	public function testWithConnectionArgs(): void {
		$config = array_merge(
			$this->connection_config,
			[
				'connectionArgs' => [
					'name' => [
						'type'        => 'String',
						'description' => 'Filter the connection based on a field',
					],
				],
				'resolve'        => function ( $source, $args, $context, $info ) {
					$data = [
						[
							'id'   => '1',
							'name' => 'Test 1',
						],
						[
							'id'   => '2',
							'name' => 'Test 2',
						],
						[
							'id'   => '3',
							'name' => 'Test 3',
						],
					];

					if ( ! isset( $args['where']['name'] ) ) {
						$this->fail( 'The connectionArgs should be passed to the resolve function' );
					}

					if ( ! empty( $args['where']['name'] ) ) {
						$data = array_filter(
							$data,
							static function ( $item ) use ( $args ) {
								return $item['name'] === $args['where']['name'];
							}
						);
					}

					return [
						'edges' => array_map(
							static function ( $item ) {
									return [
										'cursor' => base64_encode( 'arrayconnection:' . $item['id'] ),
										'node'   => $item,
									];
							},
							$data
						),
						'nodes' => $data,
					];
				},
			]
		);

		register_graphql_connection( $config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				testConnection( where: { name: "Test 1" } ) {
					__typename
					edges {
						__typename
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertValidTypes( $actual );
		$this->assertCount( 1, $actual['data']['testConnection']['edges'] );
	}

	public function testWithEdgeFields(): void {
		$config = array_merge(
			$this->connection_config,
			[
				'edgeFields' => [
					'isFirst' => [
						'type'        => 'Boolean',
						'description' => 'Is this the first item in the connection',
						'resolve'     => static function ( $source ) {
							return 'arrayconnection:1' === base64_decode( $source['cursor'] );
						},
					],
				],
			]
		);

		register_graphql_connection( $config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				testConnection {
					__typename
					edges {
						__typename
						isFirst
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertValidTypes( $actual );
		$this->assertTrue( $actual['data']['testConnection']['edges'][0]['isFirst'] );
		$this->assertFalse( $actual['data']['testConnection']['edges'][1]['isFirst'] );
	}

	public function testWithConnectionTypeName(): void {
		$config = array_merge(
			$this->connection_config,
			[
				'connectionTypeName' => 'MyTestConnection',
			]
		);

		register_graphql_connection( $config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				testConnection {
					__typename
					edges {
						__typename
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'MyTestConnection', $actual['data']['testConnection']['__typename'] );
		$this->assertEquals( 'MyTestConnectionEdge', $actual['data']['testConnection']['edges'][0]['__typename'] );
	}

	public function testWithConnectionInterfaces(): void {
		$this->markTestIncomplete();
	}

	public function testWithIncludeDefaultInterfacesDisabled(): void {
		$config = array_merge(
			$this->connection_config,
			[
				'includeDefaultInterfaces' => false,
			]
		);

		register_graphql_connection( $config );

		$this->clearSchema();

		$query = '
			query TestConnection {
				__type( name: "RootQueryToTestObjectConnection" ) {
					kind
					interfaces {
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'OBJECT', $actual['data']['__type']['kind'] );
		$this->assertEmpty( $actual['data']['__type']['interfaces'] );

		// Test querying still works.

		$query = '
			query TestConnection {
				testConnection {
					__typename
					edges {
						__typename
						node {
							__typename
							id
							name
						}
					}
					nodes {
						__typename
						id
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertValidTypes( $actual );
	}

	public function testWithQueryClass(): void {
		$this->markTestIncomplete();
	}

	public function testConnectionConfigIsAvailableInResolvers() {

		$expected = uniqid( 'test', true );

		// Pass the $expected value to the connection.
		// We will filter the resolver to do something with the value
		// And assert we have access to it
		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'Post',
				'fromFieldName' => 'connectionWithConfig',
				'testField'     => $expected,
			]
		);

		// Here we filter the resolver and throw an error
		// if the field definition had a value for testField
		add_filter(
			'graphql_resolve_field',
			static function ( $result, $source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info ) {

				if ( ! empty( $info->fieldDefinition->config['testField'] ) ) {
					throw new \GraphQL\Error\UserError( $info->fieldDefinition->config['testField'] );
				}

				return $result;
			},
			10,
			5
		);

		$query = '
		{
		 connectionWithConfig {
			 nodes {
				 __typename
			 }
		 }
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug(
			[
				'$actual' => $actual,
			]
		);

		// Here we're asserting that the $expected value exists in the errors
		// This ensures that the value passed in to the connection config
		// Is indeed accessible in the $info of the resolver
		$this->assertQueryError(
			$actual,
			[
				$this->expectedErrorPath( 'connectionWithConfig' ),
				$this->expectedErrorMessage( $expected, self::MESSAGE_EQUALS ),
				$this->expectedField( 'connectionWithConfig', self::IS_NULL ),
			]
		);
	}


	public function testWithSetQueryClassWhenNotSupported(): void {
		$config = [
			'fromType'      => 'RootQuery',
			'toType'        => 'ContentType',
			'fromFieldName' => 'testConnection',
			'resolve'       => static function ( $source, $args, $context, $info ) {
				$resolver = new ContentTypeConnectionResolver( $source, $args, $context, $info );

				$resolver->set_query_class( 'WP_Query' );

				return $resolver->get_connection();
			},
		];

		register_graphql_connection( $config );

		$query = '
			query {
				testConnection {
					nodes {
						id
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringEndsWith( 'should not use a query class, but is attempting to use the WP_Query query class.', $actual['errors'][0]['extensions']['debugMessage'] );
	}

	public function testWithCustomSetQueryClass(): void {
		require_once __DIR__ . '/../_data/classes/WP_Query_Custom.php';

		$post_ids = $this->factory()->post->create_many( 2 );

		$query = '
			query {
				testConnection {
					nodes {
						__typename
					}
				}
			}
		';

		// Test that empty query class still resolves
		$config = [
			'fromType'           => 'RootQuery',
			'toType'             => 'Post',
			'fromFieldName'      => 'testConnection',
			'connectionTypeName' => 'CustomQueryClassConnection',
			'resolve'            => static function ( $source, $args, $context, $info ) {
				$resolver = new PostObjectConnectionResolver( $source, $args, $context, $info );

				$resolver->set_query_class( WP_Query_Custom::class );

				return $resolver->get_connection();
			},
		];

		register_graphql_connection( $config );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['testConnection']['nodes'] );

		// cleanup
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	public function testWithEmptySetQueryClass(): void {
		$post_ids = $this->factory()->post->create_many( 2 );

		$query = '
			query {
				testConnection {
					nodes {
						__typename
					}
				}
			}
		';

		// Test that empty query class still resolves
		$config = [
			'fromType'           => 'RootQuery',
			'toType'             => 'Post',
			'fromFieldName'      => 'testConnection',
			'connectionTypeName' => 'EmptyQueryClassConnection',
			'resolve'            => static function ( $source, $args, $context, $info ) {
				$resolver = new PostObjectConnectionResolver( $source, $args, $context, $info );

				$resolver->set_query_class( '' );

				return $resolver->get_connection();
			},
		];

		register_graphql_connection( $config );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['testConnection']['nodes'] );

		// cleanup
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	public function testWithNonExistentSetQueryClass(): void {
		$query = '
			query {
				testConnection {
					nodes {
						__typename
					}
				}
			}
		';

		$config = [
			'fromType'           => 'RootQuery',
			'toType'             => 'Post',
			'fromFieldName'      => 'testConnection',
			'connectionTypeName' => 'NonExistentQueryClassConnection',
			'resolve'            => static function ( $source, $args, $context, $info ) {
				$resolver = new PostObjectConnectionResolver( $source, $args, $context, $info );

				$resolver->set_query_class( 'NonExistentQueryClass' );

				return $resolver->get_connection();
			},
		];

		register_graphql_connection( $config );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertEquals( 'The query class NonExistentQueryClass does not exist.', $actual['errors'][0]['extensions']['debugMessage'] );
	}

	public function testWithIncompatibleSetQueryClass(): void {
		require_once __DIR__ . '/../_data/classes/WP_Query_Incompatible.php';

		$query = '
			query {
				testConnection {
					nodes {
						__typename
					}
				}
			}
		';

		$config = [
			'fromType'           => 'RootQuery',
			'toType'             => 'Post',
			'fromFieldName'      => 'testConnection',
			'connectionTypeName' => 'NonExistentQueryClassConnection',
			'resolve'            => static function ( $source, $args, $context, $info ) {
				$resolver = new PostObjectConnectionResolver( $source, $args, $context, $info );

				$resolver->set_query_class( WP_Query_Incompatible::class );

				return $resolver->get_connection();
			},
		];

		register_graphql_connection( $config );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringStartsWith( 'The query class WP_Query_Incompatible is not compatible with', $actual['errors'][0]['extensions']['debugMessage'] );
	}

	protected function assertValidTypes( $actual ): void {
		$this->assertEquals( 'RootQueryToTestObjectConnection', $actual['data']['testConnection']['__typename'] );
		$this->assertEquals( 'RootQueryToTestObjectConnectionEdge', $actual['data']['testConnection']['edges'][0]['__typename'] );
		$this->assertEquals( 'TestObject', $actual['data']['testConnection']['edges'][0]['node']['__typename'] );
		$this->assertEquals( 'TestObject', $actual['data']['testConnection']['nodes'][0]['__typename'] );
	}
}
