<?php

class CustomPostTypeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'id' => $child_uri,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $child_post_id, $actual['data']['testCpt']['databaseId'] );

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
	}

}
