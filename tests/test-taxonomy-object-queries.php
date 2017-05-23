<?php
/**
 * WPGraphQL Test Taxonomy Object Queries
 * This tests taxonomy queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */

/**
 * Class for testing WP GraphQL TaxonomyType.
 */
class WP_GraphQL_Test_Taxonomy_Object_Queries extends WP_UnitTestCase {

	public $admin;

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testTaxonomyQueryForCategories
	 *
	 * This tests the category taxonomy.
	 *
	 * @since 0.0.5
	 */
	public function testTaxonomyQueryForCategories() {
		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			categories {
				taxonomyInfo {
					connectedPostTypeNames
					connectedPostTypes {
						name
					}
					description
					graphqlPluralName
					graphqlSingleName
					hierarchical
					id
					label
					mediaItems {
						edges {
							node {
								id
							}
						}
					}
					name
					pages {
						edges {
							node {
								id
							}
						}
					}
					posts {
						edges {
							node {
								id
							}
						}
					}
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
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'category' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'categories' => [
					'taxonomyInfo' => [
						'connectedPostTypeNames' => [ 'post' ],
						'connectedPostTypes' => [ [ 'name' => 'post'] ],
						'description' => '',
						'graphqlPluralName' => 'categories',
						'graphqlSingleName' => 'category',
						'hierarchical' => true,
						'id' => $global_id,
						'label' => 'Categories',
						'mediaItems' => [ 'edges' => [] ],
						'name' => 'category',
						'pages' => [ 'edges' => [] ],
						'posts' => [ 'edges' => [] ],
						'public' => true,
						'restBase' => 'categories',
						'restControllerClass' => 'WP_REST_Terms_Controller',
						'showCloud' => true,
						'showInAdminColumn' => true,
						'showInGraphql' => 'true',
						'showInMenu' => true,
						'showInNavMenus' => true,
						'showInQuickEdit' => true,
						'showInRest' => true,
						'showUi' => true,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testTaxonomyQueryForTags
	 *
	 * This tests the post tags taxonomy.
	 *
	 * @since 0.0.5
	 */
	public function testTaxonomyQueryForTags() {
		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			postTags {
				taxonomyInfo {
					connectedPostTypeNames
					connectedPostTypes {
						name
					}
					description
					graphqlPluralName
					graphqlSingleName
					hierarchical
					id
					label
					mediaItems {
						edges {
							node {
								id
							}
						}
					}
					name
					pages {
						edges {
							node {
								id
							}
						}
					}
					posts {
						edges {
							node {
								id
							}
						}
					}
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
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'post_tag' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'postTags' => [
					'taxonomyInfo' => [
						'connectedPostTypeNames' => [ 'post' ],
						'connectedPostTypes' => [ [ 'name' => 'post'] ],
						'description' => '',
						'graphqlPluralName' => 'postTags',
						'graphqlSingleName' => 'postTag',
						'hierarchical' => false,
						'id' => $global_id,
						'label' => 'Tags',
						'mediaItems' => [ 'edges' => [] ],
						'name' => 'post_tag',
						'pages' => [ 'edges' => [] ],
						'posts' => [ 'edges' => [] ],
						'public' => true,
						'restBase' => 'tags',
						'restControllerClass' => 'WP_REST_Terms_Controller',
						'showCloud' => true,
						'showInAdminColumn' => true,
						'showInGraphql' => 'true',
						'showInMenu' => true,
						'showInNavMenus' => true,
						'showInQuickEdit' => true,
						'showInRest' => true,
						'showUi' => true,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testTaxonomyQueryCategoryConnections.
	 *
	 * This tests the category taxonomy post object connections.
	 *
	 * @since 0.0.5
	 */
	public function testTaxonomyQueryCategoryConnections() {
		$post_id = $this->factory->post->create();
		$page_id = $this->factory->post->create( [ 'post_type' => 'page' ] );
		$attachment_id = $this->factory->post->create( [ 'post_type' => 'attachment' ] );

		$category_id = $this->factory->term->create( [ 'name' => 'Test' ] );

		wp_set_object_terms( $post_id, $category_id, 'category' );
		wp_set_object_terms( $page_id, $category_id, 'category' );
		wp_set_object_terms( $attachment_id, $category_id, 'category' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			categories {
				taxonomyInfo {
					name
					mediaItems {
						edges {
							node {
								mediaItemId
							}
						}
					}
					pages {
						edges {
							node {
								pageId
							}
						}
					}
					posts {
						edges {
							node {
								postId
							}
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'category' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'categories' => [
					'taxonomyInfo' => [
						'name' => 'category',
						'mediaItems' => [
							'edges' => [
								[
									'node' => [ 'mediaItemId' => $attachment_id ],
								],
							],
						],
						'pages' => [
							'edges' => [
								[
									'node' => [ 'pageId' => $page_id ],
								],
							],
						],
						'posts' => [
							'edges' => [
								[
									'node' => [ 'postId' => $post_id ],
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testTaxonomyQueryTagsConnections.
	 *
	 * This tests the tags taxonomy post object connections.
	 *
	 * @since 0.0.5
	 */
	public function testTaxonomyQueryTagsConnections() {
		$post_id = $this->factory->post->create();
		$page_id = $this->factory->post->create( [ 'post_type' => 'page' ] );
		$attachment_id = $this->factory->post->create( [ 'post_type' => 'attachment' ] );

		$post_tag_id = $this->factory->term->create( [ 'name' => 'Test' ] );

		wp_set_object_terms( $post_id, $post_tag_id, 'post_tag' );
		wp_set_object_terms( $page_id, $post_tag_id, 'post_tag' );
		wp_set_object_terms( $attachment_id, $post_tag_id, 'post_tag' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			postTags {
				taxonomyInfo {
					name
					mediaItems {
						edges {
							node {
								mediaItemId
							}
						}
					}
					pages {
						edges {
							node {
								pageId
							}
						}
					}
					posts {
						edges {
							node {
								postId
							}
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'post_tag' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'postTags' => [
					'taxonomyInfo' => [
						'name' => 'post_tag',
						'mediaItems' => [
							'edges' => [
								[
									'node' => [ 'mediaItemId' => $attachment_id ],
								],
							],
						],
						'pages' => [
							'edges' => [
								[
									'node' => [ 'pageId' => $page_id ],
								],
							],
						],
						'posts' => [
							'edges' => [
								[
									'node' => [ 'postId' => $post_id ],
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}
}
