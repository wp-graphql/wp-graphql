<?php
/**
 * WPGraphQL Test Term Object Queries
 * This tests term queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */

/**
 * Tests term object queries.
 */
class WP_GraphQL_Test_Term_Object_Queries extends WP_UnitTestCase {
	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function createTermObject( $args = [] ) {
		/**
		 * Create the term
		 */
		$term_id = $this->factory->term->create( $args );

		/**
		 * Return the $id of the term_object that was created
		 */
		return $term_id;

	}

	/**
	 * testTermQuery
	 *
	 * This tests creating a single term with data and retrieving said term via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testTermQuery() {

		/**
		 * Create a term
		 */
		$term_id = $this->createTermObject( [
			'name'     => 'A Category',
			'taxonomy' => 'category',
			'description' => 'just a description',
		] );

		$taxonomy = 'category';

		/**
		 * Create the global ID based on the term_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( $taxonomy, $term_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			category(id: \"{$global_id}\") {
				categoryId
				count
				description
				id
				link
				name
				posts {
					edges {
						node {
							postId
						}
					}
				}
				slug
				taxonomy {
					name
				}
				termGroupId
				termTaxonomyId
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
				'category' => [
					'categoryId' => $term_id,
					'count' => null,
					'description' => 'just a description',
					'id' => $global_id,
					'link' => "http://example.org/?cat={$term_id}",
					'name' => 'A Category',
					'posts' => [
						'edges' => [],
					],
					'slug' => 'a-category',
					'taxonomy' => [
						'name' => 'category',
					],
					'termGroupId' => null,
					'termTaxonomyId' => $term_id,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testTermQueryWithAssociatedPostObjects
	 *
	 * Tests a term with associated post objects.
	 *
	 * @since 0.0.5
	 */
	public function testTermQueryWithAssociatedPostObjects() {

		/**
		 * Create a term
		 */
		$term_id = $this->createTermObject( [ 'name' => 'A category', 'taxonomy' => 'category' ] );

		// Create a comment and assign it to term.
		$post_id = $this->factory->post->create( [ 'post_type' => 'post' ] );
		$page_id = $this->factory->post->create( [ 'post_type' => 'page'] );
		$media_id = $this->factory->post->create( [ 'post_type' => 'attachment'] );

		wp_set_object_terms( $post_id, $term_id, 'category' );
		wp_set_object_terms( $page_id, $term_id, 'category' );
		wp_set_object_terms( $media_id, $term_id, 'category' );

		$taxonomy = 'category';

		/**
		 * Create the global ID based on the term_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( $taxonomy, $term_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			category(id: \"{$global_id}\") {
				posts {
					edges {
						node {
							postId
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
			'data' => [
				'category' => [
					'posts' => [
						'edges' => [
							[
								'node' => [
									'postId' => $post_id,
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	public function testTermQueryWithParentTerm() {

		$parent_id = $this->createTermObject( [
			'name' => 'Parent Category',
			'taxonomy' => 'category',
		] );

		$child_id = $this->createTermObject( [
			'name' => 'Child category',
			'taxonomy' => 'category',
			'parent' => $parent_id,
		] );

		$global_parent_id = \GraphQLRelay\Relay::toGlobalId( 'category', $parent_id );
		$global_child_id = \GraphQLRelay\Relay::toGlobalId( 'category', $child_id );

		$query = "
		query {
			category(id: \"{$global_child_id}\"){
				id
				categoryId
				ancestors{
					id
					categoryId
				}
			}
		}
		";

		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'category' => [
					'id' => $global_child_id,
					'categoryId' => $child_id,
					'ancestors' => [
						[
							'id' => $global_parent_id,
							'categoryId' => $parent_id,
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * testTermQueryWhereTermDoesNotExist
	 *
	 * Tests a query for non existant term.
	 *
	 * @since 0.0.5
	 */
	public function testTermQueryWhereTermDoesNotExist() {
		$taxonomy = 'category';

		/**
		 * Create the global ID based on the term_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( $taxonomy, 'doesNotExist' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			category(id: \"{$global_id}\") {
				categoryId
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
				'category' => null,
			],
			'errors' => [
				[
					'message' => 'No category was found with the ID: doesNotExist',
					'locations' => [
						[
							'line' => 3,
							'column' => 4,
						],
					],
					'path' => [
						'category',
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}
}
