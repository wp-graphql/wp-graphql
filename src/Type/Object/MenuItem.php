<?php

namespace WPGraphQL\Type;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;

register_graphql_object_type(
	'MenuItem',
	[
		'description' => __( 'Navigation menu items are the individual items assigned to a menu. These are rendered as the links in a navigation menu.', 'wp-graphql' ),
		'fields'      => [
			'id'               => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'Relay ID of the menu item.', 'wp-graphql' ),
			],
			'cssClasses'       => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'Class attribute for the menu item link', 'wp-graphql' ),
			],
			'description'      => [
				'type'        => 'String',
				'description' => __( 'Description of the menu item.', 'wp-graphql' ),
			],
			'label'            => [
				'type'        => 'String',
				'description' => __( 'Label or title of the menu item.', 'wp-graphql' ),
			],
			'linkRelationship' => [
				'type'        => 'String',
				'description' => __( 'Link relationship (XFN) of the menu item.', 'wp-graphql' ),
			],
			'menuItemId'       => [
				'type'        => 'Int',
				'description' => __( 'WP ID of the menu item.', 'wp-graphql' ),
			],
			'target'           => [
				'type'        => 'String',
				'description' => __( 'Target attribute for the menu item link.', 'wp-graphql' ),
			],
			'title'            => [
				'type'        => 'String',
				'description' => __( 'Title attribute for the menu item link', 'wp-graphql' ),
			],
			'url'              => [
				'type'        => 'String',
				'description' => __( 'URL or destination of the menu item.', 'wp-graphql' ),
			],
			'isRestricted'     => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
			],
			'connectedObject'  => [
				'type'        => 'MenuItemObjectUnion',
				'description' => __( 'The object connected to this menu item.', 'wp-graphql' ),
				'resolve'     => function( $menu_item, array $args, $context, $info ) {

					$object_id   = intval( get_post_meta( $menu_item->menuItemId, '_menu_item_object_id', true ) );
					$object_type = get_post_meta( $menu_item->menuItemId, '_menu_item_type', true );

					switch ( $object_type ) {
						// Post object
						case 'post_type':
							$resolved_object = DataSource::resolve_post_object( $object_id, $context );
							break;

						// Taxonomy term
						case 'taxonomy':
							$resolved_object = DataSource::resolve_term_object( $object_id, $context );
							break;
						default:
							$resolved_object = $menu_item;
							break;
					}

					/**
					 * Allow users to override how nav menu items are resolved.
					 * This is useful since we often add taxonomy terms to menus
					 * but would prefer to represent the menu item in other ways,
					 * e.g., a linked post object (or vice-versa).
					 *
					 * @param \WP_Post|\WP_Term $resolved_object Post or term connected to MenuItem
					 * @param array             $args            Array of arguments input in the field as part of the GraphQL query
					 * @param AppContext        $context         Object containing app context that gets passed down the resolve tree
					 * @param ResolveInfo       $info            Info about fields passed down the resolve tree
					 * @param int               $object_id       Post or term ID of connected object
					 * @param string            $object_type     Type of connected object ("post_type" or "taxonomy")
					 *
					 * @since 0.0.30
					 */
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
			],
		],
	]
);
