<?php

class TermObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		global $wp_rewrite;
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->init();
		flush_rewrite_rules();
		WPGraphQL::show_in_graphql();
	}

	public function tearDown(): void {
		parent::tearDown();

	}

	public function createTermObject( $args = [] ) {
		return $this->factory()->term->create( $args );
	}

	public function testTermObjectConnectionQuery() {

		$term_id1 = $this->createTermObject( [
			'name'        => 'AAA1 Term',
			'taxonomy'    => 'category',
			'description' => 'just a description',
		] );

		$term_id2 = $this->createTermObject( [
			'name'        => 'AAA2 Term',
			'taxonomy'    => 'category',
			'description' => 'just a description',
		] );

		$term_id3 = $this->createTermObject( [
			'name'        => 'AAA3 Term',
			'taxonomy'    => 'category',
			'description' => 'just a description',
		] );

		$query = '
		{
		  categories(where:{hideEmpty:false}) {
		    edges {
		      node {
		        id
		        categoryId
		        name
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		$this->assertNotEmpty( $actual['data']['categories']['edges'][0]['node'] );
		$this->assertNotEmpty( $actual['data']['categories']['edges'][0]['node']['categoryId'], $term_id1 );

		$query = '
		query getCategoriesBefore($beforeCursor:String){
			categories(last:1 before:$beforeCursor where:{hideEmpty:false}){
			  edges{
			    node{
			      id
			      categoryId
			      name
			    }
			  }
			}
		}
		';

		/**
		 * Create a cursor for the first term ID
		 */
		$cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $term_id2 );

		/**
		 * Use the cursor in our variables
		 */
		$variables = wp_json_encode( [
			'beforeCursor' => $cursor,
		] );

		/**
		 * Do the request
		 */
		$actual = do_graphql_request( $query, 'getCategoriesBefore', $variables );

		/**
		 * Assert that we should have received just 1 node, $term_id2
		 */
		$this->assertCount( 1, $actual['data']['categories']['edges'] );
		$this->assertEquals( $actual['data']['categories']['edges'][0]['node']['categoryId'], $term_id1 );

		$query = '
		query getCategoriesAfter($afterCursor:String){
			categories(first:1 after:$afterCursor where:{hideEmpty:false}){
			  edges{
			    node{
			      id
			      categoryId
			      name
			    }
			  }
			}
		}
		';

		/**
		 * Create a cursor for the first term ID
		 */
		$cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $term_id2 );

		/**
		 * Use the cursor in our variables
		 */
		$variables = wp_json_encode( [
			'afterCursor' => $cursor,
		] );

		/**
		 * Do the request
		 */
		$actual = do_graphql_request( $query, 'getCategoriesAfter', $variables );

		/**
		 * Assert that we should have received just 1 node, $term_id2
		 */
		$this->assertCount( 1, $actual['data']['categories']['edges'] );
		$this->assertEquals( $actual['data']['categories']['edges'][0]['node']['categoryId'], $term_id3 );

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
			'name'        => 'A Category',
			'taxonomy'    => 'category',
			'description' => 'just a description',
		] );

		$taxonomy = 'category';

		/**
		 * Create the global ID based on the term_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'term', $term_id );

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
					node {
						name
					}
				}
				termGroupId
				termTaxonomyId
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );


		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'category' => [
				'categoryId'     => $term_id,
				'count'          => null,
				'description'    => 'just a description',
				'id'             => $global_id,
				'link'           => get_term_link( $term_id ),
				'name'           => 'A Category',
				'posts'          => [
					'edges' => [],
				],
				'slug'           => 'a-category',
				'taxonomy'       => [
					'node' => [
						'name' => 'category',
					],
				],
				'termGroupId'    => null,
				'termTaxonomyId' => $term_id,
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

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
		$term_id = $this->createTermObject( [ 'name' => uniqid(), 'taxonomy' => 'category' ] );

		// Create a comment and assign it to term.
		$post_id  = $this->factory()->post->create( [
			'post_type' => 'post' ,
			'post_title' => uniqid(),
			'post_status' => 'publish',
		] );

		codecept_debug( $post_id );
		$page_id  = $this->factory()->post->create( [
			'post_type' => 'page',
			'post_title' => uniqid(),
			'post_status' => 'publish',
		] );
		$media_id = $this->factory()->post->create( [
			'post_type' => 'attachment',
			'post_title' => uniqid(),
			'post_status' => 'publish',
		] );

		wp_set_object_terms( $post_id, $term_id, 'category', false );
		wp_set_object_terms( $page_id, $term_id, 'category', false );
		wp_set_object_terms( $media_id, $term_id, 'category', false );

		$taxonomy = 'category';

		/**
		 * Create the global ID based on the term_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'term', $term_id );

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

		codecept_debug( $actual );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
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
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testTermQueryWithChildTerm
	 *
	 * Tests query for term children
	 *
	 * @since 0.2.2
	 */
	public function testTermQueryWithChildTerm() {

		$parent_id = $this->createTermObject( [
			'name'     => 'Parent Category',
			'taxonomy' => 'category',
		] );

		$child_id = $this->createTermObject( [
			'name'     => 'Child category',
			'taxonomy' => 'category',
			'parent'   => $parent_id,
		] );

		$global_parent_id = \GraphQLRelay\Relay::toGlobalId( 'term', $parent_id );
		$global_child_id  = \GraphQLRelay\Relay::toGlobalId( 'term', $child_id );

		$query = "
		query {
			category(id: \"{$global_parent_id}\") {
				id
				categoryId
				children {
					nodes {
						id
						categoryId
					}
				}
			}
		}
		";

		$actual = do_graphql_request( $query );

		$expected = [
			'category' => [
				'id'         => $global_parent_id,
				'categoryId' => $parent_id,
				'children'   => [
					'nodes' => [
						[
							'id'         => $global_child_id,
							'categoryId' => $child_id,
						],
					],
				],
			]
		];

		$this->assertEquals( $expected, $actual['data'] );

	}

	/**
	 * testTermQueryWithParentTerm
	 *
	 * Tests query for term ancestors
	 *
	 */
	public function testTermQueryWithParentTerm() {

		$parent_id = $this->createTermObject( [
			'name'     => 'Parent Category',
			'taxonomy' => 'category',
		] );

		$child_id = $this->createTermObject( [
			'name'     => 'Child category',
			'taxonomy' => 'category',
			'parent'   => $parent_id,
		] );

		$global_parent_id = \GraphQLRelay\Relay::toGlobalId( 'term', $parent_id );
		$global_child_id  = \GraphQLRelay\Relay::toGlobalId( 'term', $child_id );

		$query = "
		query {
			category(id: \"{$global_child_id}\") {
				id
				categoryId
				parent {
				  node {
				    id
				  }
				}
				ancestors {
				  nodes {
					  id
					  categoryId
					}
				}
			}
		}
		";

		$actual = do_graphql_request( $query );

		$expected = [
			'category' => [
				'id'         => $global_child_id,
				'categoryId' => $child_id,
				'parent' => [
					'node' => [
						'id' => $global_parent_id,
					]
				],
				'ancestors'  => [
					'nodes' => [
						[
							'id'         => $global_parent_id,
							'categoryId' => $parent_id,
						],
					]
				],
			],
		];


		$this->assertEquals( $expected, $actual['data'] );

	}

	/**
	 * testTermQueryWhereTermDoesNotExist
	 *
	 * Tests a query for non existent term.
	 *
	 * @since 0.0.5
	 */
	public function testTermQueryWhereTermDoesNotExist() {

		/**
		 * Create the global ID based on the term_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'term', 'doesNotExist' );

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
		$expected_errors = [
			[
				'message'   => 'The ID input is invalid',
				'locations' => [
					[
						'line'   => 3,
						'column' => 4,
					],
				],
				'path'      => [
					'category',
				],
				'extensions' => [
					'category' => 'user'
				],
			],
		];

		$this->assertEquals( $expected_errors, $actual['errors'] );
	}

	public function testQueryChildCategoryByUri() {

		$parent_id = $this->factory()->category->create([
			'name' => 'parent'
		]);

		$child_id = $this->factory()->category->create([
			'name' => 'child',
			'parent' => $parent_id
		]);

		codecept_debug( get_term_link( $child_id, 'category' ) );

		$query = '
		query CategoryByUri($uri: String!) {
		  nodeByUri( uri: $uri ) {
		    __typename
		    id
		    ...on Category {
		      name
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_term_link( $child_id ),
			],
		]);
		codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 'Category', $actual['data']['nodeByUri']['__typename'] );
		$this->assertEquals( 'child', $actual['data']['nodeByUri']['name'] );


	}

	public function testCustomTaxonomyChildTermQueryByUri() {

		register_taxonomy( 'news', 'post', [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'NewsCategory',
			'graphql_plural_name' => 'NewsCategories',
			'rewrite' => true,
		]);

		flush_rewrite_rules();

		WPGraphQL::clear_schema();

		$parent_id = $this->factory()->term->create([
			'taxonomy' => 'news',
			'name' => 'parent',
		]);

		$child_id = $this->factory()->term->create([
			'taxonomy' => 'news',
			'name' => 'child',
			'parent' => $parent_id,
		]);

		$post_id = $this->factory()->post->create([
			'post_type' => 'news',
			'post_status' => 'publish',
			'post_title' => 'Test News Post',
		]);

		wp_set_object_terms( $post_id, [ $child_id ], 'news' );

		$link = get_term_link( $child_id, 'news' );

		codecept_debug( $link );

		$query = '
		query getNewsTerm($uri_string:String! $uri_id: ID! ) {
		  nodeByUri(uri: $uri_string) {
		    id
		    ...NewsCategory
		  }
		  newsCategory(id:$uri_id idType: URI ) {
		    ...NewsCategory
		  }
		}
		fragment NewsCategory on NewsCategory {
		    id
		    databaseId
		    uri
		    link
		    name
			posts {
		      nodes {
		        title
		      }
		    }
		  }
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri_string' => $link,
				'uri_id' => $link,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $link, $actual['data']['nodeByUri']['link'] );
		$this->assertSame( $link, $actual['data']['newsCategory']['link'] );

	}

}
