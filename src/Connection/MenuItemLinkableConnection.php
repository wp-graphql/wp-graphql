<?php
namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\MenuItem;

class MenuItemLinkableConnection {

	/**
	 * Registers connections to the MenuItemLinkable type
	 *
	 * @return void
	 */
	public static function register_connections() {

		register_graphql_connection([
			'fromType'      => 'MenuItem',
			'toType'        => 'MenuItemLinkable',
			'description'   => __( 'Connection from MenuItem to it\'s connected node', 'wp-graphql' ),
			'fromFieldName' => 'connectedNode',
			'oneToOne'      => true,
			'resolve'       => function( MenuItem $menu_item, $args, AppContext $context, ResolveInfo $info ) {

				$object_id   = intval( get_post_meta( $menu_item->databaseId, '_menu_item_object_id', true ) );
				$object_type = get_post_meta( $menu_item->databaseId, '_menu_item_type', true );

				$resolver = null;
				switch ( $object_type ) {
					// Post object
					case 'post_type':
						$resolver = new PostObjectConnectionResolver( $menu_item, $args, $context, $info );
						$resolver->set_query_arg( 'p', $object_id );
						break;

					// Taxonomy term
					case 'taxonomy':
						$resolver = new TermObjectConnectionResolver( $menu_item, $args, $context, $info );
						$resolver->set_query_arg( 'include', $object_id );
						break;
					default:
						$resolved_object = null;
						break;
				}

				return ! empty( $resolver ) ? $resolver->one_to_one()->get_connection() : null;

			},
		]);

	}

}
