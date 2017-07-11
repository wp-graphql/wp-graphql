<?php

/**
 * WPGraphQL Test Menu Queries
 *
 * NOTE: This file is named "test-0-menu-queries" to ensure it runs first. Something is getting funky with the environment
 * throughout the other tests which is causing things to be a bit funky if this runs later in the process...there's an issue
 * filed to figure out what the deal is to reset the environment.
 *
 * @package WPGraphQL
 */
class WP_GraphQL_Test_Menu_Queries extends WP_UnitTestCase {

	public $registered_menus;
	public $menu_id;

	public function setUp() {

		parent::setUp();

		$this->bootstrapMenus();

	}

	public function tearDown() {
		parent::tearDown();
	}

	public function bootstrapMenus() {
		/**
		 * Create a random menu
		 */
		$menu_id = wp_create_nav_menu( 'test_menu' );

		/**
		 * Register some menus to use in our testing
		 */
		$registered_menus = [
			'header' => 'Header Nav',
			'footer' => 'Footer Nav',
		];
		register_nav_menus( $registered_menus );

		$locations = [];
		foreach ( $registered_menus as $key => $value ) {
			$locations[ $key ] = $menu_id;
		}

		/**
		 * Set the created "test" menu as the active menu for each of the registered locations
		 */
		set_theme_mod( 'nav_menu_locations', $locations );

		/**
		 * Create a random menu
		 */
		$menu = wp_get_nav_menu_object( 'test_menu' );
		$this->menu_id = $menu->term_id;

		/**
		 * Register some menus to use in our testing
		 */
		$this->registered_menus = [
			'header' => 'Header Nav',
			'footer' => 'Footer Nav',
		];
	}

	/**
	 * This simply ensures that the menus we've tested are the same as what's been registered
	 */
	public function testRegisteredMenus() {
		$actual = get_registered_nav_menus();
		$expected = $this->registered_menus;
		$this->assertEquals( $actual, $expected );
	}

	/**
	 * This creates a menu with nested menu items, then queries for them.
	 */
	public function testMenuItemsQuery() {

		$post_1 = $this->factory->post->create();
		$post_2 = $this->factory->post->create();
		$post_3 = $this->factory->post->create();
		$post_4 = $this->factory->post->create();

		$menu_item1 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_1,
			'menu-item-object'      => 'post',
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 1',
			'menu-item-description' => 'Description 1',
			'menu-item-attr-title'  => 'Attr Title 1',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$menu_item2 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_2,
			'menu-item-object'      => 'post',
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 2',
			'menu-item-description' => 'Description 2',
			'menu-item-attr-title'  => 'Attr Title 2',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$menu_item1_child1 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_3,
			'menu-item-object'      => 'post',
			'menu-item-parent-id'   => $menu_item1,
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 3',
			'menu-item-description' => 'Description 3',
			'menu-item-attr-title'  => 'Attr Title 3',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$menu_item1_child2 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_4,
			'menu-item-object'      => 'post',
			'menu-item-parent-id'   => $menu_item1,
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 4',
			'menu-item-description' => 'Description 4',
			'menu-item-attr-title'  => 'Attr Title 4',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$query = '
		query getMenuItems( $menuSlug: String ) {
		  menuItems(where: {menuSlug: $menuSlug}) {
		    edges {
		      node {
		        id
		        menuItemId
		        title
		        connectedObjectType
		        connectedObjectId
		        target
		        linkRelationship
		        url
		        parentItem {
		          id
		        }
		        childItems {
		          edges {
		            node {
		              id
		              menuItemId
		              title
		              connectedObjectType
		              connectedObjectId
		              target
		              linkRelationship
		              url
		            }
		          }
		        }
		      }
		    }
		  }
		}
		';

		$variables = wp_json_encode( [
			'menuSlug' => 'test_menu',
		] );

		$actual = do_graphql_request( $query, 'getMenuItems', $variables );

		$this->assertNotEmpty( $actual );

		/**
		 * Extract the menuItems from the response
		 */
		$menu_items = $actual['data']['menuItems']['edges'];
		$this->assertNotEmpty( $menu_items );

		$expected = [
			'data' => [
				'menuItems' => [
					'edges' => [
						[
							'node' => [
								'id'                  => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item1 ),
								'menuItemId'          => $menu_item1,
								'title'               => 'Menu Item 1',
								'connectedObjectType' => 'post',
								'connectedObjectId'   => "{$post_1}",
								'target'              => '_blank',
								'linkRelationship'    => 'sample relationship',
								'url'                 => get_permalink( $post_1 ),
								'parentItem'          => null,
								'childItems'          => [
									'edges' => [
										[
											'node' => [
												'id'                  => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item1_child1 ),
												'menuItemId'          => $menu_item1_child1,
												'title'               => 'Menu Item 3',
												'connectedObjectType' => 'post',
												'connectedObjectId'   => "{$post_3}",
												'target'              => '_blank',
												'linkRelationship'    => 'sample relationship',
												'url'                 => get_permalink( $post_3 ),
											],
										],
										[
											'node' => [
												'id'                  => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item1_child2 ),
												'menuItemId'          => $menu_item1_child2,
												'title'               => 'Menu Item 4',
												'connectedObjectType' => 'post',
												'connectedObjectId'   => "{$post_4}",
												'target'              => '_blank',
												'linkRelationship'    => 'sample relationship',
												'url'                 => get_permalink( $post_4 ),
											],
										],
									],
								],
							],
						],
						[
							'node' => [
								'id'                  => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item2 ),
								'menuItemId'          => $menu_item2,
								'title'               => 'Menu Item 2',
								'connectedObjectType' => 'post',
								'connectedObjectId'   => "{$post_2}",
								'target'              => '_blank',
								'linkRelationship'    => 'sample relationship',
								'url'                 => get_permalink( $post_2 ),
								'parentItem'          => null,
								'childItems'          => [
									'edges' => [],
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $actual, $expected );

	}

	/**
	 * This tests getting a menu without specifying the location of the menu to query
	 */
	public function testMenuQueryWithoutLocation() {

		$query = '
		query getMenuWithoutLocation {
		  menu {
		    menuItems {
		      edges {
		        node {
		          id
		        }
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query, 'getMenuWithoutLocation' );

		$expected = [
			'data' => [
				'menu' => null,
			],
		];

		$this->assertEquals( $actual, $expected );

	}

	/**
	 * This tests querying for menuItems with a specific menuItem parent
	 */
	public function testQueryMenuItemsWithSpecificParentItemId() {

		$post_1 = $this->factory->post->create();
		$post_3 = $this->factory->post->create();
		$post_4 = $this->factory->post->create();

		$menu_item1 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_1,
			'menu-item-object'      => 'post',
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 1',
			'menu-item-description' => 'Description 1',
			'menu-item-attr-title'  => 'Attr Title 1',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$menu_item1_child1 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_3,
			'menu-item-object'      => 'post',
			'menu-item-parent-id'   => $menu_item1,
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 3',
			'menu-item-description' => 'Description 3',
			'menu-item-attr-title'  => 'Attr Title 3',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$menu_item1_child2 = wp_update_nav_menu_item( $this->menu_id, 0, [
			'menu-item-object-id'   => $post_4,
			'menu-item-object'      => 'post',
			'menu-item-parent-id'   => $menu_item1,
			'menu-item-type'        => 'post_type',
			'menu-item-title'       => 'Menu Item 4',
			'menu-item-description' => 'Description 4',
			'menu-item-attr-title'  => 'Attr Title 4',
			'menu-item-target'      => '_blank',
			'menu-item-classes'     => 'sample-class',
			'menu-item-xfn'         => 'sample relationship',
			'menu-item-status'      => 'publish',
		] );

		$query = '
		query menuItemsWithSpecificParent($parentId:ID){
		  menuItems(where:{parentMenuItemId:$parentId}){
		    edges{
		      node{
		        id
		        title
		      }
		    }
		  }
		}
		';

		$variables = wp_json_encode( [
			'parentId' => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item1 ),
		] );

		$actual = do_graphql_request( $query, 'menuItemsWithSpecificParent', $variables );

		$expected = [
			'data' => [
				'menuItems' => [
					'edges' => [
						[
							'node' => [
								'id'    => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item1_child1 ),
								'title' => 'Menu Item 3',
							],
						],
						[
							'node' => [
								'id'    => \GraphQLRelay\Relay::toGlobalId( 'nav_menu_item', $menu_item1_child2 ),
								'title' => 'Menu Item 4',
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $actual, $expected );

	}

}
