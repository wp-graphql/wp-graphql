<?php

namespace WPGraphQL\Type\Menu;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class MenuQuery
 *
 * @package WPGraphQL\Type\Menu
 * @since   0.0.30
 */
class MenuQuery {

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
				'type' => Types::menu(),
				'description' => __( 'A WordPress navigation menu', 'wp-graphql' ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_term_object( $id_components['id'], 'nav_menu' );
				},
			];

		}

		return self::$root_query;
	}

}
