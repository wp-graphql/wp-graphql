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
	 * @throws Exception
	 */
	public function testQueryCustomTaxomomy() {

		$id = $this->factory()->term->create( [
			'taxonomy' => 'test_custom_tax',
			'name'     => 'Honda',
		] );

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

		$actual = $this->graphql( [
			'query' => $query,
		] );

		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['nodes'][0]['bootstrapTermId'] );
		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['edges'][0]['node']['bootstrapTermId'] );

	}
	public function testQueryCustomTaxomomyChildren() {

		// Just create a post of the same cpt to expose issue #905
		$this->factory()->post->create( [
			'post_content' => 'Test post content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Post QueryCustomTaxomomyChildren',
			'post_type'    => 'test_custom_tax_cpt',
		] );

		$parent_id = $this->factory()->term->create( [
			'taxonomy' => 'test_custom_tax',
			'name'     => 'parent',
		] );

		$child_id = $this->factory()->term->create( [
			'taxonomy' => 'test_custom_tax',
			'name'     => 'child',
			'parent'   => $parent_id,
		] );

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

		$actual = $this->graphql( [
			'query' => $query,
		] );

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

		$term_id = $this->factory()->term->create( [
			'taxonomy' => 'aircraft',
			'name'     => 'Boeing 767',
		] );

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

		$actual = $this->graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $term_id,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $term_id, $actual['data']['aircraft']['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['nodes'][0]['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['edges'][0]['node']['databaseId'] );

		unregister_taxonomy( 'aircraft' );
	}

	public function testRegisterTaxonomyWithoutRootField() {
		register_taxonomy( 'non_root_field', [ 'test_custom_tax_cpt' ], [
			'show_in_graphql'             => true,
			'graphql_single_name'         => 'NonRoot',
			'graphql_plural_name'         => 'NonRoots',
			'graphql_register_root_field' => false,
		]);

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

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'RootQuery',
			],
		]);

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
		register_taxonomy( 'non_root_connection', [ 'test_custom_tax_cpt' ], [
			'show_in_graphql'                  => true,
			'graphql_single_name'              => 'NonRoot',
			'graphql_plural_name'              => 'NonRoots',
			'graphql_register_root_connection' => false,
		]);

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

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'RootQuery',
			],
		]);

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

		register_graphql_interface_type( 'TestInterface', [
			'fields' => [
				'testField' => [
					'type'    => 'String',
					'resolve' => function () use ( $value ) {
						return $value;
					},
				],
			],
		]);

		register_taxonomy( 'custom_interface', [ 'test_custom_tax_cpt' ], [
			'show_in_graphql'     => true,
			'public'              => true,
			'graphql_single_name' => 'CustomInterface',
			'graphql_plural_name' => 'CustomInterfaces',
			'graphql_interfaces'  => [ 'TestInterface' ],
		]);

		$term_id = self::factory()->term->create([
			'taxonomy' => 'custom_interface',
			'name'     => 'my test term',
		]);

		$query = '
		query getCustomInterfacePost($id:ID!){
			customInterface( id: $id idType:DATABASE_ID ) {
				__typename
				databaseId
				# We can succesfully query for the testField, which is part of the interface and
				# was added to the post type via the registry utils
				testField
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $term_id,
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertQuerySuccessful( $actual, [
			$this->expectedField( 'customInterface.__typename', 'CustomInterface' ),
			$this->expectedField( 'customInterface.databaseId', $term_id ),
			$this->expectedField( 'customInterface.testField', $value ),
		]);

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

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'CustomInterface',
			],
		]);

		$this->assertQuerySuccessful( $actual, [
			$this->expectedObject( '__type.fields', [
				'name' => 'testField',
			]),
			$this->expectedObject( '__type.interfaces', [
				'name' => 'TestInterface',
			]),
		]);

		// Now, query for the TestInterface type
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'TestInterface',
			],
		]);

		// assert that it has the testField field, and that the CustomInterface is the
		$this->assertQuerySuccessful( $actual, [
			$this->expectedObject( '__type.fields', [
				'name' => 'testField',
			]),
			$this->expectedObject( '__type.possibleTypes', [
				'name' => 'CustomInterface',
			]),
		]);

		// assert that the only Type that implements the TestInterface is the CustomInterface type
		// i.e. it hasn't accidentally leaked into being applied to other post types
		$this->assertEqualSets( [
			[ 'name' => 'CustomInterface' ],
		], $actual['data']['__type']['possibleTypes'] );

		unregister_taxonomy( 'custom_interface' );

	}

	public function testRegisterCustomTaxonomyWithExcludedInterfaces() {
		register_taxonomy( 'removed_interfaces', [ 'test_custom_tax_cpt' ], [
			'show_in_graphql'            => true,
			'public'                     => true,
			'hierarchical'               => true,
			'show_in_nav_menus'          => true,
			'graphql_single_name'        => 'CustomInterfaceExcluded',
			'graphql_plural_name'        => 'CustomInterfacesExcluded',
			'graphql_exclude_interfaces' => [ 'HierarchicalTermNode' ],
		]);

		$term_id = self::factory()->term->create([
			'taxonomy' => 'removed_interfaces',
			'name'     => 'my test term',
		]);

		$query = '
		query getCustomInterfaceExcludedPost($id:ID!){
			customInterfaceExcluded( id: $id idType:DATABASE_ID ) {
				parentDatabaseId
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $term_id,
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
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

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'CustomInterfaceExcluded',
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertNotContains( $actual['data']['__type']['interfaces'], [
			[ 'name' => 'HierarchicalTermNode' ],
		] );
		$this->assertNotContains( $actual['data']['__type']['fields'], [
			[ 'name' => 'parentDatabaseId' ],
		]);

		// Now, query for the HierarchicalTermNode type
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'HierarchicalTermNode',
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertNotContains( $actual['data']['__type']['possibleTypes'], [
			[ 'name' => 'CustomInterfaceExcluded' ],
		] );

		unregister_taxonomy( 'removed_interfaces' );
	}

	public function testRegisterCustomTaxonomyWithConnections() {
		register_taxonomy( 'with_connections', [ 'test_custom_tax_cpt' ], [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'WithConnection',
			'graphql_plural_name' => 'WithConnections',
			'graphql_connections' => [
				'connectionFieldName' => [
					'toType'  => 'Post',
					'resolve' => function () {
						return null;
					},
				],
			],
		]);

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

		$response = $this->graphql([
			'query' => $query,
		]);

		// assert that the query is valid
		$this->assertIsValidQueryResponse( $response );
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
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'WithConnection',
			],
		]);

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
		register_taxonomy( 'missing_connections', [ 'test_custom_tax_cpt' ], [
			'public'                      => true,
			'show_in_graphql'             => true,
			'graphql_single_name'         => 'ExcludedConnection',
			'graphql_plural_name'         => 'ExcludedConnections',
			'graphql_exclude_connections' => [ 'contentNodes' ],
		]);

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

		$this->tester->expectThrowable( \Exception::class, function () {
			register_taxonomy( 'with_interface_kind', [ 'test_custom_tax_cpt' ], [
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithInterfaceKind',
				'graphql_plural_name' => 'WithInterfaceKinds',
				'graphql_kind'        => 'interface',
			]);

		} );

		$this->tester->expectThrowable( \Exception::class, function () {
			register_taxonomy( 'with_union_kind_one', [ 'test_custom_tax_cpt' ], [
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithUnionKind',
				'graphql_plural_name' => 'WithUnionKinds',
				'graphql_kind'        => 'union',
			]);

		} );

		$this->tester->expectThrowable( \Exception::class, function () {
			register_taxonomy( 'with_union_kind_two', [ 'test_custom_tax_cpt' ], [
				'public'               => true,
				'show_in_graphql'      => true,
				'graphql_single_name'  => 'WithUnionKind',
				'graphql_plural_name'  => 'WithUnionKinds',
				'graphql_kind'         => 'union',
				'graphql_resolve_type' => $this->resolve_type(),
			]);

		} );

		unregister_taxonomy( 'with_interface_kind' );
		unregister_taxonomy( 'with_union_kind_one' );
		unregister_taxonomy( 'with_union_kind_two' );
	}

	public function testRegisterTaxonomyWithInterfaceKind() {
		register_taxonomy( 'with_interface_kind', [ 'test_custom_tax_cpt' ], [
			'public'               => true,
			'show_in_graphql'      => true,
			'graphql_single_name'  => 'WithInterfaceKind',
			'graphql_plural_name'  => 'WithInterfaceKinds',
			'graphql_kind'         => 'interface',
			'graphql_resolve_type' => $this->resolve_type(),
		]);

		register_taxonomy( 'child_type_one', [ 'test_custom_tax_cpt' ], [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeOne',
			'graphql_plural_name' => 'ChildTypeOne',
			'graphql_interfaces'  => [ 'WithInterfaceKind' ],
		]);

		register_taxonomy( 'child_type_two', [ 'test_custom_tax_cpt' ], [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeTwo',
			'graphql_plural_name' => 'ChildTypeTwo',
			'graphql_interfaces'  => [ 'WithInterfaceKind' ],
		]);

		$term_one_id = $this->factory()->term->create( [
			'taxonomy' => 'child_type_one',
			'name'     => 'Interface child 1',
		] );
		$term_two_id = $this->factory()->term->create( [
			'taxonomy' => 'child_type_two',
			'name'     => 'Interface child 2',
		] );

		$this->clearSchema();

		$query = '
		{
			withInterfaceKinds {
				nodes {
					... on ChildTypeOne{
						databaseId
					}
					... on ChildTypeTwo {
						databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// $this->assertEquals( $term_one_id, $actual['data']['withInterfaceKinds']['nodes'][0]['databaseId'] );
		// $this->assertEquals( $term_two_id, $actual['data']['withInterfaceKinds']['nodes'][1]['databaseId'] );

		unregister_taxonomy( 'with_interface_kind' );
		unregister_taxonomy( 'child_type_one' );
		unregister_taxonomy( 'child_type_two' );

		$this->markTestIncomplete( 'Connection is throwing duplicate fields error' );
	}

	public function testRegisterTaxonomyWithUnionKind() {
		register_taxonomy( 'with_union_kind', [ 'test_custom_tax_cpt' ], [
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
		]);

		register_taxonomy( 'child_type_one', [ 'test_custom_tax_cpt' ], [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeOne',
			'graphql_plural_name' => 'ChildTypeOne',
		]);

		register_taxonomy( 'child_type_two', [ 'test_custom_tax_cpt' ], [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeTwo',
			'graphql_plural_name' => 'ChildTypeTwo',
		]);

		$term_one_id = $this->factory()->term->create( [
			'taxonomy' => 'child_type_one',
			'name'     => 'Interface child 1',
		] );
		$term_two_id = $this->factory()->term->create( [
			'taxonomy' => 'child_type_two',
			'name'     => 'Interface child 2',
		] );

		$this->clearSchema();

		$query = '
		{
			withUnionKinds {
				nodes {
					... on ChildTypeOne{
						databaseId
					}
					... on ChildTypeTwo {
						databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		// $this->assertEquals( $term_one_id, $actual['data']['withUnionKinds']['nodes'][0]['databaseId'] );
		// $this->assertEquals( $term_two_id, $actual['data']['withUnionKinds']['nodes'][1]['databaseId'] );

		unregister_taxonomy( 'with_union_kind' );
		unregister_taxonomy( 'child_type_one' );
		unregister_taxonomy( 'child_type_two' );
		$this->markTestIncomplete( 'No nodes returned from resolve_type()' );
	}


	public function resolve_type() {
		return function ( $value ) {
			$type_registry = WPGraphQL::get_type_registry();

			$type = null;
			if ( isset( $value->taxonomyName ) ) {
				$tax_object = get_taxonomy( $value->taxonomyName );
				if ( isset( $tax_object->graphql_single_name ) ) {
					$type = $type_registry->get_type( $tax_object->graphql_single_name );
				}
			}

			return ! empty( $type ) ? $type : null;
		};
	}

}
