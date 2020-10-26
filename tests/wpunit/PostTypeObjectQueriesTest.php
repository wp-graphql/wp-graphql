<?php

class PostTypeObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown(): void {
		// your tear down methods here
		wp_set_current_user( 0 );

		// then
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

		$this->factory()->post->create( [
			'post_type'   => 'Post',
			'post_status' => 'publish',
			'post_title'  => 'Test',
		] );
		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post_type', 'post' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			posts(first:1) {
				nodes {
					contentType {
					  node {
						canExport
						connectedTaxonomies {
							nodes {
								name
							}
						}
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
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		$post_type_object = get_post_type_object( 'post' );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'posts' => [
				'nodes' => [
					[
						'contentType' => [
							'node' => [
								'canExport'              => true,
								'connectedTaxonomies'    => [
									'nodes' => [
										[
											'name' => 'category'
										],
										[
											'name' => 'post_tag'
										],
										[
											'name' => 'post_format'
										],
									]

								],
								'deleteWithUser'         => true,
								'description'            => '',
								'excludeFromSearch'      => false,
								'graphqlPluralName'      => 'posts',
								'graphqlSingleName'      => 'post',
								'hasArchive'             => (boolean) get_post_type_archive_link( 'post' ),
								'hierarchical'           => false,
								'id'                     => $global_id,
								'label'                  => 'Posts',
								'labels'                 => [
									'name'                => 'Posts',
									'singularName'        => 'Post',
									'addNew'              => 'Add New',
									'addNewItem'          => 'Add New Post',
									'editItem'            => 'Edit Post',
									'newItem'             => 'New Post',
									'viewItem'            => 'View Post',
									'viewItems'           => 'View Posts',
									'searchItems'         => 'Search Posts',
									'notFound'            => 'No posts found.',
									'notFoundInTrash'     => 'No posts found in Trash.',
									'parentItemColon'     => null,
									'allItems'            => 'All Posts',
									'archives'            => 'Post Archives',
									'attributes'          => 'Post Attributes',
									'insertIntoItem'      => 'Insert into post',
									'uploadedToThisItem'  => 'Uploaded to this post',
									'featuredImage'       => $post_type_object->labels->featured_image,
									'setFeaturedImage'    => 'Set featured image',
									'removeFeaturedImage' => 'Remove featured image',
									'useFeaturedImage'    => null,
									'menuName'            => 'Posts',
									'filterItemsList'     => 'Filter posts list',
									'itemsListNavigation' => 'Posts list navigation',
									'itemsList'           => 'Posts list',
								],
								'menuIcon'               => $post_type_object->menu_icon,
								'menuPosition'           => 5,
								'name'                   => 'post',
								'public'                 => true,
								'publiclyQueryable'      => true,
								'restBase'               => 'posts',
								'restControllerClass'    => 'WP_REST_Posts_Controller',
								'showInAdminBar'         => true,
								'showInGraphql'          => true,
								'showInMenu'             => true,
								'showInNavMenus'         => true,
								'showInRest'             => true,
								'showUi'                 => true,
							],
						],
					]
				]
			]
		];

		codecept_debug( $actual );

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testPostTypeQueryForPages
	 *
	 * This tests post type info for pages post type.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForPages() {

		$this->factory()->post->create( [
			'post_type'   => 'Page',
			'post_status' => 'publish',
			'post_title'  => 'Test',
		] );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post_type', 'page' );

		$post_type_object = get_post_type_object( 'page' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			pages(first:1) {
				nodes {
				  contentType {
				    node {
						canExport
						connectedTaxonomies {
							nodes {
							  name
							}
						}
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
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'pages' => [
				'nodes' => [
					[
						'contentType' => [
							'node' => [
								'canExport'           => true,
								'connectedTaxonomies' => null,
								'deleteWithUser'      => true,
								'description'         => '',
								'excludeFromSearch'   => false,
								'graphqlPluralName'   => 'pages',
								'graphqlSingleName'   => 'page',
								'hasArchive'          => false,
								'hierarchical'        => true,
								'id'                  => $global_id,
								'label'               => 'Pages',
								'menuIcon'            => $post_type_object->menu_icon,
								'menuPosition'        => 20,
								'name'                => 'page',
								'public'              => true,
								'publiclyQueryable'   => false,
								'restBase'            => 'pages',
								'restControllerClass' => 'WP_REST_Posts_Controller',
								'showInAdminBar'      => true,
								'showInGraphql'       => true,
								'showInMenu'          => true,
								'showInNavMenus'      => true,
								'showInRest'          => true,
								'showUi'              => true,
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testPostTypeQueryForMedia
	 *
	 * This tests post type info for attachment post type.
	 *
	 * @since 0.0.5
	 */
	public function testPostTypeQueryForMedia() {

		$post_id = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Test',
		] );

		$this->factory()->post->create( [
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'post_title'  => 'Test',
			'post_parent' => $post_id,
		] );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post_type', 'attachment' );

		$post_type_object = get_post_type_object( 'attachment' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			mediaItems(first:1) {
			    nodes {
			        contentType {
			          node {
						canExport
						connectedTaxonomies {
							nodes {
							  name
							}
						}
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
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'mediaItems' => [
				'nodes' => [
					[
						'contentType' => [
							'node' => [
								'canExport'           => true,
								'connectedTaxonomies' => null,
								'deleteWithUser'      => true,
								'description'         => '',
								'excludeFromSearch'   => false,
								'graphqlPluralName'   => 'mediaItems',
								'graphqlSingleName'   => 'mediaItem',
								'hasArchive'          => false,
								'hierarchical'        => false,
								'id'                  => $global_id,
								'label'               => 'Media',
								'menuIcon'            => $post_type_object->menu_icon,
								'menuPosition'        => null,
								'name'                => 'attachment',
								'public'              => true,
								'publiclyQueryable'   => true,
								'restBase'            => 'media',
								'restControllerClass' => 'WP_REST_Attachments_Controller',
								'showInAdminBar'      => true,
								'showInGraphql'       => true,
								'showInMenu'          => true,
								'showInNavMenus'      => false,
								'showInRest'          => true,
								'showUi'              => true,
							],
						],
					],

				],
			],
		];


		$this->assertEquals( $expected, $actual['data'] );
	}

}
