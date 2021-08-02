<?php

class CustomPostTypeTest extends \Codeception\TestCase\WPTestCase {

	public $post_id;
	public $admin;

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();

		$this->post_id = $this->factory()->post->create([
			'post_type' => 'bootstrap_cpt',
			'post_status' => 'publish',
			'post_title' => 'Test'
		]);

		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);

	}

	public function tearDown(): void {
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
			'query' => $query,
			'variables' => [
				'id' => $this->post_id
			]
		]);

		codecept_debug( $actual );
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPostBy']['bootstrapPostId']);
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPosts']['nodes'][0]['bootstrapPostId']);
		$this->assertEquals( $this->post_id, $actual['data']['bootstrapPosts']['edges'][0]['node']['bootstrapPostId']);

	}

	public function testQueryCustomPostTypeByUri() {

		global $wp_rewrite;

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->init();


		register_post_type(
			'test_cpt',
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
			'post_type' => 'test_cpt',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_author' => $this->admin
		]);

		$child_post_id = $this->factory()->post->create([
			'post_type' => 'test_cpt',
			'post_status' => 'publish',
			'post_title' => 'Child Post',
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
		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $uri
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['testCpt']['databaseId'] );

		$child_uri = get_permalink( $child_post_id );

		// Query a child post of CPT by uri
		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $child_uri
			]
		]);

		codecept_debug( $actual );

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
			'test_cpt',
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
			'post_type' => 'test_cpt',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_author' => $this->admin
		]);

		$child_post_id = $this->factory()->post->create([
			'post_type' => 'test_cpt',
			'post_status' => 'publish',
			'post_title' => 'Child Post',
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

		$uri = get_permalink( $post_id );

		codecept_debug( $uri );

		// Query a parent (top-level) post by URI
		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $post_id
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['testCpt']['databaseId'] );

		$child_uri = get_permalink( $child_post_id );

		// Query a child post of CPT by uri
		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $child_post_id
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertSame( $child_post_id, $actual['data']['testCpt']['databaseId'] );

	}

	public function testQueryCustomPostTypeWithSameValueForGraphqlSingleNameAndGraphqlPluralName() {
		register_post_type(
			'test_cpt',
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
			'post_type' => 'test_cpt',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_author' => $this->admin
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

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $post_id
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 'TestCpt', $actual['data']['testCpt']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['testCpt']['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['allTestCpt']['nodes'][0]['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['allTestCpt']['edges'][0]['node']['databaseId'] );
	}

}
