<?php

/**
 * WPGraphQL Test PostType Object Queries
 * This tests postType queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_PostType_Object_Queries extends WP_UnitTestCase {

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

	/**
	 * testPostTypeQueryForPosts
	 *
	 * This tests post type info for posts post type.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForPosts() {
		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post_type', 'post' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			posts {
				postTypeInfo {
					canExport
					categories {
						edges {
							node {
								name
							}
						}
					}
					connectedTaxonomies {
						name
					}
					connectedTaxonomyNames
					deleteWithUser
					description
					excludeFromSearch
					graphqlPluralName
					graphqlSingleName
					hasArchive
					hierarchical
					id
					label
					labels {
						name
						singularName
						addNew
						addNewItem
						editItem
						newItem
						viewItem
						viewItems
						searchItems
						notFound
						notFoundInTrash
						parentItemColon
						allItems
						archives
						attributes
						insertIntoItem
						uploadedToThisItem
						featuredImage
						setFeaturedImage
						removeFeaturedImage
						useFeaturedImage
						menuName
						filterItemsList
						itemsListNavigation
						itemsList
					}
					menuIcon
					menuPosition
					name
					tags {
						edges {
							node {
								name
							}
						}
					}
					public
					publiclyQueryable
					restBase
					restControllerClass
					showInAdminBar
					showInGraphql
					showInMenu
					showInNavMenus
					showInRest
					showUi
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
				'posts' => [
					'postTypeInfo' => [
						'canExport' => true,
						'categories' => [
							'edges' => [],
						],
						'connectedTaxonomies' => [
							[
								'name' => 'category'
							],
							[
								'name' => 'post_tag'
							],
						],
						'connectedTaxonomyNames' => [ 'category', 'post_tag' ],
						'deleteWithUser' => true,
						'description' => '',
						'excludeFromSearch' => false,
						'graphqlPluralName' => 'posts',
						'graphqlSingleName' => 'post',
						'hasArchive' => null,
						'hierarchical' => false,
						'id' => $global_id,
						'label' => 'Posts',
						'labels' => [
							'name' => 'Posts',
							'singularName' => 'Post',
							'addNew' => 'Add New',
							'addNewItem' => 'Add New Post',
							'editItem' => 'Edit Post',
							'newItem' => 'New Post',
							'viewItem' => 'View Post',
							'viewItems' => 'View Posts',
							'searchItems' => 'Search Posts',
							'notFound' => 'No posts found.',
							'notFoundInTrash' => 'No posts found in Trash.',
							'parentItemColon' => null,
							'allItems' => 'All Posts',
							'archives' => 'Post Archives',
							'attributes' => 'Post Attributes',
							'insertIntoItem' => 'Insert into post',
							'uploadedToThisItem' => 'Uploaded to this post',
							'featuredImage' => 'Featured Image',
							'setFeaturedImage' => 'Set featured image',
							'removeFeaturedImage' => 'Remove featured image',
							'useFeaturedImage' => null,
							'menuName' => 'Posts',
							'filterItemsList' => 'Filter posts list',
							'itemsListNavigation' => 'Posts list navigation',
							'itemsList' => 'Posts list',
						],
						'menuIcon' => null,
						'menuPosition' => 5,
						'name' => 'post',
						'tags' => [
							'edges' => [],
						],
						'public' => true,
						'publiclyQueryable' => true,
						'restBase' => 'posts',
						'restControllerClass' => 'WP_REST_Posts_Controller',
						'showInAdminBar' => true,
						'showInGraphql' => 'true',
						'showInMenu' => true,
						'showInNavMenus' => true,
						'showInRest' => true,
						'showUi' => true,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostTypeQueryForPostConnections
	 *
	 * This tests connections for the post post type.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForPostConnections() {

		/**
		 * Create a post
		 */
		$post_id = $this->factory->post->create();

		// Create a comment and assign it to postType.
		$tag_id = $this->factory->tag->create( [ 'name' => 'A tag' ] );
		$category_id = $this->factory->category->create( [ 'name' => 'A category' ] );

		wp_set_object_terms( $post_id, $tag_id, 'post_tag' );
		wp_set_object_terms( $post_id, $category_id, 'category' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			posts {
				postTypeInfo {
					tags {
						edges {
							node {
								tagId
								name
							}
						}
					}
					categories {
						edges {
							node {
								categoryId
								name
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

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'posts' => [
					'postTypeInfo' => [
						'tags' => [
							'edges' => [
								[
									'node' => [
										'tagId' => $tag_id,
										'name' => 'A tag',
									],
								],
							],
						],
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
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostTypeQueryForPages
	 *
	 * This tests post type info for pages post type.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForPages() {
		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post_type', 'page' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			pages {
				postTypeInfo {
					canExport
					categories {
						edges {
							node {
								name
							}
						}
					}
					connectedTaxonomies {
						name
					}
					connectedTaxonomyNames
					deleteWithUser
					description
					excludeFromSearch
					graphqlPluralName
					graphqlSingleName
					hasArchive
					hierarchical
					id
					label
					menuIcon
					menuPosition
					name
					tags {
						edges {
							node {
								name
							}
						}
					}
					public
					publiclyQueryable
					restBase
					restControllerClass
					showInAdminBar
					showInGraphql
					showInMenu
					showInNavMenus
					showInRest
					showUi
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
				'pages' => [
					'postTypeInfo' => [
						'canExport' => true,
						'categories' => [
							'edges' => [],
						],
						'connectedTaxonomies' => null,
						'connectedTaxonomyNames' => null,
						'deleteWithUser' => true,
						'description' => '',
						'excludeFromSearch' => false,
						'graphqlPluralName' => 'pages',
						'graphqlSingleName' => 'page',
						'hasArchive' => null,
						'hierarchical' => true,
						'id' => $global_id,
						'label' => 'Pages',
						'menuIcon' => null,
						'menuPosition' => 20,
						'name' => 'page',
						'tags' => [
							'edges' => [],
						],
						'public' => true,
						'publiclyQueryable' => null,
						'restBase' => 'pages',
						'restControllerClass' => 'WP_REST_Posts_Controller',
						'showInAdminBar' => true,
						'showInGraphql' => 'true',
						'showInMenu' => true,
						'showInNavMenus' => true,
						'showInRest' => true,
						'showUi' => true,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostTypeQueryForPageConnections
	 *
	 * This tests post type page connections.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForPageConnections() {

		/**
		 * Create a post
		 */
		$post_id = $this->factory->post->create( [ 'post_type' => 'page' ] );

		// Create a comment and assign it to postType.
		$tag_id = $this->factory->tag->create( [ 'name' => 'A tag' ] );
		$category_id = $this->factory->category->create( [ 'name' => 'A category' ] );

		wp_set_object_terms( $post_id, $tag_id, 'post_tag' );
		wp_set_object_terms( $post_id, $category_id, 'category' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			pages {
				postTypeInfo {
					tags {
						edges {
							node {
								tagId
								name
							}
						}
					}
					categories {
						edges {
							node {
								categoryId
								name
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

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'pages' => [
					'postTypeInfo' => [
						'tags' => [
							'edges' => [],
						],
						'categories' => [
							'edges' => [],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostTypeQueryForMedia
	 *
	 * This tests post type info for attachment post type.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForMedia() {
		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post_type', 'attachment' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			mediaItems {
				postTypeInfo {
					canExport
					categories {
						edges {
							node {
								name
							}
						}
					}
					connectedTaxonomies {
						name
					}
					connectedTaxonomyNames
					deleteWithUser
					description
					excludeFromSearch
					graphqlPluralName
					graphqlSingleName
					hasArchive
					hierarchical
					id
					label
					menuIcon
					menuPosition
					name
					tags {
						edges {
							node {
								name
							}
						}
					}
					public
					publiclyQueryable
					restBase
					restControllerClass
					showInAdminBar
					showInGraphql
					showInMenu
					showInNavMenus
					showInRest
					showUi
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
				'mediaItems' => [
					'postTypeInfo' => [
						'canExport' => true,
						'categories' => [
							'edges' => [],
						],
						'connectedTaxonomies' => null,
						'connectedTaxonomyNames' => null,
						'deleteWithUser' => true,
						'description' => '',
						'excludeFromSearch' => false,
						'graphqlPluralName' => 'mediaItems',
						'graphqlSingleName' => 'mediaItem',
						'hasArchive' => null,
						'hierarchical' => false,
						'id' => $global_id,
						'label' => 'Media',
						'menuIcon' => null,
						'menuPosition' => null,
						'name' => 'attachment',
						'tags' => [
							'edges' => [],
						],
						'public' => true,
						'publiclyQueryable' => true,
						'restBase' => 'media',
						'restControllerClass' => 'WP_REST_Attachments_Controller',
						'showInAdminBar' => true,
						'showInGraphql' => 'true',
						'showInMenu' => true,
						'showInNavMenus' => null,
						'showInRest' => true,
						'showUi' => true,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostTypeQueryForMediaConnections
	 *
	 * This tests connections for the media post type ( attachments ).
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForMediaConnections() {

		/**
		 * Create a post
		 */
		$post_id = $this->factory->post->create( [ 'post_type' => 'page' ] );

		// Create a comment and assign it to postType.
		$tag_id = $this->factory->tag->create( [ 'name' => 'A tag' ] );
		$category_id = $this->factory->category->create( [ 'name' => 'A category' ] );

		wp_set_object_terms( $post_id, $tag_id, 'post_tag' );
		wp_set_object_terms( $post_id, $category_id, 'category' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			pages {
				postTypeInfo {
					tags {
						edges {
							node {
								tagId
								name
							}
						}
					}
					categories {
						edges {
							node {
								categoryId
								name
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

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'pages' => [
					'postTypeInfo' => [
						'tags' => [
							'edges' => [],
						],
						'categories' => [
							'edges' => [],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}
}
