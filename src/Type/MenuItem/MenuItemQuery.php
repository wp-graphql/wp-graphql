<?php

namespace WPGraphQL\Type\MenuItem;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class MenuItemQuery
 *
 * @package WPGraphQL\Type\MenuItem
 * @since   0.0.30
 */
class MenuItemQuery {

	/**
	 * Holds the root_query field definition
	 *
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Method that returns the root query field definition
	 *
	 * @return array
	 * @since  0.0.30
	 */
	public static function root_query() {

		if ( null === self::$root_query ) {

			self::$root_query = [
				'type' => Types::menu_item(),
				'description' => __( 'A WordPress navigation menu item', 'wp-graphql' ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_post_object( $id_components['id'], 'nav_menu_item' );
				},
			];

		}

		return self::$root_query;
	}

}
