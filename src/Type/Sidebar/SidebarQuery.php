<?php

namespace WPGraphQL\Type\Sidebar;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class SidebarQuery
 *
 * @package WPGraphQL\Type\Sidebar
 * @since   0.0.31
 */
class SidebarQuery {
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
	 * @since  0.0.31
	 */
	public static function root_query() {
    if ( null === self::$root_query ) {

			self::$root_query = [
				'type' => Types::sidebar(),
				'description' => __( 'A WordPress sidebar', 'wp-graphql' ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_sidebar( $id_components['id'] );
				},
			];

		}

		return self::$root_query;
  }
}