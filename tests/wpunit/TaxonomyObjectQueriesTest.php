<?php

class TaxonomyObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * testTaxonomyQueryForCategories
	 *
	 * This tests the category taxonomy.
	 *
	 * @since 0.0.5
	 * @param boolean $logged_in Whether the test should be executed as a logged in user
	 * @dataProvider dataProviderUserState
	 * @throws Exception
	 */
	public function testTaxonomyQueryForCategories( $logged_in ) {

		$category_id = $this->factory()->category->create([
			'name' => 'test',
		]);

		$this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'category' => $category_id,
		]);

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			categories(first: 1) {
				nodes {
				  taxonomy {
				    node {
						connectedContentTypes {
							nodes {
								name
							}
						}
						description
						graphqlPluralName
						graphqlSingleName
						hierarchical
						id
						label
						name
						public
						restBase
						restControllerClass
						showCloud
						showInAdminColumn
						showInGraphql
						showInMenu
						showInNavMenus
						showInQuickEdit
						showInRest
						showUi
				     }
				   }
				}
			}
		}";

		if ( true === $logged_in ) {
			$user = $this->admin;
		} else {
			$user = 0;
		}

		wp_set_current_user( $user );

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'category' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'categories' => [
				'nodes' => [
					[
						'taxonomy' => [
							'node' => [
								'connectedContentTypes'     => [
									'nodes' => [
										[
											'name' => 'post'
										]
									]
								],
								'description'            => '',
								'graphqlPluralName'      => 'categories',
								'graphqlSingleName'      => 'category',
								'hierarchical'           => true,
								'id'                     => $global_id,
								'label'                  => 'Categories',
								'name'                   => 'category',
								'public'                 => true,
								'restBase'               => 'categories',
								'restControllerClass'    => 'WP_REST_Terms_Controller',
								'showCloud'              => true,
								'showInAdminColumn'      => true,
								'showInGraphql'          => true,
								'showInMenu'             => true,
								'showInNavMenus'         => true,
								'showInQuickEdit'        => true,
								'showInRest'             => true,
								'showUi'                 => true,
							],
						],
					],
				],
			],
		];

		if ( false === $logged_in ) {
			$expected['categories']['nodes'][0]['taxonomy']['node']['label'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['public'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['restControllerClass'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showCloud'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showInAdminColumn'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showInMenu'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showInNavMenus'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showInQuickEdit'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showInRest'] = null;
			$expected['categories']['nodes'][0]['taxonomy']['node']['showUi'] = null;
		}

		codecept_debug( $actual );

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testTaxonomyQueryForTags
	 *
	 * This tests the post tags taxonomy.
	 *
	 * @since 0.0.5
	 * @param boolean $logged_in
	 * @dataProvider dataProviderUserState
	 * @throws Exception
	 */
	public function testTaxonomyQueryForTags( $logged_in ) {

		$tag_id = $this->factory()->tag->create([
			'name' => 'test'
		]);

		$this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_tag' => $tag_id
		]);

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			tags(first:1) {
				nodes {
				 taxonomy {
				   node {
					connectedContentTypes {
						nodes {
							name
						}
					}
					description
					graphqlPluralName
					graphqlSingleName
					hierarchical
					id
					label
					name
					public
					restBase
					restControllerClass
					showCloud
					showInAdminColumn
					showInGraphql
					showInMenu
					showInNavMenus
					showInQuickEdit
					showInRest
					showUi
					}
				  }
				}
			}
		}";

		if ( true === $logged_in ) {
			$user = $this->admin;
		} else {
			$user = 0;
		}

		wp_set_current_user( $user );


		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'post_tag' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'tags' => [
				'nodes' => [
					[
						'taxonomy' => [
							'node' => [
								'connectedContentTypes' => [
									'nodes' => [
										[
											'name' => 'post'
										]
									]
								],
								'description'            => '',
								'graphqlPluralName'      => 'tags',
								'graphqlSingleName'      => 'tag',
								'hierarchical'           => false,
								'id'                     => $global_id,
								'label'                  => 'Tags',
								'name'                   => 'post_tag',
								'public'                 => true,
								'restBase'               => 'tags',
								'restControllerClass'    => 'WP_REST_Terms_Controller',
								'showCloud'              => true,
								'showInAdminColumn'      => true,
								'showInGraphql'          => true,
								'showInMenu'             => true,
								'showInNavMenus'         => true,
								'showInQuickEdit'        => true,
								'showInRest'             => true,
								'showUi'                 => true,
							],
						],
					],
				],
			],
		];

		if ( false === $logged_in ) {
			$expected['tags']['nodes'][0]['taxonomy']['node']['label'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['public'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['restControllerClass'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showCloud'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showInAdminColumn'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showInMenu'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showInNavMenus'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showInQuickEdit'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showInRest'] = null;
			$expected['tags']['nodes'][0]['taxonomy']['node']['showUi'] = null;
		}

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testTaxonomyQueryCategoryConnections.
	 *
	 * This tests the category taxonomy post object connections.
	 *
	 * @since 0.0.5
	 * @throws Exception
	 */
	public function testTaxonomyQueryCategoryConnections() {
		$post_id       = $this->factory()->post->create();
		$page_id       = $this->factory()->post->create( [ 'post_type' => 'page' ] );
		$attachment_id = $this->factory()->post->create( [ 'post_type' => 'attachment' ] );

		$category_id = $this->factory()->term->create( [ 'name' => 'Test' ] );

		wp_set_object_terms( $post_id, $category_id, 'category' );
		wp_set_object_terms( $page_id, $category_id, 'category' );
		wp_set_object_terms( $attachment_id, $category_id, 'category' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			categories(first:1) {
				nodes {
				  taxonomy {
				    node {
					  name
					}
			      }
			    }
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'categories' => [
				'nodes' => [
					[
						'taxonomy' => [
							'node' => [
								'name' => 'category',
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testTaxonomyQueryTagsConnections.
	 *
	 * This tests the tags taxonomy post object connections.
	 *
	 * @since 0.0.5
	 * @throws Exception
	 */
	public function testTaxonomyQueryTagsConnections() {
		$post_id = $this->factory()->post->create();

		$post_tag_id = $this->factory()->term->create( [ 'name' => 'Test' ] );

		wp_set_object_terms( $post_id, $post_tag_id, 'post_tag' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			tags(first:1)
			 {
			  nodes {
			     taxonomy {
			         node {
					   name
				     }
				   }
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'tags' => [
				'nodes' => [
					[
						'taxonomy' => [
							'node' => [
								'name' => 'post_tag',
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testTaxonomyQueryPostConnections.
	 *
	 * This tests whether the taxonomy to post object connections only returns
	 *  posts registered with the taxonomy.
	 *
	 * @since 0.0.10
	 * @throws Exception
	 */
	public function testTaxonomyQueryPostConnections() {
		$post_id           = $this->factory()->post->create();
		$unrelated_post_id = $this->factory()->post->create();
		$term_id = $this->factory()->term->create( [ 'name' => 'Test' ] );

		wp_set_object_terms( $post_id, $term_id, 'post_tag' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			tags(first:2) {
				nodes {
					posts {
						nodes {
							databaseId
						}
					}
			  }
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'tags' => [
				'nodes' => [
					[
						'posts' => [
							'nodes' => [
								[
									'databaseId' => $post_id,
								]
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

		$unexpected = [
			'tags' => [
				'nodes' => [
					[
						'posts' => [
							'nodes' => [
								[
									'databaseId' => $unrelated_post_id,
								]
							],
						],
					],
				],
			],
		];
		$this->assertNotEquals( $unexpected, $actual['data'] );

	}

	public function dataProviderUserState() {
		return [
			[
				'logged_in' => true,
			],
			[
				'logged_in' => false,
			]
		];
	}

}
