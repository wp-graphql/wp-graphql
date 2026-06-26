<?php

class AssertValidSchemaTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();

		$settings                                 = get_option( 'graphql_general_settings' );
		$settings['public_introspection_enabled'] = 'on';
		update_option( 'graphql_general_settings', $settings );
		$this->clearSchema();
	}

	public function tearDown(): void {
		// your tear down methods here

		// then
		$this->clearSchema();
		parent::tearDown();
	}

	public function testValidSchema() {
		$this->assertTrue( true );
	}

	// Validate schema.
	public function testSchema() {
		try {
			$request = new \WPGraphQL\Request();

			$schema = WPGraphQL::get_schema();
			$this->clearSchema();
			$schema->assertValid();

			// Assert true upon success.
			$this->assertTrue( true );
		} catch ( \GraphQL\Error\InvariantViolation $e ) {
			// use --debug flag to view.
			codecept_debug( $e->getMessage() );
			$this->clearSchema();
			// Fail upon throwing
			$this->assertTrue( false );
		}
	}

	public function testIntrospectionQueriesDisabledForPublicRequests() {

		add_filter( 'graphql_debug_enabled', '__return_false' );

		$settings                                 = get_option( 'graphql_general_settings' );
		$settings['public_introspection_enabled'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		$actual = $this->graphql(
			[
				'query' => '
			{
				__type(name: "RootQuery") {
					name
				}
				__schema {
					queryType {
						name
					}
				}
			}
			',
			]
		);

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertSame( 'The query contained __schema or __type, however GraphQL introspection is not allowed for public requests by default. Public introspection can be enabled under the WPGraphQL Settings.', $actual['errors'][0]['message'] );
	}

	public function testIntrospectionQueriesByAdminWhenPublicIntrospectionIsDisabled() {

		$settings                                 = get_option( 'graphql_general_settings' );
		$settings['public_introspection_enabled'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		$admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		wp_set_current_user( $admin );

		$query = '
			{
				__type(name: "RootQuery") {
					name
				}
				__schema {
					queryType {
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testIntrospectionQueriesEnabledForPublicUsers() {

		$settings                                 = get_option( 'graphql_general_settings' );
		$settings['public_introspection_enabled'] = 'on';
		update_option( 'graphql_general_settings', $settings );

		$query = '
			{
				__type(name: "RootQuery") {
					name
				}
				__schema {
					queryType {
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testContentTypesOfTaxonomy() {

		register_post_type(
			'test_content_type',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestContentType',
				'graphql_plural_name' => 'TestContentTypes',
				'public'              => true,
				'label'               => 'Content Types of Taxonomy',
			]
		);

		register_post_type(
			'test_cpt_excluded',
			[
				'public' => true,
				'label'  => 'Content Types Not in GraphQL',
			]
		);

		register_taxonomy(
			'test_ct_tax_one',
			[ 'test_content_type', 'test_cpt_excluded' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestTaxonomy',
				'graphql_plural_name' => 'TestTaxonomies',
				'public'              => true,
				'label'               => 'Content Types of Tax One',
			]
		);

		register_taxonomy(
			'test_ct_tax_two',
			[ 'test_cpt_excluded' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestTaxonomyTwo',
				'graphql_plural_name' => 'TestTaxonomieTwos',
				'public'              => true,
				'label'               => 'Content Types of Tax Two',
			]
		);

		$query = '
		query GetEnum($name:String!){
			__type(name: $name) {
				name
				kind
				enumValues {
					name
				}
			}
		}
		';

		$variables = [
			'name' => 'ContentTypesOfTestTaxonomyEnum',
		];

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);

		// We registered a Taxonomy that is associated with 2 post types. One that is shown in GraphQL, and one that is not
		// So we Introspect the taxonomy and assert that the enum is created with the association to the post type that
		// is shown in GraphQL but excludes the other post type
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( 1 === count( $actual['data']['__type']['enumValues'] ) );
		$this->assertSame( \WPGraphQL\Type\WPEnumType::get_safe_name( 'test_content_type' ), $actual['data']['__type']['enumValues'][0]['name'] );

		$variables = [
			'name' => 'ContentTypesOfTestTaxonomyTwoEnum',
		];

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);

		// We registered another taxonomy that is only associated with a post type that is not shown in graphql,
		// so we introspect to test that a "ContentTypesOfTestTaxonomyTwoEnum" isn't actually
		// registered to the Schema
		$this->assertNull( $actual['data']['__type'] );

		unregister_post_type( 'test_content_type' );
		unregister_post_type( 'test_cpt_excluded' );
		unregister_taxonomy( 'test_ct_tax_one' );
		unregister_taxonomy( 'test_ct_tax_two' );
	}

	public function testSchemaSupportsLazyLoadingTypes() {
		add_filter(
			'graphql_get_type',
			function ( $type, $type_name ) {
				if ( 'TestLazyType' === $type_name ) {
					// Should not be called since this type does not need to be loaded.
					$this->assertTrue( false );
				}

				return $type;
			},
			10,
			2
		);

		add_filter(
			'graphql_wp_object_type_config',
			function ( $config ) {
				if ( 'TestLazyType' === $config['name'] ) {
					// Should not be called since this type does not need to be loaded.
					$this->assertTrue( false );
				}

				return $config;
			},
			10,
			1
		);

		add_filter(
			'graphql_object_fields',
			function ( $fields, $type_name ) {
				if ( 'TestLazyType' === $type_name ) {
					// Should not be called since this type does not need to be loaded.
					$this->assertTrue( false );
				}

				return $fields;
			},
			10,
			2
		);

		register_graphql_type(
			'TestLazyType',
			[
				'fields' => function () {
					// Should not be called since this type does not need to be loaded.
					$this->assertTrue( false );

					return [];
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'example',
			[ 'type' => 'TestLazyType' ]
		);

		register_graphql_field(
			'RootQuery',
			'allExamples',
			[
				'type' => [
					'list_of' => [
						'non_null' => 'TestLazyType',
					],
				],
			],
		);

		$actual = $this->graphql(
			[
				'query' => '
			{
				__type(name: "RootQuery") {
					name
				}
			}
			',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testSchemaCallsLazyLoadingTypesWhenNeeded() {
		$filter_calls = [];

		add_filter(
			'graphql_get_type',
			static function ( $type, $type_name ) use ( &$filter_calls ) {
				if ( 'TestLazyType' === $type_name ) {
					$filter_calls[] = 'graphql_get_type';
				}

				return $type;
			},
			10,
			2
		);

		add_filter(
			'graphql_wp_object_type_config',
			static function ( $config ) use ( &$filter_calls ) {
				if ( 'TestLazyType' === $config['name'] ) {
					$filter_calls[] = 'graphql_wp_object_type_config';
				}

				return $config;
			},
			10,
			1
		);

		add_filter(
			'graphql_object_fields',
			static function ( $fields, $type_name ) use ( &$filter_calls ) {
				if ( 'TestLazyType' === $type_name ) {
					$filter_calls[] = 'graphql_object_fields';
				}

				return $fields;
			},
			10,
			2
		);

		register_graphql_type(
			'TestLazyType',
			[
				'fields' => [
					'foo' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'example',
			[
				'type'    => 'TestLazyType',
				'resolve' => static function () {
					return [
						'foo' => 'bar',
					];
				},
			]
		);

		$actual = $this->graphql(
			[
				'query' => '
			{
				example {
					foo
				}
			}
			',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'bar', $actual['data']['example']['foo'] );

		sort( $filter_calls );
		$expected_filter_calls = [
			'graphql_get_type',
			'graphql_object_fields',
			'graphql_wp_object_type_config',
		];

		$this->assertSame( $expected_filter_calls, $filter_calls );
	}

	public function testRegisterTypeWithNameOfExistingPhpFunctionDoesNotCauseErrors() {

		register_graphql_field(
			'RootQuery',
			'header',
			[
				'type'    => 'Header',
				'resolve' => static function () {
					return 'it works!';
				},
			]
		);

		register_graphql_object_type(
			'Header',
			[
				'description' => __( 'This Type is named after a PHP function to test that it does not cause conflicts', 'wp-graphql' ),
				'fields'      => [
					'test' => [
						'type' => 'String',
					],
				],
			]
		);

		$query = '
		{
			header {
				test
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testRegisterTypeWithNameOfExistingWordpressFunctionDoesNotCauseErrors() {

		register_graphql_field(
			'RootQuery',
			'wpSendJson',
			[
				'type'    => 'WP_Send_Json',
				'resolve' => static function () {
					return 'it works!';
				},
			]
		);

		register_graphql_object_type(
			'WP_Send_Json',
			[
				'description' => __( 'This type is named after a WordPress function to test that it does not cause conflicts', 'wp-graphql' ),
				'fields'      => [
					'test' => [
						'type' => 'String',
					],
				],
			]
		);

		$query = '
		{
			wpSendJson {
				test
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	/**
	 * Many moons ago, fields could pass a Type definition instead of an array.
	 *
	 * This test ensures older plugins that extend WPGraphQL in this way still work
	 *
	 * @throws \Exception
	 */
	public function testRegisteringFieldWithGraphQLTypeDefinitionAsTypeConfigDoesntThrowErrors() {

		$type = new \GraphQL\Type\Definition\ObjectType(
			[
				'name'   => 'Test',
				'fields' => [
					'test' => GraphQL\Type\Definition\Type::string(),
				],
			]
		);

		// New way to register:
		// register_graphql_object_type( 'Test', [
		// 'fields' => [
		// 'test' => [
		// 'type' => 'String',
		// ],
		// ],
		// ] );

		register_graphql_field(
			'RootQuery',
			'test',
			[
				'type' => $type,
			]
		);

		// New way to register
		// register_graphql_field( 'RootQuery', 'test', [
		// 'type' => 'Test'
		// ]);

		$query = '
		{
			test {
				test
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	/**
	 * Enforce the GraphQL spec rule that an implementation field may only be deprecated
	 * if the corresponding interface field is also deprecated (graphql/graphql-spec#1053).
	 *
	 * graphql-php v15's assertValid() does not enforce this rule, but client tooling built
	 * on graphql-js (graphql-codegen, buildClientSchema, strict introspection) does, so a
	 * schema that violates it is broken for those consumers even though assertValid() passes.
	 *
	 * This is a schema-wide guard (not specific to any one type) so the whole class of bug
	 * is caught regardless of which connection/type introduces it.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3979
	 */
	public function testNoInterfaceImplementationDeprecationMismatch() {
		// Walk the schema as rendered to an introspection query, because that is exactly
		// what graphql-js based tooling consumes. WPGraphQL only materializes
		// `deprecationReason` callables during an introspection request, so the deprecation
		// state only exists on the introspected schema, not on a bare WPGraphQL::get_schema().
		$introspection = $this->graphql(
			[
				'query' => '
				{
					__schema {
						types {
							name
							interfaces { name }
							fields(includeDeprecated: true) { name isDeprecated }
						}
					}
				}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $introspection );

		$types = $introspection['data']['__schema']['types'];

		// Build a map of type name => [ field name => isDeprecated ].
		$field_deprecation = [];
		foreach ( $types as $type ) {
			if ( empty( $type['fields'] ) ) {
				continue;
			}
			foreach ( $type['fields'] as $field ) {
				$field_deprecation[ $type['name'] ][ $field['name'] ] = (bool) $field['isDeprecated'];
			}
		}

		// For every implementing type, a deprecated field is only valid if the same field
		// on each implemented interface is also deprecated (graphql/graphql-spec#1053).
		$violations = [];
		foreach ( $types as $type ) {
			if ( empty( $type['interfaces'] ) || empty( $type['fields'] ) ) {
				continue;
			}

			foreach ( $type['fields'] as $field ) {
				if ( ! $field['isDeprecated'] ) {
					continue;
				}

				foreach ( $type['interfaces'] as $interface ) {
					$interface_name = $interface['name'];

					// Only compare fields the interface actually declares.
					if ( ! isset( $field_deprecation[ $interface_name ][ $field['name'] ] ) ) {
						continue;
					}

					if ( false === $field_deprecation[ $interface_name ][ $field['name'] ] ) {
						$violations[] = sprintf(
							'%1$s.%2$s is deprecated, but interface field %3$s.%2$s is not.',
							$type['name'],
							$field['name'],
							$interface_name
						);
					}
				}
			}
		}

		$this->assertSame(
			[],
			$violations,
			"Found interface/implementation deprecation mismatches that break graphql-js based tooling (graphql-codegen, buildClientSchema):\n" . implode( "\n", $violations )
		);
	}
}
