<?php


class CustomPostTypeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	protected $tester;
	public $post_id;
	public $admin;

	public function setUp(): void {
		parent::setUp();

		register_post_type(
			'cpt_test_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'bootstrapPost',
				'graphql_plural_name' => 'bootstrapPosts',
				'hierarchical'        => true,
				'taxonomies'          => [ 'cpt_test_tax' ],
			]
		);
		register_taxonomy(
			'cpt_test_tax',
			[ 'cpt_test_cpt' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'bootstrapTerm',
				'graphql_plural_name' => 'bootstrapTerms',
				'hierarchical'        => true,
			]
		);

		$this->clearSchema();

		$this->post_id = $this->factory()->post->create([
			'post_type'   => 'cpt_test_cpt',
			'post_status' => 'publish',
			'post_title'  => 'Test for CustomPostTypeTest',
		]);

		$this->admin = $this->factory()->user->create([
			'role' => 'administrator',
		]);

	}

	public function tearDown(): void {
		unregister_post_type( 'cpt_test_cpt' );
		unregister_taxonomy( 'cpt_test_tax' );
		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * @throws Exception
	 */
	public function testQueryCustomPostType() {

		codecept_debug( WPGraphQL::get_allowed_post_types() );

		$query = '
		query GET_CUSTOM_POSTS( $id: Int ) {
			bootstrapPostBy( bootstrapPostId: $id ) {
				bootstrapPostId
			}
			bootstrapPosts {
				nodes {
					bootstrapPostId
				}
				edges {
					node {
						bootstrapPostId
					}
				}
			}
		}
		';

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'id' => $this->post_id,
			],
		]);

		// Since the post type was registered as not-public, a public user should
		// not be able to query the content.
		// This asserts that the content is not returned to a public user.
		$this->assertEmpty( $actual['data']['bootstrapPosts']['nodes'] );
		$this->assertEmpty( $actual['data']['bootstrapPosts']['edges'] );
		$this->assertEmpty( $actual['data']['bootstrapPostBy'] );

		// An authenticated user should be able to access the content
		wp_set_current_user( $this->admin );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $this->post_id,
			],
		]);

		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPostBy']['bootstrapPostId'] );
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPosts']['nodes'][0]['bootstrapPostId'] );
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPosts']['edges'][0]['node']['bootstrapPostId'] );

	}

	public function testQueryNonPublicPostTypeThatIsPublicyQueryable() {

		register_post_type( 'cpt_test_private_cpt', [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'notPublic',
			'graphql_plural_name' => 'notPublics',
			'public'              => false,
			'publicly_queryable'  => true,
		]);

		$database_id = $this->factory()->post->create([
			'post_type'   => 'cpt_test_private_cpt',
			'post_status' => 'publish',
			'post_title'  => 'Test for QueryNonPublicPostTypeThatIsPublicyQueryable',
		]);

		$query = '
		query GET_CUSTOM_POSTS( $id: ID! ) {
			contentNode( id: $id, idType: DATABASE_ID ) {
				databaseId
			}
			notPublics {
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

		// make sure the query is from a public user
		wp_set_current_user( 0 );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $database_id,
			],
		]);

		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		// make sure the query is from a logged in user
		wp_set_current_user( $this->admin );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $database_id,
			],
		]);

		// A logged in user should be able to see the data as well!
		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'cpt_test_private_cpt' );
	}

	public function testQueryPublicPostTypeThatIsNotPublicyQueryable() {

		register_post_type( 'cpt_test_private_cpt', [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'notPublic',
			'graphql_plural_name' => 'notPublics',
			'public'              => true,
			'publicly_queryable'  => false,
		]);

		$database_id = $this->factory()->post->create([
			'post_type'   => 'cpt_test_private_cpt',
			'post_status' => 'publish',
			'post_title'  => 'Test for QueryPublicPostTypeThatIsNotPublicyQueryable',
		]);

		$query = '
		query GET_CUSTOM_POSTS( $id: ID! ) {
			contentNode( id: $id, idType: DATABASE_ID ) {
				databaseId
			}
			notPublics {
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

		// make sure the query is from a public user
		wp_set_current_user( 0 );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $database_id,
			],
		]);

		// Since the post_type is public we should see data, even if it's set to publicly_queryable=>false, as public=>true should trump publicly_queryable
		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'cpt_test_private_cpt' );
	}

	public function testQueryNonPublicPostTypeThatIsNotPublicyQueryable() {

		register_post_type( 'cpt_test_private_cpt', [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'notPublic',
			'graphql_plural_name' => 'notPublics',
			'public'              => false,
			'publicly_queryable'  => false,
		]);

		$database_id = $this->factory()->post->create([
			'post_type'   => 'cpt_test_private_cpt',
			'post_status' => 'publish',
			'post_title'  => 'Test for QueryNonPublicPostTypeThatIsNotPublicyQueryable',
		]);

		$query = '
		query GET_CUSTOM_POSTS( $id: ID! ) {
			contentNode( id: $id, idType: DATABASE_ID ) {
				databaseId
			}
			notPublics {
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

		// make sure the query is from a public user
		wp_set_current_user( 0 );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $database_id,
			],
		]);

		// Since the post_type is public=>false / publicly_queryable=>false, the content should be null for a public user
		$this->assertEmpty( $actual['data']['contentNode'] );
		$this->assertEmpty( $actual['data']['notPublics']['nodes'] );
		$this->assertEmpty( $actual['data']['notPublics']['edges'] );

		// Log the user in and do the request again
		wp_set_current_user( $this->admin );

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $database_id,
			],
		]);

		// The admin user should be able to see the content
		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'cpt_test_private_cpt' );
	}

	public function testQueryCustomPostTypeByUri() {

		global $wp_rewrite;

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->init();

		register_post_type(
			'test_cpt_by_uri',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testCpt',
				'graphql_plural_name' => 'testCpts',
				'hierarchical'        => true,
				'public'              => true,
				'taxonomies'          => [ 'category' ],
				'rewrite'             => true,
			]
		);

		flush_rewrite_rules();

		$post_id = $this->factory()->post->create([
			'post_type'   => 'test_cpt_by_uri',
			'post_status' => 'publish',
			'post_title'  => 'Test for QueryCustomPostTypeByUri',
			'post_author' => $this->admin,
		]);

		$child_post_id = $this->factory()->post->create([
			'post_type'   => 'test_cpt_by_uri',
			'post_status' => 'publish',
			'post_title'  => 'Child Post for QueryCustomPostTypeByUri',
			'post_author' => $this->admin,
		]);

		WPGraphQL::show_in_graphql();

		$query = '
		query GET_CUSTOM_POSTS( $id: ID! ) {
			testCpt(id: $id idType: URI ) {
				__typename
				databaseId
			}
		}
		';

		$uri = get_permalink( $post_id );

		codecept_debug( $uri );

		// Query a parent (top-level) post by URI
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['testCpt']['databaseId'] );

		$child_uri = get_permalink( $child_post_id );

		// Query a child post of CPT by uri
		$actual = $this->graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $child_uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $child_post_id, $actual['data']['testCpt']['databaseId'] );

		unregister_post_type( 'test_cpt_by_uri' );

	}

	public function testQueryCustomPostTypeByDatabaseId() {

		global $wp_rewrite;

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->init();

		register_post_type(
			'test_cpt_by_uri',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testCpt',
				'graphql_plural_name' => 'testCpts',
				'hierarchical'        => true,
				'public'              => true,
				'taxonomies'          => [ 'category' ],
				'rewrite'             => true,
			]
		);

		flush_rewrite_rules();

		$post_id = $this->factory()->post->create([
			'post_type'   => 'test_cpt_by_uri',
			'post_status' => 'publish',
			'post_title'  => 'Test for QueryCustomPostTypeByDatabaseId',
			'post_author' => $this->admin,
		]);

		$child_post_id = $this->factory()->post->create([
			'post_type'   => 'test_cpt_by_uri',
			'post_status' => 'publish',
			'post_title'  => 'Child Post for QueryCustomPostTypeByDatabaseId',
			'post_author' => $this->admin,
		]);

		WPGraphQL::show_in_graphql();

		$query = '
		query GET_CUSTOM_POSTS( $id: ID! ) {
			testCpt(id: $id idType: DATABASE_ID ) {
				__typename
				databaseId
			}
		}
		';

		// Query a parent (top-level) post by DatabaseId
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $post_id,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['testCpt']['databaseId'] );

		// Query a child post of CPT by ID
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $child_post_id,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $child_post_id, $actual['data']['testCpt']['databaseId'] );

		unregister_post_type( 'test_cpt_by_uri' );

	}

	public function testQueryCustomPostTypeWithSameValueForGraphqlSingleNameAndGraphqlPluralName() {
		register_post_type(
			'test_cpt_by_uri',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testCpt',
				'graphql_plural_name' => 'testCpt',
				'hierarchical'        => true,
				'public'              => true,
				'taxonomies'          => [ 'category' ],
			]
		);

		$post_id = $this->factory()->post->create([
			'post_type'   => 'test_cpt_by_uri',
			'post_status' => 'publish',
			'post_title'  => 'Test for QueryCustomPostTypeWithSameValueForGraphqlSingleNameAndGraphqlPluralName',
			'post_author' => $this->admin,
		]);

		$query = '
		query GET_CUSTOM_POSTS( $id: ID! ) {
			testCpt( id: $id idType: DATABASE_ID ) {
			__typename
			databaseId
			}
			allTestCpt {
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

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $post_id,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['testCpt']['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['allTestCpt']['nodes'][0]['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['allTestCpt']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'test_cpt_by_uri' );
	}

	public function testRegisterPostTypeWithoutRootField() {

		register_post_type( 'non_root', [
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

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'RootQuery',
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		codecept_debug( [ 'names' => $names ] );

		// assert that other common fields are in the rootQuery
		$this->assertContains( 'node', $names );
		$this->assertContains( 'post', $names );

		// assert that the connection field is there
		$this->assertContains( 'nonRoots', $names );

		// but the singular root field is not there
		$this->assertNotContains( 'nonRoot', $names );
	}

	public function testRegisterPostTypeWithoutRootConnection() {

		register_post_type( 'non_root', [
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
		$this->assertContains( 'node', $names );
		$this->assertContains( 'post', $names );

		// assert that the single field is there
		$this->assertContains( 'nonRoot', $names );

		// but the root connection field is not there
		$this->assertNotContains( 'nonRoots', $names );

	}

	/**
	 * @throws Exception
	 */
	public function testRegisterCustomPostTypeWithCustomInterfaces() {

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

		register_post_type( 'custom_interface', [
			'show_in_graphql'     => true,
			'public'              => true,
			'graphql_single_name' => 'CustomInterface',
			'graphql_plural_name' => 'CustomInterfaces',
			'graphql_interfaces'  => [ 'TestInterface' ],
		]);

		$custom_interface_post_id = self::factory()->post->create([
			'post_type'   => 'custom_interface',
			'post_status' => 'publish',
			'post_title'  => 'test',
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
				'id' => $custom_interface_post_id,
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertQuerySuccessful( $actual, [
			$this->expectedField( 'customInterface.__typename', 'CustomInterface' ),
			$this->expectedField( 'customInterface.databaseId', $custom_interface_post_id ),
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

	}

	public function testRegisterCustomPostTypeWithExcludedInterfaces() {
		register_post_type( 'removed_interfaces', [
			'show_in_graphql'            => true,
			'public'                     => true,
			'supports'                   => [ 'title', 'author' ],
			'graphql_single_name'        => 'CustomInterfaceExcluded',
			'graphql_plural_name'        => 'CustomInterfacesExcluded',
			'graphql_exclude_interfaces' => [ 'NodeWithAuthor', 'NodeWithTitle' ],
		]);

		$post_id = self::factory()->post->create([
			'post_type'   => 'removed_interfaces',
			'post_status' => 'publish',
			'post_title'  => 'test',
		]);

		$query = '
		query getCustomInterfaceExcludedPost($id:ID!){
			customInterfaceExcluded( id: $id idType:DATABASE_ID ) {
				authorDatabaseId
				title
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $post_id,
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringStartsWith( 'Cannot query field "authorDatabaseId"', $actual['errors'][0]['message'] );
		$this->assertStringStartsWith( 'Cannot query field "title"', $actual['errors'][1]['message'] );

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
			[ 'name' => 'NodeWithAuthor' ],
			[ 'name' => 'NodeWithTitle' ],
		] );
		$this->assertNotContains( $actual['data']['__type']['fields'], [
			[ 'name' => 'authorDatabaseId' ],
			[ 'name' => 'title' ],
		]);

		// Now, query for the NodeWithAuthor type
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'NodeWithAuthor',
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertNotContains( $actual['data']['__type']['possibleTypes'], [
			[ 'name' => 'CustomInterfaceExcluded' ],
		] );

		// Now, query for the NodeWithAuthor type
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'typeName' => 'NodeWithTitle',
			],
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertNotContains( $actual['data']['__type']['possibleTypes'], [
			[ 'name' => 'CustomInterfaceExcluded' ],
		] );
	}

	public function testRegisterCustomPostTypeWithConnections() {

		register_post_type( 'with_connections', [
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

	}

	public function testRegisterCustomPostTypeWithExcludedConnections() {
		register_post_type( 'missing_connections', [
			'public'                      => true,
			'show_in_graphql'             => true,
			'graphql_single_name'         => 'ExcludedConnection',
			'graphql_plural_name'         => 'ExcludedConnections',
			'supports'                    => [ 'comments', 'revisions' ],
			'graphql_exclude_connections' => [ 'comments', 'revisions' ],
		]);

		$query = '
		{
			excludedConnections {
				nodes {
					__typename
					databaseId
					comments {
						nodes {
							__typename
						}
					}
					revisions {
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
		$this->assertStringStartsWith( 'Cannot query field "comments"', $actual['errors'][0]['message'] );
		$this->assertStringStartsWith( 'Cannot query field "revisions"', $actual['errors'][1]['message'] );
	}

	public function testRegisterCustomPostTypeWithGraphQLKindNoResolver() {

		$this->tester->expectThrowable( \Exception::class, function () {
			register_post_type( 'with_interface_kind', [
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithInterfaceKind',
				'graphql_plural_name' => 'WithInterfaceKinds',
				'graphql_kind'        => 'interface',
			]);

		} );

		$this->tester->expectThrowable( \Exception::class, function () {
			register_post_type( 'with_union_kind', [
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithUnionKind',
				'graphql_plural_name' => 'WithUnionKinds',
				'graphql_kind'        => 'union',
			]);

		} );

		$this->tester->expectThrowable( \Exception::class, function () {
			register_post_type( 'with_union_kind', [
				'public'               => true,
				'show_in_graphql'      => true,
				'graphql_single_name'  => 'WithUnionKind',
				'graphql_plural_name'  => 'WithUnionKinds',
				'graphql_kind'         => 'union',
				'graphql_resolve_type' => $this->resolve_type(),
			]);

		} );
	}

	public function testRegisterCustomPostTypeWithInterfaceKind() {
		register_post_type( 'with_interface_kind', [
			'public'               => true,
			'show_in_graphql'      => true,
			'graphql_single_name'  => 'WithInterfaceKind',
			'graphql_plural_name'  => 'WithInterfaceKinds',
			'graphql_kind'         => 'interface',
			'graphql_resolve_type' => $this->resolve_type(),
		]);

		register_post_type( 'child_type_one', [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeOne',
			'graphql_plural_name' => 'ChildTypeOne',
			'graphql_interfaces'  => [ 'WithInterfaceKind' ],
		]);

		register_post_type( 'child_type_two', [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeTwo',
			'graphql_plural_name' => 'ChildTypeTwo',
			'graphql_interfaces'  => [ 'WithInterfaceKind' ],
		]);

		$cpt_one_id = $this->factory()->post->create([
			'post_type'   => 'child_type_one',
			'post_status' => 'publish',
			'post_title'  => 'Interface child 1',
		]);

		$cpt_two_id = $this->factory()->post->create([
			'post_type'   => 'child_type_two',
			'post_status' => 'publish',
			'post_title'  => 'Interface child 2',
		]);

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
		$this->markTestIncomplete( 'Connection is throwing duplicate fields error' );
		$this->assertEquals( $cpt_one_id, $actual['data']['withInterfaceKinds']['nodes'][0]['databaseId'] );
		$this->assertEquals( $cpt_two_id, $actual['data']['withInterfaceKinds']['nodes'][1]['databaseId'] );
	}

	public function testRegisterCustomPostTypeWithUnionKind() {
		register_post_type( 'with_union_kind', [
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

		register_post_type( 'child_type_one', [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeOne',
			'graphql_plural_name' => 'ChildTypeOne',
		]);

		register_post_type( 'child_type_two', [
			'public'              => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'ChildTypeTwo',
			'graphql_plural_name' => 'ChildTypeTwo',
		]);

		$cpt_one_id = $this->factory()->post->create([
			'post_type'   => 'child_type_one',
			'post_status' => 'publish',
			'post_title'  => 'Union child 1',
		]);

		$cpt_two_id = $this->factory()->post->create([
			'post_type'   => 'child_type_two',
			'post_status' => 'publish',
			'post_title'  => 'Union child 2',
		]);

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
		$this->markTestIncomplete( 'No nodes returned from resolve_type()' );
		$this->assertEquals( $cpt_one_id, $actual['data']['withInterfaceKinds']['nodes'][0]['databaseId'] );
		$this->assertEquals( $cpt_two_id, $actual['data']['withInterfaceKinds']['nodes'][1]['databaseId'] );
	}

	public function resolve_type() {
		return function ( $value ) {
			$type_registry = WPGraphQL::get_type_registry();

			$type = null;
			if ( isset( $value->post_type ) ) {
				$post_type_object = get_post_type_object( $value->post_type );

				if ( isset( $post_type_object->graphql_single_name ) ) {
					$type = $type_registry->get_type( $post_type_object->graphql_single_name );
				}
			}

			return ! empty( $type ) ? $type : null;
		};
	}

}
