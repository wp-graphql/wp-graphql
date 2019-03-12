<?php

namespace WPGraphQL\Type;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\MenuItem;

register_graphql_object_type( 'MenuItem', [
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
				'list_of' => 'String'
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
		'connectedObject'  => [
			'type'        => 'MenuItemObjectUnion',
			'description' => __( 'The object connected to this menu item.', 'wp-graphql' ),
			'resolve'     => function( $menu_item, array $args, $context, $info ) {

				$object_id   = intval( get_post_meta( $menu_item->menuItemId, '_menu_item_object_id', true ) );
				$object_type = get_post_meta( $menu_item->menuItemId, '_menu_item_type', true );

				$post_id = absint( $object_id );
				$context->PostObjectLoader->buffer( [ $post_id ] );
				return new Deferred( function() use ( $object_id, $menu_item, $object_type, $context, $args, $info ) {

					switch ( $object_type ) {
						// Post object
						case 'post_type':
							$resolved_object = $context->PostObjectLoader->load( $object_id );
							break;

						// Taxonomy term
						case 'taxonomy':
							$resolved_object = get_term( $object_id );
							$resolved_object = isset( $resolved_object->term_id ) && isset( $resolved_object->taxonomy ) ? DataSource::resolve_term_object( $resolved_object->term_id, $resolved_object->taxonomy ) : $resolved_object;
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

				});
			},
		]
	]
] );
