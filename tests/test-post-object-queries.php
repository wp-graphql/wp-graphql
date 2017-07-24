<?php

/**
 * WPGraphQL Test Post Object Queries
 * This tests post queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_Post_Object_Queries extends WP_UnitTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
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

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Title',
			'post_type'    => 'post',
			'post_date'    => $this->current_date,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;

	}

	/**
	 * testPostQuery
	 *
	 * This tests creating a single post with data and retrieving said post via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testPostQuery() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject( [
			'post_type' => 'post',
		] );

		/**
		 * Create a featured image and attach it to the post
		 */
		$featured_image_id = $this->createPostObject( [
			'post_type' => 'attachment',
		] );
		update_post_meta( $post_id, '_thumbnail_id', $featured_image_id );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				author{
					userId
				}
				commentCount
				commentStatus
				content
				date
				dateGmt
				desiredSlug
				editLast{
					userId
				}
				editLock{
					editTime
					user{
						userId
					}
				}
				enclosure
				excerpt
				status
				link
				menuOrder
				postId
				slug
				toPing
				pinged
				modified
				modifiedGmt
				title
				guid
				featuredImage{
					mediaItemId
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
				'post' => [
					'id' => $global_id,
					'author' => [
						'userId' => $this->admin,
					],
					'commentCount' => null,
					'commentStatus' => 'open',
					'content' => apply_filters( 'the_content', 'Test page content' ),
					'date' => $this->current_date,
					'dateGmt' => $this->current_date_gmt,
					'desiredSlug' => null,
					'editLast' => [
						'userId' => $this->admin,
					],
					'editLock' => [
						'editTime' => $this->current_date,
						'user' => [
							'userId' => $this->admin,
						],
					],
					'enclosure' => null,
					'excerpt' => apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', 'Test excerpt' ) ),
					'status' => 'publish',
					'link' => get_permalink( $post_id ),
					'menuOrder' => null,
					'postId' => $post_id,
					'slug' => 'test-title',
					'toPing' => null,
					'pinged' => null,
					'modified' => get_post( $post_id )->post_modified,
					'modifiedGmt' => get_post( $post_id )->post_modified_gmt,
					'title' => 'Test Title',
					'guid' => get_post( $post_id )->guid,
					'featuredImage' => [
						'mediaItemId' => $featured_image_id,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostQueryWithComments
	 *
	 * This tests creating a single post with comments.
	 *
	 * @since 0.0.5
	 */
	public function testPostQueryWithComments() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject( [
			'post_type' => 'post',
		] );

		// Create a comment and assign it to post.
		$comment_id = $this->factory->comment->create( [
			'comment_post_ID' => $post_id,
		] );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				commentCount
				commentStatus
				comments {
					edges {
						node {
							commentId
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
				'post' => [
					'id' => $global_id,
					'comments' => [
						'edges' => [
							[
								'node' => [
									'commentId' => $comment_id,
								],
							],
						],
					],
					'commentCount' => 1,
					'commentStatus' => 'open',
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPageQueryWithParent
	 *
	 * This tests a hierarchical post type assigned a parent.
	 *
	 * @since 0.0.5
	 */
	public function testPageQueryWithParent() {

		// Parent post.
		$parent_id = $this->createPostObject( [
			'post_type' => 'page',
		] );

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject( [
			'post_type' => 'page', 'post_parent' => $parent_id,
		] );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			page(id: \"{$global_id}\") {
				id
				parent {
					... on page {
						pageId
					}
				}
				ancestors {
					... on page {
						pageId
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
				'page' => [
					'id' => $global_id,
					'parent' => [
						'pageId' => $parent_id,
					],
					'ancestors' => [
						[
							'pageId' => $parent_id,
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostQueryWithTags
	 *
	 * This tests creating a single post with assigned post tags.
	 *
	 * @since 0.0.5
	 */
	public function testPostQueryWithTags() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject( [
			'post_type' => 'post',
		] );

		// Create a comment and assign it to post.
		$tag_id = $this->factory->tag->create( [
			'name' => 'Test Tag',
		] );

		wp_delete_object_term_relationships( $post_id, [ 'post_tag', 'category' ] );
		wp_set_object_terms( $post_id, $tag_id, 'post_tag', true );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				tags {
					edges {
						node {
							tagId
							name
						}
					}
				}
				tagNames:termNames(taxonomy:TAG)
				terms{
				  ...on tag{
				    name
				  }
				}
				termNames
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
				'post' => [
					'id' => $global_id,
					'tags' => [
						'edges' => [
							[
								'node' => [
									'tagId' => $tag_id,
									'name' => 'Test Tag',
								],
							],
						],
					],
					'tagNames' => [ 'Test Tag' ],
					'terms' => [
						[
							'name' => 'Test Tag',
						],
					],
					'termNames' => [ 'Test Tag' ],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostQueryWithCategories
	 *
	 * This tests creating a single post with categories assigned.
	 *
	 * @since 0.0.5
	 */
	public function testPostQueryWithCategories() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject( [
			'post_type' => 'post',
		] );

		// Create a comment and assign it to post.
		$category_id = $this->factory->category->create( [
			'name' => 'A category',
		] );

		wp_set_object_terms( $post_id, $category_id, 'category' );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				categories {
					edges {
						node {
							categoryId
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
			'data' => [
				'post' => [
					'id' => $global_id,
					'categories' => [
						'edges' => [
							[
								'node' => [
									'categoryId' => $category_id,
									'name' => 'A category',
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
