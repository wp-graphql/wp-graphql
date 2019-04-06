<?php

class TaxonomyObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp() {
		parent::setUp();

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testTaxonomyQueryForCategories
	 *
	 * This tests the category taxonomy.
	 *
	 * @since 0.0.5
	 * @dataProvider dataProviderUserState
	 */
	public function testTaxonomyQueryForCategories( $logged_in ) {
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

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'category' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'categories' => [
					'taxonomyInfo' => [
						'connectedPostTypeNames' => [ 'post' ],
						'connectedPostTypes'     => [ [ 'name' => 'post' ] ],
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
		];

		if ( false === $logged_in ) {
			$expected['data']['categories']['taxonomyInfo']['label'] = null;
			$expected['data']['categories']['taxonomyInfo']['public'] = null;
			$expected['data']['categories']['taxonomyInfo']['restControllerClass'] = null;
			$expected['data']['categories']['taxonomyInfo']['showCloud'] = null;
			$expected['data']['categories']['taxonomyInfo']['showInAdminColumn'] = null;
			$expected['data']['categories']['taxonomyInfo']['showInMenu'] = null;
			$expected['data']['categories']['taxonomyInfo']['showInNavMenus'] = null;
			$expected['data']['categories']['taxonomyInfo']['showInQuickEdit'] = null;
			$expected['data']['categories']['taxonomyInfo']['showInRest'] = null;
			$expected['data']['categories']['taxonomyInfo']['showUi'] = null;
		}

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testTaxonomyQueryForTags
	 *
	 * This tests the post tags taxonomy.
	 *
	 * @since 0.0.5
	 * @dataProvider dataProviderUserState
	 */
	public function testTaxonomyQueryForTags( $logged_in ) {
		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			tags {
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

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'post_tag' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'tags' => [
					'taxonomyInfo' => [
						'connectedPostTypeNames' => [ 'post' ],
						'connectedPostTypes'     => [ [ 'name' => 'post' ] ],
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
		];

		if ( false === $logged_in ) {
			$expected['data']['tags']['taxonomyInfo']['label'] = null;
			$expected['data']['tags']['taxonomyInfo']['public'] = null;
			$expected['data']['tags']['taxonomyInfo']['restControllerClass'] = null;
			$expected['data']['tags']['taxonomyInfo']['showCloud'] = null;
			$expected['data']['tags']['taxonomyInfo']['showInAdminColumn'] = null;
			$expected['data']['tags']['taxonomyInfo']['showInMenu'] = null;
			$expected['data']['tags']['taxonomyInfo']['showInNavMenus'] = null;
			$expected['data']['tags']['taxonomyInfo']['showInQuickEdit'] = null;
			$expected['data']['tags']['taxonomyInfo']['showInRest'] = null;
			$expected['data']['tags']['taxonomyInfo']['showUi'] = null;
		}

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
		$post_id       = $this->factory->post->create();
		$page_id       = $this->factory->post->create( [ 'post_type' => 'page' ] );
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

		$post_tag_id = $this->factory->term->create( [ 'name' => 'Test' ] );

		wp_set_object_terms( $post_id, $post_tag_id, 'post_tag' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			tags {
				taxonomyInfo {
					name
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
			'data' => [
				'tags' => [
					'taxonomyInfo' => [
						'name' => 'post_tag',
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
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