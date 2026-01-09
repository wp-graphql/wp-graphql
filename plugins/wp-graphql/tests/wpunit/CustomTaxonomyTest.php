<?php

class CustomTaxonomyTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();

		register_post_type(
			'test_custom_tax_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'bootstrapPost',
				'graphql_plural_name' => 'bootstrapPosts',
				'hierarchical'        => true,
				'taxonomies'          => [ 'test_custom_tax' ],
			]
		);
		register_taxonomy(
			'test_custom_tax',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'bootstrapTerm',
				'graphql_plural_name' => 'bootstrapTerms',
				'hierarchical'        => true,
			]
		);

		$this->clearSchema();
	}

	public function tearDown(): void {
		unregister_post_type( 'test_custom_tax_cpt' );
		unregister_taxonomy( 'test_custom_tax' );
		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryCustomTaxomomy() {

		$id = $this->factory()->term->create(
			[
				'taxonomy' => 'test_custom_tax',
				'name'     => 'Honda',
			]
		);

		$query = '
		query GET_CUSTOM_TAX_TERMS {
			bootstrapTerms {
				nodes {
					bootstrapTermId
				}
				edges {
					node {
						bootstrapTermId
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['nodes'][0]['bootstrapTermId'] );
		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['edges'][0]['node']['bootstrapTermId'] );
	}
	public function testQueryCustomTaxomomyChildren() {

		// Just create a post of the same cpt to expose issue #905
		$this->factory()->post->create(
			[
				'post_content' => 'Test post content',
				'post_excerpt' => 'Test excerpt',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post QueryCustomTaxomomyChildren',
				'post_type'    => 'test_custom_tax_cpt',
			]
		);

		$parent_id = $this->factory()->term->create(
			[
				'taxonomy' => 'test_custom_tax',
				'name'     => 'parent',
			]
		);

		$child_id = $this->factory()->term->create(
			[
				'taxonomy' => 'test_custom_tax',
				'name'     => 'child',
				'parent'   => $parent_id,
			]
		);

		$query = '
		query TaxonomyChildren {
			bootstrapTerms(where:{parent:0}) {
				nodes {
			name
				children {
					nodes {
						name
					}
				}
				}
			}
			bootstrapPosts {
				nodes {
					title
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertEquals( 'child', $actual['data']['bootstrapTerms']['nodes'][0]['children']['nodes'][0]['name'] );
	}

	public function testQueryCustomTaxonomyWithSameValueForGraphqlSingleNameAndGraphqlPluralName() {
		register_taxonomy(
			'aircraft',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'aircraft',
				'graphql_plural_name' => 'aircraft',
				'hierarchical'        => true,
			]
		);

		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'aircraft',
				'name'     => 'Boeing 767',
			]
		);

		$query = '
		query GET_CUSTOM_TAX_TERMS( $id: ID! ) {
			aircraft( id: $id idType: DATABASE_ID ) {
				databaseId
			}
			allAircraft {
				nodes {
					databaseId
				}
				edges {
					node {
					databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $term_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $term_id, $actual['data']['aircraft']['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['nodes'][0]['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['edges'][0]['node']['databaseId'] );

		unregister_taxonomy( 'aircraft' );
	}

	public function testRegisterTaxonomyWithoutRootField() {
		register_taxonomy(
			'non_root_field',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'             => true,
				'graphql_single_name'         => 'NonRoot',
				'graphql_plural_name'         => 'NonRoots',
				'graphql_register_root_field' => false,
			]
		);

		$query = '
		query GetType( $typeName: String! ){
			__type(name: $typeName) {
				name
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'RootQuery',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		codecept_debug( [ 'names' => $names ] );

		// assert that other common fields are in the rootQuery
		$this->assertContains( 'terms', $names );
		$this->assertContains( 'category', $names );

		// assert that the connection field is there
		$this->assertContains( 'nonRoots', $names );

		// but the singular root field is not there
		$this->assertNotContains( 'nonRoot', $names );

		unregister_taxonomy( 'non_root_field' );
	}

	public function testRegisterTaxonomyWithoutRootConnection() {
		register_taxonomy(
			'non_root_connection',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'                  => true,
				'graphql_single_name'              => 'NonRoot',
				'graphql_plural_name'              => 'NonRoots',
				'graphql_register_root_connection' => false,
			]
		);

		$query = '
		query GetType( $typeName: String! ){
			__type(name: $typeName) {
				name
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'RootQuery',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		codecept_debug( [ 'names' => $names ] );

		// assert that other common fields are in the rootQuery
		$this->assertContains( 'terms', $names );
		$this->assertContains( 'category', $names );

		// assert that the connection field is there
		$this->assertContains( 'nonRoot', $names );

		// but the singular root field is not there
		$this->assertNotContains( 'nonRoots', $names );

		unregister_taxonomy( 'non_root_connection' );
	}

	public function testRegisterCustomTaxonomyWithCustomInterfaces() {
		$value = uniqid( 'testField', true );

		register_graphql_interface_type(
			'TestInterface',
			[
				'fields' => [
					'testField' => [
						'type'    => 'String',
						'resolve' => static function () use ( $value ) {
							return $value;
						},
					],
				],
			]
		);

		register_taxonomy(
			'custom_interface',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'     => true,
				'public'              => true,
				'graphql_single_name' => 'CustomInterface',
				'graphql_plural_name' => 'CustomInterfaces',
				'graphql_interfaces'  => [ 'TestInterface' ],
			]
		);

		$term_id = self::factory()->term->create(
			[
				'taxonomy' => 'custom_interface',
				'name'     => 'my test term',
			]
		);

		$query = '
		query getCustomInterfacePost($id:ID!){
			customInterface( id: $id idType:DATABASE_ID ) {
				__typename
				databaseId
				# We can successfully query for the testField, which is part of the interface and
				# was added to the post type via the registry utils
				testField
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $term_id,
				],
			]
		);

		$this->assertResponseIsValid( $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'customInterface.__typename', 'CustomInterface' ),
				$this->expectedField( 'customInterface.databaseId', $term_id ),
				$this->expectedField( 'customInterface.testField', $value ),
			]
		);

		// now we want to query type from the schema and assert that it
		// has the interface applied and the field from the interface
		$query = '
		query GetType( $typeName: String! ){
			__type(name: $typeName) {
				name
				interfaces {
					name
				}
				possibleTypes {
					name
				}
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'CustomInterface',
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedObject(
					'__type.fields',
					[
						'name' => 'testField',
					]
				),
				$this->expectedObject(
					'__type.interfaces',
					[
						'name' => 'TestInterface',
					]
				),
			]
		);

		// Now, query for the TestInterface type
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'TestInterface',
				],
			]
		);

		// assert that it has the testField field, and that the CustomInterface is the
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedObject(
					'__type.fields',
					[
						'name' => 'testField',
					]
				),
				$this->expectedObject(
					'__type.possibleTypes',
					[
						'name' => 'CustomInterface',
					]
				),
			]
		);

		// assert that the only Type that implements the TestInterface is the CustomInterface type
		// i.e. it hasn't accidentally leaked into being applied to other post types
		$this->assertEqualSets(
			[
				[ 'name' => 'CustomInterface' ],
			],
			$actual['data']['__type']['possibleTypes']
		);

		unregister_taxonomy( 'custom_interface' );
	}

	public function testRegisterCustomTaxonomyWithExcludedInterfaces() {
		register_taxonomy(
			'removed_interfaces',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'            => true,
				'public'                     => true,
				'hierarchical'               => true,
				'show_in_nav_menus'          => true,
				'graphql_single_name'        => 'CustomInterfaceExcluded',
				'graphql_plural_name'        => 'CustomInterfacesExcluded',
				'graphql_exclude_interfaces' => [ 'HierarchicalTermNode' ],
			]
		);

		$term_id = self::factory()->term->create(
			[
				'taxonomy' => 'removed_interfaces',
				'name'     => 'my test term',
			]
		);

		$query = '
		query getCustomInterfaceExcludedPost($id:ID!){
			customInterfaceExcluded( id: $id idType:DATABASE_ID ) {
				parentDatabaseId
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $term_id,
				],
			]
		);

		$this->assertResponseIsValid( $actual );
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringStartsWith( 'Cannot query field "parentDatabaseId"', $actual['errors'][0]['message'] );

		// now we want to query type from the schema and assert that it
		// has the interface applied and the field from the interface
		$query = '
		query GetType( $typeName: String! ){
			__type(name: $typeName) {
				name
				interfaces {
					name
				}
				possibleTypes {
					name
				}
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'CustomInterfaceExcluded',
				],
			]
		);

		$this->assertResponseIsValid( $actual );
		$this->assertNotContains(
			$actual['data']['__type']['interfaces'],
			[
				[ 'name' => 'HierarchicalTermNode' ],
			]
		);
		$this->assertNotContains(
			$actual['data']['__type']['fields'],
			[
				[ 'name' => 'parentDatabaseId' ],
			]
		);

		// Now, query for the HierarchicalTermNode type
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'HierarchicalTermNode',
				],
			]
		);

		$this->assertResponseIsValid( $actual );
		$this->assertNotContains(
			$actual['data']['__type']['possibleTypes'],
			[
				[ 'name' => 'CustomInterfaceExcluded' ],
			]
		);

		unregister_taxonomy( 'removed_interfaces' );
	}

	public function testRegisterCustomTaxonomyWithConnections() {
		register_taxonomy(
			'with_connections',
			[ 'test_custom_tax_cpt' ],
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithConnection',
				'graphql_plural_name' => 'WithConnections',
				'graphql_connections' => [
					'connectionFieldName' => [
						'toType'  => 'Post',
						'resolve' => static function () {
							return null;
						},
					],
				],
			]
		);

		$query = '
		{
			withConnections {
				nodes {
					__typename
					databaseId
					connectionFieldName {
						nodes {
							__typename
						}
					}
				}
			}
		}
		';

		$response = $this->graphql(
			[
				'query' => $query,
			]
		);

		// assert that the query is valid
		$this->assertResponseIsValid( $response );
		$this->assertArrayNotHasKey( 'errors', $response );

		$query = '
		query GetType( $typeName: String! ){
			__type(name: $typeName) {
				name
				fields {
					name
				}
			}
		}
		';

		// query the WithConnection type
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'WithConnection',
				],
			]
		);

		$names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		codecept_debug( [ 'names' => $names ] );

		// assert that other common fields are in the rootQuery
		$this->assertContains( 'id', $names );
		$this->assertContains( 'databaseId', $names );

		// assert that the connection field is there
		$this->assertContains( 'connectionFieldName', $names );

		unregister_taxonomy( 'with_connections' );
	}

	public function testRegisterTaxonomyWithExcludedConnections() {
		register_taxonomy(
			'missing_connections',
			[ 'test_custom_tax_cpt' ],
			[
				'public'                      => true,
				'show_in_graphql'             => true,
				'graphql_single_name'         => 'ExcludedConnection',
				'graphql_plural_name'         => 'ExcludedConnections',
				'graphql_exclude_connections' => [ 'contentNodes' ],
			]
		);

		$query = '
		{
			excludedConnections {
				nodes {
					__typename
					databaseId
					contentNodes {
						nodes {
							__typename
						}
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringStartsWith( 'Cannot query field "contentNodes"', $actual['errors'][0]['message'] );

		unregister_taxonomy( 'missing_connections' );
	}

	public function testRegisterTaxonomyWithGraphQLKindNoResolver() {
		$query = '{
			taxonomies {
				nodes {
					__typename
				}
			}
		}';

		register_taxonomy(
			'with_interface_kind',
			[ 'test_custom_tax_cpt' ],
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithInterfaceKind',
				'graphql_plural_name' => 'WithInterfaceKinds',
				'graphql_kind'        => 'interface',
			]
		);
		register_taxonomy(
			'with_union_kind_one',
			[ 'test_custom_tax_cpt' ],
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithUnionKindOne',
				'graphql_plural_name' => 'WithUnionKindOnes',
				'graphql_kind'        => 'union',
			]
		);

		register_taxonomy(
			'with_union_kind_two',
			[ 'test_custom_tax_cpt' ],
			[
				'public'               => true,
				'show_in_graphql'      => true,
				'graphql_single_name'  => 'WithUnionKindTwo',
				'graphql_plural_name'  => 'WithUnionKindTwos',
				'graphql_kind'         => 'union',
				'graphql_resolve_type' => $this->resolve_type(),
			]
		);

		// Don't clutter up the log.
		$actual = graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['taxonomies']['nodes'] );

		$error_object_names = array_map(
			static function ( $obj ) {
				return $obj->name;
			},
			array_column( $actual['extensions']['debug'], 'registered_taxonomy_object' )
		);

		codecept_debug( $error_object_names );

		$this->assertEquals( 'with_interface_kind', $error_object_names[0] );
		$this->assertEquals( 'with_union_kind_one', $error_object_names[1] );
		$this->assertEquals( 'with_union_kind_two', $error_object_names[2] );

		unregister_taxonomy( 'with_interface_kind' );
		unregister_taxonomy( 'with_union_kind_one' );
		unregister_taxonomy( 'with_union_kind_two' );
	}

	public function testRegisterTaxonomyWithInterfaceKind() {
		register_taxonomy(
			'with_interface_kind',
			[ 'test_custom_tax_cpt' ],
			[
				'public'               => true,
				'show_in_graphql'      => true,
				'graphql_single_name'  => 'WithInterfaceKind',
				'graphql_plural_name'  => 'WithInterfaceKinds',
				'graphql_kind'         => 'interface',
				'graphql_resolve_type' => $this->resolve_type(),
			]
		);

		register_graphql_object_type(
			'ChildTypeOne',
			[
				'eagerlyLoadType' => true,
				'interfaces'      => [ 'WithInterfaceKind' ],
				'fields'          => [
					'isTypeOne' => [
						'type'    => 'Boolean',
						'resolve' => static function ( $term ) {
							return 'ChildTypeOne' === get_term_meta( $term->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'ChildTypeTwo',
			[
				'eagerlyLoadType' => true,
				'interfaces'      => [ 'WithInterfaceKind' ],
				'fields'          => [
					'isTypeTwo' => [
						'type'    => 'Boolean',
						'resolve' => static function ( $term ) {
							return 'ChildTypeTwo' === get_term_meta( $term->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		$term_one_id = $this->factory()->term->create(
			[
				'taxonomy' => 'with_interface_kind',
				'name'     => 'Interface child 1',
			]
		);
		add_term_meta( $term_one_id, 'child_type', 'ChildTypeOne' );

		$term_two_id = $this->factory()->term->create(
			[
				'taxonomy' => 'with_interface_kind',
				'name'     => 'Interface child 2',
			]
		);
		add_term_meta( $term_two_id, 'child_type', 'ChildTypeTwo' );

		$this->clearSchema();

		$query = '
		{
			withInterfaceKinds {
				nodes {
					__typename
						databaseId
					... on ChildTypeOne{
						isTypeOne
					}
					... on ChildTypeTwo {
						isTypeTwo
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $term_one_id, $actual['data']['withInterfaceKinds']['nodes'][0]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeTwo', $actual['data']['withInterfaceKinds']['nodes'][0] );
		$this->assertTrue( $actual['data']['withInterfaceKinds']['nodes'][0]['isTypeOne'] );

		$this->assertEquals( $term_two_id, $actual['data']['withInterfaceKinds']['nodes'][1]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeOne', $actual['data']['withInterfaceKinds']['nodes'][1] );
		$this->assertTrue( $actual['data']['withInterfaceKinds']['nodes'][1]['isTypeTwo'] );

		unregister_taxonomy( 'with_interface_kind' );
	}

	public function testRegisterTaxonomyWithUnionKind() {
		register_taxonomy(
			'with_union_kind',
			[ 'test_custom_tax_cpt' ],
			[
				'public'               => true,
				'show_in_graphql'      => true,
				'graphql_single_name'  => 'WithUnionKind',
				'graphql_plural_name'  => 'WithUnionKinds',
				'graphql_kind'         => 'union',
				'graphql_resolve_type' => $this->resolve_type(),
				'graphql_union_types'  => [
					'ChildTypeOne',
					'ChildTypeTwo',
				],
			]
		);

		register_graphql_object_type(
			'ChildTypeOne',
			[
				'eagerlyLoadType' => true,
				'interfaces'      => [ 'TermNode' ],
				'fields'          => [
					'isTypeOne' => [
						'type'    => 'Boolean',
						'resolve' => static function ( $term ) {
							return 'ChildTypeOne' === get_term_meta( $term->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'ChildTypeTwo',
			[
				'eagerlyLoadType' => true,
				'interfaces'      => [ 'TermNode' ],
				'fields'          => [
					'isTypeTwo' => [
						'type'    => 'Boolean',
						'resolve' => static function ( $term ) {
							return 'ChildTypeTwo' === get_term_meta( $term->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		$term_one_id = $this->factory()->term->create(
			[
				'taxonomy' => 'with_union_kind',
				'name'     => 'Union child 1',
			]
		);
		add_term_meta( $term_one_id, 'child_type', 'ChildTypeOne' );

		$term_two_id = $this->factory()->term->create(
			[
				'taxonomy' => 'with_union_kind',
				'name'     => 'Union child 2',
			]
		);
		add_term_meta( $term_two_id, 'child_type', 'ChildTypeTwo' );

		$this->clearSchema();

		$query = '
		{
			withUnionKinds {
				nodes {
					__typename
					... on ChildTypeOne {
						databaseId
						isTypeOne
					}
					... on ChildTypeTwo {
						databaseId
						isTypeTwo
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $term_one_id, $actual['data']['withUnionKinds']['nodes'][0]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeTwo', $actual['data']['withUnionKinds']['nodes'][0] );
		$this->assertTrue( $actual['data']['withUnionKinds']['nodes'][0]['isTypeOne'] );

		$this->assertEquals( $term_two_id, $actual['data']['withUnionKinds']['nodes'][1]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeOne', $actual['data']['withUnionKinds']['nodes'][1] );
		$this->assertTrue( $actual['data']['withUnionKinds']['nodes'][1]['isTypeTwo'] );

		unregister_taxonomy( 'with_union_kind' );
	}


	public function resolve_type() {
		return static function ( $value ) {
			$type_registry = WPGraphQL::get_type_registry();

			$type = null;

			$child_type_name = get_term_meta( $value->databaseId, 'child_type', true );

			if ( ! empty( $child_type_name ) ) {
				$type = $type_registry->get_type( $child_type_name );
			}

			return ! empty( $type ) ? $type : null;
		};
	}

	public function testExcludeCreateMutation() {

		register_taxonomy(
			'without_create',
			'post',
			[
				'public'                    => true,
				'show_in_graphql'           => true,
				'graphql_single_name'       => 'WithoutCreate',
				'graphql_plural_name'       => 'WithoutCreates',
				'graphql_exclude_mutations' => [ 'create' ],
			]
		);

		$this->clearSchema();

		$query = '
		query {
			__type(name:"RootMutation") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		$this->assertNotEmpty( $actual['data']['__type']['fields'] );
		$this->assertContains( 'deleteWithoutCreate', $field_names );
		$this->assertContains( 'updateWithoutCreate', $field_names );

		// we excluded this mutation
		$this->assertNotContains( 'createWithoutCreate', $field_names );

		unregister_taxonomy( 'without_create' );
	}

	public function testExcludeDeleteMutation() {

		register_taxonomy(
			'without_delete',
			'post',
			[
				'public'                    => true,
				'show_in_graphql'           => true,
				'graphql_single_name'       => 'WithoutDelete',
				'graphql_plural_name'       => 'WithoutDeletes',
				'graphql_exclude_mutations' => [ 'delete' ],
			]
		);

		$this->clearSchema();

		$query = '
		query {
			__type(name:"RootMutation") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		$this->assertNotEmpty( $actual['data']['__type']['fields'] );
		$this->assertContains( 'createWithoutDelete', $field_names );
		$this->assertContains( 'updateWithoutDelete', $field_names );

		// we excluded this mutation
		$this->assertNotContains( 'deleteWithoutDelete', $field_names );

		unregister_taxonomy( 'without_delete' );
	}

	public function testExcludeUpdateMutation() {

		register_taxonomy(
			'without_update',
			'post',
			[
				'public'                    => true,
				'show_in_graphql'           => true,
				'graphql_single_name'       => 'WithoutUpdate',
				'graphql_plural_name'       => 'WithoutUpdates',
				'graphql_exclude_mutations' => [ 'update' ],
			]
		);

		$this->clearSchema();

		$query = '
		query {
			__type(name:"RootMutation") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertNotEmpty( $actual['data']['__type']['fields'] );

		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		$this->assertContains( 'createWithoutUpdate', $field_names );
		$this->assertContains( 'deleteWithoutUpdate', $field_names );

		// we excluded this mutation
		$this->assertNotContains( 'updateWithoutUpdate', $field_names );

		unregister_taxonomy( 'without_update' );
	}

	public function testRegisterTaxonomyWithGraphqlFields() {

		register_taxonomy(
			'gql_fields',
			'post',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'GraphqlField',
				'graphql_plural_name' => 'GraphqlFields',

				// we're testing that this field was added to the Schema when
				// registering a post type
				'graphql_fields'      => [
					'testField' => [
						'type'        => 'String',
						'description' => 'test field',
						'resolve'     => static function () {
							return 'test value';
						},
					],
				],
			]
		);

		$this->clearSchema();

		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'gql_fields',
				'name'     => 'Test GraphQL Fields',
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		wp_set_object_terms( $post_id, [ $term_id ], 'gql_fields' );

		$query = '
		{
			graphqlFields {
				nodes {
					id
					databaseId
					name
					testField
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'test value', $actual['data']['graphqlFields']['nodes'][0]['testField'] );
		$this->assertSame( $term_id, $actual['data']['graphqlFields']['nodes'][0]['databaseId'] );

		wp_delete_term( $term_id, 'gql_fields' );
		unregister_taxonomy( 'gql_fields' );
	}

	public function testRegisterTaxonomyWithGraphqlExcludeFields() {

		register_taxonomy(
			'gql_exclude_fields',
			'post',
			[
				'public'                 => true,
				'show_in_graphql'        => true,
				'graphql_single_name'    => 'GraphqlExcludeField',
				'graphql_plural_name'    => 'GraphqlExcludeFields',

				// we're testing that this field was added to the Schema when
				// registering a post type
				'graphql_fields'         => [
					'testField'    => [
						'type'        => 'String',
						'description' => 'test field',
						'resolve'     => static function () {
							return 'test value';
						},
					],
					'testFieldTwo' => [
						'type'        => 'String',
						'description' => 'test field',
						'resolve'     => static function () {
							return 'test value';
						},
					],
				],
				'graphql_exclude_fields' => [ 'testField' ],
			]
		);

		$this->clearSchema();

		$query = '
		query {
			__type(name:"GraphqlExcludeField") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertNotEmpty( $actual['data']['__type']['fields'] );

		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		// we included 2 fields, then excluded 1

		// the excluded field should not be present
		$this->assertNotContains( 'testField', $field_names );

		// the included field that was not excluded should still remain
		$this->assertContains( 'testFieldTwo', $field_names );

		unregister_taxonomy( 'gql_exclude_fields' );
	}

	public function testRegisterCustomPostTypeWithUnderscoresInGraphqlNameHasValidSchema() {

		$args = [
			'public'              => true,
			'hierarchical'        => true,
			'show_ui'             => true,
			'graphql_single_name' => 'tax_with_underscore',
			'graphql_plural_name' => 'tax_with_underscores',
			'show_in_graphql'     => true,
			'label'               => 'Taxonomy with Underscores',
		];

		// register the taxonomy with underscore in the graphql_single_name / graphql_plural_name
		register_taxonomy( 'with_underscore', [ 'post' ], $args );

		$request = new \WPGraphQL\Request();

		unregister_taxonomy( 'with_underscore' );

		$schema = WPGraphQL::get_schema();
		$schema->assertValid();

		// Assert true upon success.
		$this->assertTrue( true );
	}

	public function testRegisterTaxonomyWithoutGraphqlPluralNameIsValid() {

		register_taxonomy(
			'tax_no_plural',
			'post',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'taxNoPlural',
			]
		);

//		$request = new \WPGraphQL\Request();
		$schema  = WPGraphQL::get_schema();
		unregister_taxonomy( 'tax_no_plural' );
		$schema->assertValid();

		$query = '
		{
			allTaxNoPlural {
				nodes {
					id
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);


		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'allTaxNoPlural.nodes', self::IS_FALSY ),
			]
		);


	}

	/**
	 * @throws Exception
	 */
	public function testRegisterTaxonomyWithoutGraphqlSingleOrPluralNameDoesntInvalidateSchema() {

		register_taxonomy(
			'tax_no_single_plural',
			'post',
			[
				'show_in_graphql' => true,
			// no graphql_single_name
			// no graphql_plural_name
			]
		);

		// assert that the schema is still valid, even though the tax
		// didn't provide the single/plural name (it will be left out of the schema)

//		$request = new \WPGraphQL\Request();
		$schema  = WPGraphQL::get_schema();
		$schema->assertValid();


		unregister_taxonomy( 'tax_no_single_plural' );

		$schema->assertValid();



	}
}
