<?php

use GraphQLRelay\Relay;

class MenuItemQueriesTest extends \Codeception\TestCase\WPTestCase
{

	public $admin;

	public function setUp(): void
	{
		parent::setUp();
		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);
		WPGraphQL::clear_schema();
		$this->register_types();

	}

	public function tearDown(): void
	{
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function register_types()
	{

		add_action('graphql_register_types', function ($type_registry) {

			$args = [
				'show_in_nav_menus' => true,
				'show_in_graphql'   => true,
			];

			$possible_types = [];

			// Add post types that are allowed in WPGraphQL.
			foreach (get_post_types($args) as $type) {
				$post_type_object = get_post_type_object($type);
				if (isset($post_type_object->graphql_single_name)) {
					$possible_types[] = $post_type_object->graphql_single_name;
				}
			}

			// Add taxonomies that are allowed in WPGraphQL.
			foreach (get_taxonomies($args) as $type) {
				$tax_object = get_taxonomy($type);
				if (isset($tax_object->graphql_single_name)) {
					$possible_types[] = $tax_object->graphql_single_name;
				}
			}

			register_graphql_union_type(
				'MenuItemObjectUnion',
				[
					'typeNames'   => $possible_types,
					'description' => __('Deprecated in favor of MenuItemLinkeable Interface', 'wp-graphql'),
					'resolveType' => function ($object) use ($type_registry) {
						// Post object
						if ($object instanceof \WPGraphQL\Model\Post && isset($object->post_type) && ! empty($object->post_type)) {
							$post_type_object = get_post_type_object($object->post_type);

							return $type_registry->get_type($post_type_object->graphql_single_name);
						}

						// Taxonomy term
						if ($object instanceof \WPGraphQL\Model\Term && ! empty($object->taxonomyName)) {
							$tax_object = get_taxonomy($object->taxonomyName);

							return $type_registry->get_type($tax_object->graphql_single_name);
						}

						return $object;
					},
				]
			);

			register_graphql_field('MenuItem', 'connectedObject', [
				'type'              => 'MenuItemObjectUnion',
				'deprecationReason' => __('Deprecated in favor of the connectedNode field', 'wp-graphql'),
				'description'       => __('The object connected to this menu item.', 'wp-graphql'),
				'resolve'           => function ($menu_item, array $args, $context, $info) {

					$object_id   = intval(get_post_meta($menu_item->menuItemId, '_menu_item_object_id', true));
					$object_type = get_post_meta($menu_item->menuItemId, '_menu_item_type', true);

					switch ($object_type) {
						// Post object
						case 'post_type':
							$resolved_object = $context->get_loader('post')->load_deferred($object_id);
							break;

						// Taxonomy term
						case 'taxonomy':
							$resolved_object = $context->get_loader('term')->load_deferred($object_id);
							break;
						default:
							$resolved_object = null;
							break;
					}

					return apply_filters(
						'graphql_resolve_menu_item',
						$resolved_object,
						$args,
						$context,
						$info,
						$object_id,
						$object_type
					);
				},

			]);
		});
	}

	public function testMenuItemQuery()
	{

		add_theme_support('nav_menus');
		$location_name = 'test-location';
		register_nav_menu($location_name, 'test menu...');

		$menu_slug = 'my-test-menu';
		$menu_id   = wp_create_nav_menu($menu_slug);
		$post_id   = $this->factory()->post->create();

		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			[
				'menu-item-title'     => 'Menu item',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			]
		);

		set_theme_mod('nav_menu_locations', [ $location_name => $menu_id ]);

		codecept_debug(get_theme_mod('nav_menu_locations'));

		$menu_item_relay_id = Relay::toGlobalId('post', $menu_item_id);

		$query = '
		{
			menuItem( id: "' . $menu_item_relay_id . '" ) {
				id
				databaseId
				connectedObject {
					... on Post {
						id
						postId
					}
				}
				locations
				menu {
				  node {
				    slug
				    locations
				  }
				}
			}
		}
		';

		$actual = do_graphql_request($query);


		codecept_debug($actual);

		$this->assertEquals($menu_item_id, $actual['data']['menuItem']['databaseId']);
		$this->assertEquals($menu_item_relay_id, $actual['data']['menuItem']['id']);
		$this->assertEquals($post_id, $actual['data']['menuItem']['connectedObject']['postId']);
		$this->assertEquals($menu_slug, $actual['data']['menuItem']['menu']['node']['slug']);
		$this->assertEquals([ \WPGraphQL\Type\WPEnumType::get_safe_name($location_name) ], $actual['data']['menuItem']['locations']);
		$this->assertEquals([ \WPGraphQL\Type\WPEnumType::get_safe_name($location_name) ], $actual['data']['menuItem']['menu']['node']['locations']);

		$old_id = Relay::toGlobalId('nav_menu_itemci', $menu_item_id);

		$query = '
		{
			menuItem( id: "' . $old_id . '" ) {
				id
				databaseId
				connectedObject {
					... on Post {
						id
						postId
					}
				}
				locations
				menu {
				  node {
				    slug
				    locations
				  }
				}
			}
		}
		';

		$actual = do_graphql_request($query);


		codecept_debug($actual);

		$this->assertEquals($menu_item_id, $actual['data']['menuItem']['databaseId']);
		$this->assertEquals($menu_item_relay_id, $actual['data']['menuItem']['id']);
		$this->assertEquals($post_id, $actual['data']['menuItem']['connectedObject']['postId']);
		$this->assertEquals($menu_slug, $actual['data']['menuItem']['menu']['node']['slug']);
		$this->assertEquals([ \WPGraphQL\Type\WPEnumType::get_safe_name($location_name) ], $actual['data']['menuItem']['locations']);
		$this->assertEquals([ \WPGraphQL\Type\WPEnumType::get_safe_name($location_name) ], $actual['data']['menuItem']['menu']['node']['locations']);
	}
}
