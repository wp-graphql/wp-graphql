<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\MenuConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\MenuItem as MenuItemModel;

class MenuItem {

	/**
	 * Register the MenuItem Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'MenuItem',
			[
				'description' => static function () {
					return __( 'Navigation menu items are the individual items assigned to a menu. These are rendered as the links in a navigation menu.', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node', 'DatabaseIdentifier' ],
				'model'       => MenuItemModel::class,
				'connections' => [
					'connectedNode' => [
						'toType'      => 'MenuItemLinkable',
						'description' => static function () {
							return __( 'Connection from MenuItem to it\'s connected node', 'wp-graphql' );
						},
						'oneToOne'    => true,
						'resolve'     => static function ( MenuItemModel $menu_item, $args, AppContext $context, ResolveInfo $info ) {
							if ( ! isset( $menu_item->databaseId ) ) {
								return null;
							}

							$object_id   = (int) get_post_meta( $menu_item->databaseId, '_menu_item_object_id', true );
							$object_type = get_post_meta( $menu_item->databaseId, '_menu_item_type', true );

							/**
							 * When this filter returns anything other than null it will be used as the resolved connection for the menu item's connected node, short-circuiting the default resolution.
							 *
							 * This is useful since we often add taxonomy terms to menus but would prefer to represent the menu item in other ways.
							 * E.g., a linked post object (or vice-versa).
							 *
							 * @param ?\GraphQL\Deferred                   $deferred_connection The AbstractConnectionResolver's connection, or null to continue with the default resolution.
							 * @param \WPGraphQL\Model\MenuItem            $menu_item           The MenuItem model.
							 * @param array<string,mixed>                  $args                The GraphQL args for the connection.
							 * @param \WPGraphQL\AppContext                $context             The AppContext object.
							 * @param \GraphQL\Type\Definition\ResolveInfo $info                The ResolveInfo object.
							 * @param int                                  $object_id           The ID of the connected object.
							 * @param string                               $object_type         The type of the connected object.
							 */
							$deferred_connection = apply_filters( 'graphql_pre_resolve_menu_item_connected_node', null, $menu_item, $args, $context, $info, $object_id, $object_type );

							if ( null !== $deferred_connection ) {
								return $deferred_connection;
							}

							// Handle the default resolution.
							switch ( $object_type ) {
								// Post object
								case 'post_type':
									$resolver = new PostObjectConnectionResolver( $menu_item, $args, $context, $info, 'any' );
									$resolver->set_query_arg( 'p', $object_id );

									// connected objects to menu items can be any post status
									$resolver->set_query_arg( 'post_status', 'any' );
									break;

								// Taxonomy term
								case 'taxonomy':
									$resolver = new TermObjectConnectionResolver( $menu_item, $args, $context, $info );
									$resolver->set_query_arg( 'include', $object_id );
									break;
								default:
									$resolver = null;
									break;
							}

							return null !== $resolver ? $resolver->one_to_one()->get_connection() : null;
						},
					],
					'menu'          => [
						'toType'      => 'Menu',
						'description' => static function () {
							return __( 'The Menu a MenuItem is part of', 'wp-graphql' );
						},
						'oneToOne'    => true,
						'resolve'     => static function ( MenuItemModel $menu_item, $args, $context, $info ) {
							$resolver = new MenuConnectionResolver( $menu_item, $args, $context, $info );
							$resolver->set_query_arg( 'include', $menu_item->menuDatabaseId );

							return $resolver->one_to_one()->get_connection();
						},
					],
				],
				'fields'      => static function () {
					return [
						'id'               => [
							'description' => static function () {
								return __( 'The globally unique identifier of the nav menu item object.', 'wp-graphql' );
							},
						],
						'parentId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'The globally unique identifier of the parent nav menu item object.', 'wp-graphql' );
							},
						],
						'parentDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The database id of the parent menu item or null if it is the root', 'wp-graphql' );
							},
						],
						'cssClasses'       => [
							'type'        => [
								'list_of' => 'String',
							],
							'description' => static function () {
								return __( 'Class attribute for the menu item link', 'wp-graphql' );
							},
						],
						'description'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Description of the menu item.', 'wp-graphql' );
							},
						],
						'label'            => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Label or title of the menu item.', 'wp-graphql' );
							},
						],
						'linkRelationship' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Link relationship (XFN) of the menu item.', 'wp-graphql' );
							},
						],
						'menuItemId'       => [
							'type'              => 'Int',
							'description'       => static function () {
								return __( 'WP ID of the menu item.', 'wp-graphql' );
							},
							'deprecationReason' => static function () {
								return __( 'Deprecated in favor of the databaseId field', 'wp-graphql' );
							},
						],
						'target'           => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Target attribute for the menu item link.', 'wp-graphql' );
							},
						],
						'title'            => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Title attribute for the menu item link', 'wp-graphql' );
							},
						],
						'url'              => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'URL or destination of the menu item.', 'wp-graphql' );
							},
						],
						// Note: this field is added to the MenuItem type instead of applied by the "UniformResourceIdentifiable" interface
						// because a MenuItem is not identifiable by a uri, the connected resource is identifiable by the uri.
						'uri'              => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The uri of the resource the menu item links to', 'wp-graphql' );
							},
						],
						'path'             => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Path for the resource. Relative path for internal resources. Absolute path for external resources.', 'wp-graphql' );
							},
						],
						'isRestricted'     => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
						'order'            => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Menu item order', 'wp-graphql' );
							},
						],
						'locations'        => [
							'type'        => [
								'list_of' => 'MenuLocationEnum',
							],
							'description' => static function () {
								return __( 'The locations the menu item\'s Menu is assigned to', 'wp-graphql' );
							},
						],
						'connectedObject'  => [
							'type'              => 'MenuItemObjectUnion',
							'deprecationReason' => static function () {
								return __( 'Deprecated in favor of the connectedNode field', 'wp-graphql' );
							},
							'description'       => static function () {
								return __( 'The object connected to this menu item.', 'wp-graphql' );
							},
							'resolve'           => static function ( $menu_item, array $args, AppContext $context, $info ) {
								$object_id   = intval( get_post_meta( $menu_item->menuItemId, '_menu_item_object_id', true ) );
								$object_type = get_post_meta( $menu_item->menuItemId, '_menu_item_type', true );

								switch ( $object_type ) {
									// Post object
									case 'post_type':
										$resolved_object = $context->get_loader( 'post' )->load_deferred( $object_id );
										break;

									// Taxonomy term
									case 'taxonomy':
										$resolved_object = $context->get_loader( 'term' )->load_deferred( $object_id );
										break;
									default:
										$resolved_object = null;
										break;
								}

								/**
								 * Allow users to override how nav menu items are resolved.
								 * This is useful since we often add taxonomy terms to menus
								 * but would prefer to represent the menu item in other ways,
								 * e.g., a linked post object (or vice-versa).
								 *
								 * @param \WP_Post|\WP_Term                    $resolved_object Post or term connected to MenuItem
								 * @param array<string,mixed>                  $args            Array of arguments input in the field as part of the GraphQL query
								 * @param \WPGraphQL\AppContext                $context         Object containing app context that gets passed down the resolve tree
								 * @param \GraphQL\Type\Definition\ResolveInfo $info            Info about fields passed down the resolve tree
								 * @param int                                  $object_id       Post or term ID of connected object
								 * @param string                               $object_type     Type of connected object ("post_type" or "taxonomy")
								 *
								 * @since 0.0.30
								 */
								return apply_filters_deprecated(
									'graphql_resolve_menu_item',
									[
										$resolved_object,
										$args,
										$context,
										$info,
										$object_id,
										$object_type,
									],
									'1.22.0',
									'graphql_pre_resolve_menu_item_connected_node',
									__( 'Use the `graphql_pre_resolve_menu_item_connected_node` filter on `connectedNode` instead.', 'wp-graphql' )
								);
							},
						],
					];
				},
			]
		);
	}
}
