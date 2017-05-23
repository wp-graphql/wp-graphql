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
				mediaItems {
					edges {
						node {
							mediaItemId
						}
					}
				}
				name
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
					'mediaItems' => [
						'edges' => [],
					],
					'name' => 'A Category',
					'pages' => [
						'edges' => [],
					],
					'posts' => [
						'edges' => [],
					],
					'slug' => 'a-category',
					'taxonomy' => [
						'name' => 'category'
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
				pages {
					edges {
						node {
							pageId
						}
					}
				}
				mediaItems {
					edges {
						node {
							mediaItemId
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
					'pages' => [
						'edges' => [
							[
								'node' => [
									'pageId' => $page_id,
								],
							],
						],
					],
					'posts' => [
						'edges' => [
							[
								'node' => [
									'postId' => $post_id,
								],
							],
						],
					],
					'mediaItems' => [
						'edges' => [
							[
								'node' => [
									'mediaItemId' => $media_id,
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
