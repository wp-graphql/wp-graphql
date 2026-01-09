<?php

use GraphQLRelay\Relay;

class MenuItemQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $location_name;
	public $menu_id;
	public $menu_slug;


	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		add_theme_support( 'nav_menus' );

		$this->location_name = 'test-location';
		register_nav_menu( $this->location_name, 'test menu...' );

		$this->menu_slug = 'my-test-menu';
		$this->menu_id   = wp_create_nav_menu( $this->menu_slug );

		set_theme_mod( 'nav_menu_locations', [ $this->location_name => $this->menu_id ] );

		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		remove_theme_support( 'nav_menus' );
		wp_delete_nav_menu( $this->menu_id );
		unregister_nav_menu( $this->location_name );

		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function testMenuItemQueryWithPostObject() {
		$post_id   = $this->factory()->post->create();
		$permalink = get_permalink( $post_id );

		$menu_args = [
			'menu-item-attr-title'  => 'Menu item',
			'menu-item-classes'     => 'my-class my-other-class',
			'menu-item-description' => 'Some description',
			'menu-item-object-id'   => $post_id,
			'menu-item-object'      => 'post',
			'menu-item-position'    => 1,
			'menu-item-status'      => 'publish',
			'menu-item-title'       => 'Menu item',
			'menu-item-type'        => 'post_type',
			'menu-item-target'      => '_blank',
		];

		$menu_item_id = wp_update_nav_menu_item( $this->menu_id, 0, $menu_args );

		$menu_item_relay_id = Relay::toGlobalId( 'post', $menu_item_id );

		codecept_debug( get_theme_mod( 'nav_menu_locations' ) );

		// test with database ID.
		$query = $this->get_query();

		$variables = [
			'id'     => $menu_item_id,
			'idType' => 'DATABASE_ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( explode( ' ', $menu_args['menu-item-classes'] ), $actual['data']['menuItem']['cssClasses'] );
		$this->assertEquals( $post_id, $actual['data']['menuItem']['connectedNode']['node']['databaseId'] );
		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_args['menu-item-description'], $actual['data']['menuItem']['description'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $menu_args['menu-item-title'], $actual['data']['menuItem']['label'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['locations'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['menu']['node']['locations'] );
		$this->assertEquals( $this->menu_slug, $actual['data']['menuItem']['menu']['node']['slug'] );
		$this->assertEquals( $menu_args['menu-item-position'], $actual['data']['menuItem']['order'] );
		$this->assertEquals( $menu_args['menu-item-target'], $actual['data']['menuItem']['target'] );
		$this->assertEquals( $menu_args['menu-item-attr-title'], $actual['data']['menuItem']['title'] );
		$this->assertEquals( str_ireplace( home_url(), '', $permalink ), $actual['data']['menuItem']['uri'] );
		$this->assertEquals( $permalink, $actual['data']['menuItem']['url'] );

		// Test with relay Id.
		$variables = [
			'id'     => $menu_item_relay_id,
			'idType' => 'ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
	}

	public function testMenuItemQueryWithTermObject() {
		$term_id   = $this->factory()->term->create( [ 'taxonomy' => 'category' ] );
		$permalink = get_term_link( $term_id );

		$menu_args = [
			'menu-item-attr-title'  => 'Menu item',
			'menu-item-classes'     => 'my-class my-other-class',
			'menu-item-description' => 'Some description',
			'menu-item-object-id'   => $term_id,
			'menu-item-object'      => 'category',
			'menu-item-position'    => 1,
			'menu-item-status'      => 'publish',
			'menu-item-title'       => 'Menu item',
			'menu-item-type'        => 'taxonomy',
			'menu-item-target'      => '_blank',
		];

		$menu_item_id = wp_update_nav_menu_item( $this->menu_id, 0, $menu_args );

		$menu_item_relay_id = Relay::toGlobalId( 'post', $menu_item_id );

		codecept_debug( get_theme_mod( 'nav_menu_locations' ) );

		// test with database ID.
		$query = $this->get_query();

		$variables = [
			'id'     => $menu_item_id,
			'idType' => 'DATABASE_ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( explode( ' ', $menu_args['menu-item-classes'] ), $actual['data']['menuItem']['cssClasses'] );
		$this->assertEquals( $term_id, $actual['data']['menuItem']['connectedNode']['node']['databaseId'] );
		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_args['menu-item-description'], $actual['data']['menuItem']['description'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $menu_args['menu-item-title'], $actual['data']['menuItem']['label'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['locations'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['menu']['node']['locations'] );
		$this->assertEquals( $this->menu_slug, $actual['data']['menuItem']['menu']['node']['slug'] );
		$this->assertEquals( $menu_args['menu-item-position'], $actual['data']['menuItem']['order'] );
		$this->assertEquals( $menu_args['menu-item-target'], $actual['data']['menuItem']['target'] );
		$this->assertEquals( $menu_args['menu-item-attr-title'], $actual['data']['menuItem']['title'] );
		$this->assertEquals( str_ireplace( home_url(), '', $permalink ), $actual['data']['menuItem']['uri'] );
		$this->assertEquals( $permalink, $actual['data']['menuItem']['url'] );

		// Test with relay Id.
		$variables = [
			'id'     => $menu_item_relay_id,
			'idType' => 'ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
	}

	public function testMenuItemWithCustomFilters() {
		$term_id   = $this->factory()->term->create( [ 'taxonomy' => 'category' ] );
		$post_id   = $this->factory()->post->create(); // We'll resolve to this.
		$permalink = get_term_link( $term_id ); // The menu permalink stays the same.

		$menu_args = [
			'menu-item-attr-title'  => 'Menu item',
			'menu-item-classes'     => 'my-class my-other-class',
			'menu-item-description' => 'Some description',
			'menu-item-object-id'   => $term_id,
			'menu-item-object'      => 'category',
			'menu-item-position'    => 1,
			'menu-item-status'      => 'publish',
			'menu-item-title'       => 'Menu item',
			'menu-item-type'        => 'taxonomy',
			'menu-item-target'      => '_blank',
		];

		$menu_item_id = wp_update_nav_menu_item( $this->menu_id, 0, $menu_args );

		$menu_item_relay_id = Relay::toGlobalId( 'post', $menu_item_id );

		// Filter the resolver.
		add_filter(
			'graphql_pre_resolve_menu_item_connected_node',
			static function ( $deferred_connection, $source, $args, $context, $info ) use ( $post_id ) {
				$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, [], $context, $info, 'any' );
				$resolver->set_query_arg( 'p', $post_id );

				return $resolver->one_to_one()->get_connection();
			},
			10,
			7
		);

		// test with database ID.
		$query = $this->get_query();

		$variables = [
			'id'     => $menu_item_id,
			'idType' => 'DATABASE_ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( explode( ' ', $menu_args['menu-item-classes'] ), $actual['data']['menuItem']['cssClasses'] );
		$this->assertEquals( $post_id, $actual['data']['menuItem']['connectedNode']['node']['databaseId'] );
		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_args['menu-item-description'], $actual['data']['menuItem']['description'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $menu_args['menu-item-title'], $actual['data']['menuItem']['label'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['locations'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['menu']['node']['locations'] );
		$this->assertEquals( $this->menu_slug, $actual['data']['menuItem']['menu']['node']['slug'] );
		$this->assertEquals( $menu_args['menu-item-position'], $actual['data']['menuItem']['order'] );
		$this->assertEquals( $menu_args['menu-item-target'], $actual['data']['menuItem']['target'] );
		$this->assertEquals( $menu_args['menu-item-attr-title'], $actual['data']['menuItem']['title'] );
		$this->assertEquals( str_ireplace( home_url(), '', $permalink ), $actual['data']['menuItem']['uri'] );
		$this->assertEquals( $permalink, $actual['data']['menuItem']['url'] );

		// Cleanup
		remove_all_filters( 'graphql_pre_resolve_menu_item_connected_node' );
	}

	public function testCustomMenuItemWithChildren() {
		$parent_args = [
			'menu-item-title'     => 'Parent Item',
			'menu-item-parent-id' => 0,
			'menu-item-url'       => 'http://example.com/',
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
		];

		$parent_database_id = wp_update_nav_menu_item( $this->menu_id, 0, $parent_args );

		$child_args = [
			'menu-item-title'     => 'Child Item',
			'menu-item-parent-id' => $parent_database_id,
			'menu-item-url'       => 'http://example.com/child',
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
		];

		$child_database_id = wp_update_nav_menu_item( $this->menu_id, 0, $child_args );

		$query = $this->get_query();

		$variables = [
			'id'     => $parent_database_id,
			'idType' => 'DATABASE_ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( $parent_database_id, $actual['data']['menuItem']['databaseId'] );

		// When external, these are all the same.
		$this->assertEquals( $parent_args['menu-item-url'], $actual['data']['menuItem']['path'] );
		$this->assertEquals( $parent_args['menu-item-url'], $actual['data']['menuItem']['uri'] );
		$this->assertEquals( $parent_args['menu-item-url'], $actual['data']['menuItem']['url'] );

		$this->assertEquals( $parent_database_id, $actual['data']['menuItem']['childItems']['nodes'][0]['parentDatabaseId'] );
		$this->assertEquals( $child_database_id, $actual['data']['menuItem']['childItems']['nodes'][0]['databaseId'] );
	}

	public function get_query() {
		return '
			query menuItem( $id: ID!, $idType: MenuItemNodeIdTypeEnum) {
				menuItem( id: $id, idType: $idType ) {
					childItems {
						nodes {
							databaseId
							parentDatabaseId
						}
					}
					connectedNode {
						node {
							__typename
							... on Post {
								id
								databaseId
							}
							... on TermNode {
								id
								databaseId
							}
						}
					}
					cssClasses
					databaseId
					description
					id
					label
					linkRelationship
					locations
					menu {
						node {
							locations
							slug
						}
					}
					order
					parentDatabaseId
					parentId
					path
					target
					title
					uri
					url
				}
			}
		';
	}
}
