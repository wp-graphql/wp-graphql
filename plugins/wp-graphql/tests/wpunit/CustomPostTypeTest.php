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

		$this->post_id = $this->factory()->post->create(
			[
				'post_type'   => 'cpt_test_cpt',
				'post_status' => 'publish',
				'post_title'  => 'Test for CustomPostTypeTest',
			]
		);

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
	}

	public function tearDown(): void {
		unregister_post_type( 'cpt_test_cpt' );
		unregister_taxonomy( 'cpt_test_tax' );
		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * @throws \Exception
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

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $this->post_id,
				],
			]
		);

		// Since the post type was registered as not-public, a public user should
		// not be able to query the content.
		// This asserts that the content is not returned to a public user.
		$this->assertEmpty( $actual['data']['bootstrapPosts']['nodes'] );
		$this->assertEmpty( $actual['data']['bootstrapPosts']['edges'] );
		$this->assertEmpty( $actual['data']['bootstrapPostBy'] );

		// An authenticated user should be able to access the content
		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $this->post_id,
				],
			]
		);

		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPostBy']['bootstrapPostId'] );
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPosts']['nodes'][0]['bootstrapPostId'] );
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPosts']['edges'][0]['node']['bootstrapPostId'] );
	}

	public function testQueryNonPublicPostTypeThatIsPubliclyQueryable() {

		register_post_type(
			'cpt_test_private_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'notPublic',
				'graphql_plural_name' => 'notPublics',
				'public'              => false,
				'publicly_queryable'  => true,
			]
		);

		$database_id = $this->factory()->post->create(
			[
				'post_type'   => 'cpt_test_private_cpt',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryNonPublicPostTypeThatIsPubliclyQueryable',
			]
		);

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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $database_id,
				],
			]
		);

		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		// make sure the query is from a logged in user
		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $database_id,
				],
			]
		);

		// A logged in user should be able to see the data as well!
		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'cpt_test_private_cpt' );
	}

	public function testQueryPublicPostTypeThatIsNotPubliclyQueryable() {

		register_post_type(
			'cpt_test_private_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'notPublic',
				'graphql_plural_name' => 'notPublics',
				'public'              => true,
				'publicly_queryable'  => false,
			]
		);

		$database_id = $this->factory()->post->create(
			[
				'post_type'   => 'cpt_test_private_cpt',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryPublicPostTypeThatIsNotPubliclyQueryable',
			]
		);

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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $database_id,
				],
			]
		);

		// Since the post_type is public we should see data, even if it's set to publicly_queryable=>false, as public=>true should trump publicly_queryable
		$this->assertEquals( $database_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['nodes'][0]['databaseId'] );
		$this->assertEquals( $database_id, $actual['data']['notPublics']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'cpt_test_private_cpt' );
	}

	public function testQueryNonPublicPostTypeThatIsNotPubliclyQueryable() {

		register_post_type(
			'cpt_test_private_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'notPublic',
				'graphql_plural_name' => 'notPublics',
				'public'              => false,
				'publicly_queryable'  => false,
			]
		);

		$database_id = $this->factory()->post->create(
			[
				'post_type'   => 'cpt_test_private_cpt',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryNonPublicPostTypeThatIsNotPubliclyQueryable',
			]
		);

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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $database_id,
				],
			]
		);

		// Since the post_type is public=>false / publicly_queryable=>false, the content should be null for a public user
		$this->assertEmpty( $actual['data']['contentNode'] );
		$this->assertEmpty( $actual['data']['notPublics']['nodes'] );
		$this->assertEmpty( $actual['data']['notPublics']['edges'] );

		// Log the user in and do the request again
		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $database_id,
				],
			]
		);

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

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'test_cpt_by_uri',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryCustomPostTypeByUri',
				'post_author' => $this->admin,
			]
		);

		$child_post_id = $this->factory()->post->create(
			[
				'post_type'   => 'test_cpt_by_uri',
				'post_status' => 'publish',
				'post_title'  => 'Child Post for QueryCustomPostTypeByUri',
				'post_author' => $this->admin,
			]
		);

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
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['testCpt']['databaseId'] );

		$child_uri = get_permalink( $child_post_id );

		// Query a child post of CPT by uri
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $child_uri,
				],
			]
		);

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

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'test_cpt_by_uri',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryCustomPostTypeByDatabaseId',
				'post_author' => $this->admin,
			]
		);

		$child_post_id = $this->factory()->post->create(
			[
				'post_type'   => 'test_cpt_by_uri',
				'post_status' => 'publish',
				'post_title'  => 'Child Post for QueryCustomPostTypeByDatabaseId',
				'post_author' => $this->admin,
			]
		);

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
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['testCpt']['databaseId'] );

		// Query a child post of CPT by ID
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $child_post_id,
				],
			]
		);

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

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'test_cpt_by_uri',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryCustomPostTypeWithSameValueForGraphqlSingleNameAndGraphqlPluralName',
				'post_author' => $this->admin,
			]
		);

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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['testCpt']['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['allTestCpt']['nodes'][0]['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['allTestCpt']['edges'][0]['node']['databaseId'] );

		unregister_post_type( 'test_cpt_by_uri' );
	}

	public function testRegisterPostTypeWithoutRootField() {

		register_post_type(
			'non_root_field',
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

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'RootQuery',
				],
			]
		);

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

		unregister_post_type( 'non_root_field' );
	}

	public function testRegisterPostTypeWithoutRootConnection() {

		register_post_type(
			'non_root_connection',
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
		$this->assertContains( 'node', $names );
		$this->assertContains( 'post', $names );

		// assert that the single field is there
		$this->assertContains( 'nonRoot', $names );

		// but the root connection field is not there
		$this->assertNotContains( 'nonRoots', $names );

		unregister_post_type( 'non_root_connection' );
	}

	/**
	 * @throws \Exception
	 */
	public function testRegisterCustomPostTypeWithCustomInterfaces() {

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

		register_post_type(
			'custom_interface',
			[
				'show_in_graphql'     => true,
				'public'              => true,
				'graphql_single_name' => 'CustomInterface',
				'graphql_plural_name' => 'CustomInterfaces',
				'graphql_interfaces'  => [ 'TestInterface' ],
			]
		);

		$custom_interface_post_id = self::factory()->post->create(
			[
				'post_type'   => 'custom_interface',
				'post_status' => 'publish',
				'post_title'  => 'test',
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
					'id' => $custom_interface_post_id,
				],
			]
		);

		$this->assertResponseIsValid( $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'customInterface.__typename', 'CustomInterface' ),
				$this->expectedField( 'customInterface.databaseId', $custom_interface_post_id ),
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

		unregister_post_type( 'custom_interface' );
	}

	public function testRegisterCustomPostTypeWithExcludedInterfaces() {
		register_post_type(
			'removed_interfaces',
			[
				'show_in_graphql'            => true,
				'public'                     => true,
				'supports'                   => [ 'title', 'author' ],
				'graphql_single_name'        => 'CustomInterfaceExcluded',
				'graphql_plural_name'        => 'CustomInterfacesExcluded',
				'graphql_exclude_interfaces' => [ 'NodeWithAuthor', 'NodeWithTitle' ],
			]
		);

		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'removed_interfaces',
				'post_status' => 'publish',
				'post_title'  => 'test',
			]
		);

		$query = '
		query getCustomInterfaceExcludedPost($id:ID!){
			customInterfaceExcluded( id: $id idType:DATABASE_ID ) {
				authorDatabaseId
				title
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post_id,
				],
			]
		);

		$this->assertResponseIsValid( $actual );
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
				[ 'name' => 'NodeWithAuthor' ],
				[ 'name' => 'NodeWithTitle' ],
			]
		);
		$this->assertNotContains(
			$actual['data']['__type']['fields'],
			[
				[ 'name' => 'authorDatabaseId' ],
				[ 'name' => 'title' ],
			]
		);

		// Now, query for the NodeWithAuthor type
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'NodeWithAuthor',
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

		// Now, query for the NodeWithAuthor type
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'NodeWithTitle',
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

		unregister_post_type( 'removed_interfaces' );
	}

	public function testRegisterCustomPostTypeWithConnections() {

		register_post_type(
			'with_connections',
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

		unregister_post_type( 'with_connections' );
	}

	public function testRegisterCustomPostTypeWithExcludedConnections() {
		register_post_type(
			'missing_connections',
			[
				'public'                      => true,
				'show_in_graphql'             => true,
				'graphql_single_name'         => 'ExcludedConnection',
				'graphql_plural_name'         => 'ExcludedConnections',
				'supports'                    => [ 'comments', 'revisions' ],
				'graphql_exclude_connections' => [ 'comments', 'revisions' ],
			]
		);

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

		unregister_post_type( 'missing_connections' );
	}

	public function testRegisterCustomPostTypeWithGraphQLKindNoResolver() {
		$query = '{
			contentTypes {
				nodes {
					__typename
				}
			}
		}';

		// Test with interface.
		register_post_type(
			'with_interface_kind',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithInterfaceKind',
				'graphql_plural_name' => 'WithInterfaceKinds',
				'graphql_kind'        => 'interface',
			]
		);

		register_post_type(
			'with_union_kind_one',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithUnionKindOne',
				'graphql_plural_name' => 'WithUnionKindOnes',
				'graphql_kind'        => 'union',
			]
		);

		// Test with union, where a resolve type is set, but no graphql_union_types.
		register_post_type(
			'with_union_kind_two',
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
		$this->assertNotEmpty( $actual['data']['contentTypes']['nodes'] );

		$error_object_names = array_map(
			static function ( $obj ) {
				return $obj->name;
			},
			array_column( $actual['extensions']['debug'], 'registered_post_type_object' )
		);

		codecept_debug( $error_object_names );

		$this->assertEquals( 'with_interface_kind', $error_object_names[0] );
		$this->assertEquals( 'with_union_kind_one', $error_object_names[1] );
		$this->assertEquals( 'with_union_kind_two', $error_object_names[2] );

		unregister_post_type( 'with_interface_kind' );
		unregister_post_type( 'with_union_kind_one' );
		unregister_post_type( 'with_union_kind_two' );
	}

	public function testRegisterCustomPostTypeWithInterfaceKind() {
		register_post_type(
			'with_interface_kind',
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
						'resolve' => static function ( $post ) {
							return 'ChildTypeOne' === get_post_meta( $post->databaseId, 'child_type', true );
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
						'resolve' => static function ( $post ) {
							return 'ChildTypeTwo' === get_post_meta( $post->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		$cpt_one_id = $this->factory()->post->create(
			[
				'post_type'   => 'with_interface_kind',
				'post_status' => 'publish',
				'post_title'  => 'Interface child 1',
				'meta_input'  => [
					'child_type' => 'ChildTypeOne',
				],
			]
		);

		$cpt_two_id = $this->factory()->post->create(
			[
				'post_type'   => 'with_interface_kind',
				'post_status' => 'publish',
				'post_title'  => 'Interface child 2',
				'meta_input'  => [
					'child_type' => 'ChildTypeTwo',
				],
			]
		);

		$this->clearSchema();

		$query = '
		{
			withInterfaceKinds {
				nodes {
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

		$this->assertEquals( $cpt_two_id, $actual['data']['withInterfaceKinds']['nodes'][0]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeOne', $actual['data']['withInterfaceKinds']['nodes'][0] );
		$this->assertTrue( $actual['data']['withInterfaceKinds']['nodes'][0]['isTypeTwo'] );

		$this->assertEquals( $cpt_one_id, $actual['data']['withInterfaceKinds']['nodes'][1]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeTwo', $actual['data']['withInterfaceKinds']['nodes'][1] );
		$this->assertTrue( $actual['data']['withInterfaceKinds']['nodes'][1]['isTypeOne'] );

		unregister_post_type( 'with_interface_kind' );
	}

	public function testRegisterCustomPostTypeWithUnionKind() {
		register_post_type(
			'with_union_kind',
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
				'interfaces'      => [ 'ContentNode' ],
				'fields'          => [
					'isTypeOne' => [
						'type'    => 'Boolean',
						'resolve' => static function ( $post ) {
							return 'ChildTypeOne' === get_post_meta( $post->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'ChildTypeTwo',
			[
				'eagerlyLoadType' => true,
				'interfaces'      => [ 'ContentNode' ],
				'fields'          => [
					'isTypeTwo' => [
						'type'    => 'Boolean',
						'resolve' => static function ( $post ) {
							return 'ChildTypeTwo' === get_post_meta( $post->databaseId, 'child_type', true );
						},
					],
				],
			]
		);

		$cpt_one_id = $this->factory()->post->create(
			[
				'post_type'   => 'with_union_kind',
				'post_status' => 'publish',
				'post_title'  => 'Union child 1',
				'meta_input'  => [
					'child_type' => 'ChildTypeOne',
				],
			]
		);

		$cpt_two_id = $this->factory()->post->create(
			[
				'post_type'   => 'with_union_kind',
				'post_status' => 'publish',
				'post_title'  => 'Union child 2',
				'meta_input'  => [
					'child_type' => 'ChildTypeTwo',
				],
			]
		);

		$this->clearSchema();

		$query = '
		{
			withUnionKinds {
				nodes {
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

		$this->assertEquals( $cpt_two_id, $actual['data']['withUnionKinds']['nodes'][0]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeOne', $actual['data']['withUnionKinds']['nodes'][0] );
		$this->assertTrue( $actual['data']['withUnionKinds']['nodes'][0]['isTypeTwo'] );

		$this->assertEquals( $cpt_one_id, $actual['data']['withUnionKinds']['nodes'][1]['databaseId'] );
		$this->assertArrayNotHasKey( 'isTypeTwo', $actual['data']['withUnionKinds']['nodes'][1] );
		$this->assertTrue( $actual['data']['withUnionKinds']['nodes'][1]['isTypeOne'] );

		unregister_post_type( 'with_union_kind' );
	}

	public function resolve_type() {
		return static function ( $value ) {
			$type_registry = WPGraphQL::get_type_registry();

			$type = null;

			$child_type_name = get_post_meta( $value->ID, 'child_type', true );
			if ( ! empty( $child_type_name ) ) {
				$type = $type_registry->get_type( $child_type_name );
			}

			return ! empty( $type ) ? $type : null;
		};
	}

	public function testExcludeCreateMutation() {

		register_post_type(
			'without_create',
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

		unregister_post_type( 'without_create' );
	}

	public function testExcludeDeleteMutation() {

		register_post_type(
			'without_delete',
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

		unregister_post_type( 'without_delete' );
	}

	public function testExcludeUpdateMutation() {

		register_post_type(
			'without_update',
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

		unregister_post_type( 'without_update' );
	}

	public function testRegisterPostTypeWithGraphqlFields() {

		register_post_type(
			'graphql_fields',
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

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'graphql_fields',
				'post_status' => 'publish',
				'post_title'  => 'Test GraphQL Fields',
			]
		);

		$query = '
		{
			graphqlFields {
				nodes {
					id
					databaseId
					title
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
		$this->assertSame( $post_id, $actual['data']['graphqlFields']['nodes'][0]['databaseId'] );

		wp_delete_post( $post_id );
		unregister_post_type( 'graphql_fields' );
	}

	public function testRegisterPostTypeWithGraphqlExcludeFields() {

		register_post_type(
			'gql_exclude_fields',
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

		unregister_post_type( 'gql_exclude_fields' );
	}

	public function testGraphqlSingleNameWithUnderscoresIsAllowed() {

		register_post_type(
			'indicator',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'indicator',
				'graphql_plural_name' => 'indicators',
			]
		);

		register_taxonomy(
			'indicator_category',
			'indicator',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'indicator_category',
				'graphql_plural_name' => 'indicator_categories',
			]
		);

		$query = '
		query GetType( $name: String! ){
			__type(name:$name) {
				fields(includeDeprecated:true) {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'Indicator_category',
				],
			]
		);

		$this->assertNotContains( 'errors', $actual );

		$this->assertNotEmpty( $actual['data']['__type']['fields'] );

		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		codecept_debug( $field_names );

		// the included field that was not excluded should still remain
		$this->assertContains( 'indicator_categoryId', $field_names );

		unregister_post_type( 'indicator' );
		unregister_taxonomy( 'indicator_category' );
	}

	public function testRegisterCustomPostTypeWithUnderscoresInGraphqlNameHasValidSchema() {

			$args = [
				'public'              => true,
				'show_in_rest'        => true,
				'hierarchical'        => true,
				'show_ui'             => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'with_underscore',
				'graphql_plural_name' => 'with_underscore',
				'has_archive'         => true,
				'query_var'           => true,
				'rewrite'             => [ 'slug' => 'casinos' ],
				'supports'            => [ 'editor', 'thumbnail', 'title' ],
				'label'               => __( 'With Underscores' ),
				'map_meta_cap'        => true,
			];

			// register the post type with underscore in the graphql_single_name / graphql_plural_name
			register_post_type( 'with_underscore', $args );

			new \WPGraphQL\Request();

			$schema = WPGraphQL::get_schema();

			// clean up
			unregister_post_type( 'with_underscore' );

			$this->assertTrue( $schema->hasType( 'With_underscore' ) );

			$schema->assertValid();

			// Assert true upon success.
			$this->assertTrue( true );
	}

	public function testRegisterPostTypeWithoutGraphqlPluralNameIsValid() {

		register_post_type(
			'cpt_no_plural',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'noPlural',
			]
		);

		$this->clearSchema();

		$query = '
		{
			allNoPlural {
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

		$request = new \WPGraphQL\Request();
		$schema  = WPGraphQL::get_schema();
		$schema->assertValid();
		$this->assertTrue( $schema->hasType( 'NoPlural' ) );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'allNoPlural.nodes', self::IS_FALSY ),
			]
		);

		// Cleanup.
		unregister_post_type( 'cpt_no_plural' );
		$this->clearSchema();
	}

	public function testRegisterPostTypeWithoutGraphqlSingleOrPluralNameDoesntInvalidateSchema() {

		register_post_type(
			'cpt_no_single_plural',
			[
				'show_in_graphql' => true,
			// no graphql_single_name
			// no graphql_plural_name
			]
		);

		// assert that the schema is still valid, even though the tax
		// didn't provide the single/plural name (it will be left out of the schema)
		new \WPGraphQL\Request();
		$schema = WPGraphQL::get_schema();

		// Cleanup
		unregister_post_type( 'cpt_no_single_plural' );

		$schema->assertValid();

		$map = array_keys( $schema->getTypeMap() );

		$this->assertTrue( in_array( 'BootstrapPost', $map, true ) );
		$this->assertTrue( ! in_array( 'CptNoSinglePlural', $map, true ) );

		// Cleanup
		unregister_post_type( 'cpt_no_single_plural' );
	}

	public function testRegisterPostTypeWithUnderscoresAsGraphqlSingleName() {

		register_post_type(
			'test_events',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'test_event',
				'graphql_plural_name' => 'test_events',
			]
		);

		$query = '
		{
			testEvents {
				nodes {
					__typename
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

		// ensure the query succeeds without error
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'testEvents.nodes', [] ),
			]
		);

		unregister_post_type( 'test_events' );
	}
}
